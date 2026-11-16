@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('general.categories') }}
@parent
@stop

{{-- Page content --}}
@section('content')

<div class="row">
  <div class="col-md-12">
    <div class="nav-tabs-custom">
      <ul class="nav nav-tabs" role="tablist">
        <li class="active" role="presentation">
          <a href="#categories" aria-controls="categories" role="tab" data-toggle="tab">
            {{ trans('general.categories') }}
          </a>
        </li>
        <li role="presentation">
          <a href="#category-distribution" aria-controls="category-distribution" role="tab" data-toggle="tab">
            {{ trans('admin/categories/general.distribution') }}
          </a>
        </li>
      </ul>

      <div class="tab-content">
        <div role="tabpanel" class="tab-pane fade in active" id="categories">

          <x-tables.bulk-actions
                  id_divname='categoriesBulkEditToolbar'
                  action_route="{{route('categories.bulk.delete')}}"
                  id_formname="categoriesBulkForm"
                  id_button="bulkCategoryEditButton"
                  model_name="category"
          >
              @can('delete', App\Models\Category::class)
                  <option>Delete</option>
              @endcan
          </x-tables.bulk-actions>

          <table
                  data-columns="{{ \App\Presenters\CategoryPresenter::dataTableLayout() }}"
            data-cookie-id-table="categoryTable"
            data-id-table="categoryTable"
            data-side-pagination="server"
            data-sort-order="asc"
            id="categoryTable"
            {{-- begin stuff for bulk dropdown --}}
            data-toolbar="#categoriesBulkEditToolbar"
            data-bulk-button-id="#bulkCategoryEditButton"
            data-bulk-form-id="#categoriesBulkForm"
            {{-- end stuff for bulk dropdown --}}
            data-buttons="categoryButtons"
            class="table table-striped snipe-table"
            data-url="{{ route('api.categories.index', ['manager_id' => request('manager_id')]) }}"
            data-export-options='{
              "fileName": "export-categories-{{ date('Y-m-d') }}",
              "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
              }'>
          </table>
        </div>

        <div role="tabpanel" class="tab-pane fade" id="category-distribution">
          <div class="clearfix" style="margin-bottom: 15px;">
            <div class="pull-left">
              <p class="text-muted" style="margin: 7px 0 0;">
                {{ trans('admin/categories/general.distribution_intro') }}
              </p>
            </div>
            <div class="btn-group pull-right" role="group" aria-label="{{ trans('admin/categories/general.distribution') }}">
              <button type="button" class="btn btn-default active" data-distribution-dimension="site" aria-pressed="true">
                {{ trans('admin/categories/general.by_site') }}
              </button>
              <button type="button" class="btn btn-default" data-distribution-dimension="discipline" aria-pressed="false">
                {{ trans('admin/categories/general.by_discipline') }}
              </button>
            </div>
          </div>

          <div id="category-distribution-summary" class="text-muted" style="margin-bottom: 10px;"></div>
          <div id="category-distribution-alert" class="alert" style="display: none;"></div>

          <div class="table-responsive">
            <table class="table table-striped table-hover" id="category-distribution-table">
              <thead>
                <tr>
                  <th>{{ trans('general.category') }}</th>
                  <th id="category-dimension-heading">{{ trans('general.company') }}</th>
                  <th class="text-right">{{ trans('general.assets') }}</th>
                  <th class="text-right">{{ trans('admin/categories/general.portfolio_percentage') }}</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@stop

@section('moar_scripts')
  @include ('partials.bootstrap-table',
      ['exportFile' => 'category-export',
      'search' => true,
      'columns' => \App\Presenters\CategoryPresenter::dataTableLayout()
  ])

  <script>
    $(function () {
      var distributionUrl = @json(route('api.categories.distribution'));
      var loadedDimensions = {};
      var expandedCategories = {};
      var labels = {
        site: @json(trans('general.company')),
        discipline: @json(trans('general.discipline')),
        managedAssets: @json(trans('admin/categories/general.managed_assets')),
        managedCategories: @json(trans('admin/categories/general.managed_categories')),
        empty: @json(trans('admin/categories/general.distribution_empty')),
        error: @json(trans('admin/categories/general.distribution_error')),
        loading: @json(trans('general.loading'))
      };

      function formatNumber(value) {
        return new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);
      }

      function assetCountLink(count, url) {
        if (!url) {
          return $('<span>').text(formatNumber(count));
        }

        return $('<a>').attr('href', url).text(formatNumber(count));
      }

      function renderDistribution(data) {
        var $body = $('#category-distribution-table tbody').empty();
        var dimension = data.dimension;

        $('#category-dimension-heading').text(labels[dimension]);
        $('#category-distribution-summary').text(
          formatNumber(data.total_assets) + ' ' + labels.managedAssets + ' · ' +
          formatNumber(data.category_count) + ' ' + labels.managedCategories
        );

        if (!data.categories.length) {
          showDistributionAlert('info', labels.empty);
          return;
        }

        hideDistributionAlert();

        $.each(data.categories, function (_, category) {
          var categoryKey = dimension + '-' + category.id;
          var isExpanded = expandedCategories[categoryKey] !== false;
          var $categoryRow = $('<tr>').addClass('distribution-category-row');
          var $toggle = $('<button type="button" class="btn btn-link btn-xs">')
            .attr('aria-expanded', isExpanded ? 'true' : 'false')
            .attr('aria-label', category.name)
            .append($('<i>').addClass(isExpanded ? 'fas fa-minus-square' : 'fas fa-plus-square').attr('aria-hidden', 'true'));

          $categoryRow
            .append($('<th scope="row">').append($toggle).append(document.createTextNode(' ' + category.name)))
            .append($('<td>'))
            .append($('<td class="text-right">').append(assetCountLink(category.asset_count, category.assets_url)))
            .append($('<td class="text-right">').text(category.percentage.toFixed(2) + '%'));
          $body.append($categoryRow);

          var $children = $();
          $.each(category.children, function (_, child) {
            var $childRow = $('<tr>').addClass('distribution-child-row');
            $childRow
              .append($('<td>'))
              .append($('<th scope="row">').css('padding-left', '28px').text(child.name))
              .append($('<td class="text-right">').append(assetCountLink(child.asset_count, child.assets_url)))
              .append($('<td class="text-right">').text(child.percentage.toFixed(2) + '%'));
            $body.append($childRow);
            $children = $children.add($childRow);
          });

          $children.toggle(isExpanded);
          $toggle.on('click', function () {
            var expanded = $(this).attr('aria-expanded') !== 'true';
            expandedCategories[categoryKey] = expanded;
            $(this).attr('aria-expanded', expanded ? 'true' : 'false');
            $(this).find('i')
              .toggleClass('fa-minus-square', expanded)
              .toggleClass('fa-plus-square', !expanded);
            $children.toggle(expanded);
          });
        });
      }

      function showDistributionAlert(type, message) {
        $('#category-distribution-alert')
          .removeClass('alert-info alert-danger')
          .addClass('alert-' + type)
          .text(message)
          .show();
      }

      function hideDistributionAlert() {
        $('#category-distribution-alert').hide();
      }

      function loadDistribution(dimension) {
        if (loadedDimensions[dimension]) {
          renderDistribution(loadedDimensions[dimension]);
          return;
        }

        $('#category-distribution-summary').text(labels.loading);
        $('#category-distribution-table tbody').empty();
        hideDistributionAlert();

        $.ajax({
          url: distributionUrl,
          data: { dimension: dimension },
          dataType: 'json',
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
        })
          .done(function (data) {
            loadedDimensions[dimension] = data;
            renderDistribution(data);
          })
          .fail(function () {
            $('#category-distribution-summary').empty();
            showDistributionAlert('danger', labels.error);
          });
      }

      $('a[href="#category-distribution"]').one('shown.bs.tab', function () {
        loadDistribution('site');
      });

      $('[data-distribution-dimension]').on('click', function () {
        var dimension = $(this).data('distribution-dimension');
        $('[data-distribution-dimension]')
          .removeClass('active')
          .attr('aria-pressed', 'false');
        $(this).addClass('active').attr('aria-pressed', 'true');
        loadDistribution(dimension);
      });
    });
  </script>
@stop
