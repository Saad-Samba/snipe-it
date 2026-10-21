@extends('layouts/default')

{{-- Page title --}}
@section('title')

{{ $category->name }}

@parent
@stop

@push('css')
    <style nonce="{{ csrf_token() }}">
        /* Wide specification tables should scroll sideways, not grow tall as
           every short value wraps into several lines. */
        #assetModelsTable tbody td {
            white-space: nowrap;
        }
        #assetModelsTable tbody tr {
            content-visibility: auto;
            contain-intrinsic-size: auto 48px;
        }
    </style>
@endpush

{{-- Page content --}}
@section('content')

    @if ($category->fieldset)
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <strong>{{ trans('admin/models/general.fieldset') }}:</strong>
                    <a href="{{ route('fieldsets.show', $category->fieldset->id) }}">{{ $category->fieldset->name }}</a>
                </div>
            </div>
        </div>
    @endif



    <div class="row">
        <div class="col-md-12">

            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#items" data-toggle="tab" title="{{ trans('general.items') }}">
                            @if ($category->category_type=='asset')
                                {{ trans('general.assets') }}
                                @if ($category->showableAssets()->count() > 0)
                                    <span class="badge badge-secondary"> {{ $category->showableAssets()->count() }}</span>
                                @endif
                            @elseif ($category->category_type=='accessory')
                                {{ trans('general.accessories') }}
                            @elseif ($category->category_type=='license')
                                {{ trans('general.licenses') }}
                            @elseif ($category->category_type=='consumable')
                                {{ trans('general.consumables') }}
                            @elseif ($category->category_type=='component')
                                {{ trans('general.components') }}
                            @endif

                        </a>
                    </li>
                    @if ($category->category_type=='asset')
                    <li>
                        <a href="#models" data-toggle="tab" title="{{ trans('general.asset_models') }}">
                            {{ trans('general.asset_models') }}
                            @if ($category->models->count() > 0)
                                <span class="badge badge-secondary"> {{ $category->models->count()}}</span>
                            @endif
                        </a>
                    </li>
                   @endif
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade in active" id="items">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    @if ($category->category_type=='asset')
                                        @include('partials.asset-bulk-actions')
                                    @endif

                                    <table

                                            @if ($category->category_type=='asset')
                                            data-columns="{{ \App\Presenters\AssetPresenter::dataTableLayout() }}"
                                            data-show-columns-search="true"
                                            data-cookie-id-table="categoryAssetsTable"
                                            id="categoryAssetsTable"
                                            data-buttons="assetButtons"
                                            data-id-table="categoryAssetsTable"
                                            data-toolbar="#assetsBulkEditToolbar"
                                            data-bulk-button-id="#bulkAssetEditButton"
                                            data-bulk-form-id="#assetsBulkForm"
                                            data-export-options='{
                    "fileName": "export-{{ str_slug($category->name) }}-assets-{{ date('Y-m-d') }}",
                    "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                    }'
                                            @elseif ($category->category_type=='accessory')
                                            data-columns="{{ \App\Presenters\AccessoryPresenter::dataTableLayout() }}"
                                            data-cookie-id-table="categoryAccessoryTable"
                                            id="categoryAccessoryTable"
                                            data-buttons="accessoryButtons"
                                            data-id-table="categoryAccessoryTable"
                                            data-export-options='{
                      "fileName": "export-{{ str_slug($category->name) }}-accessories-{{ date('Y-m-d') }}",
                      "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                      }'
                                            @elseif ($category->category_type=='consumable')
                                                data-columns="{{ \App\Presenters\ConsumablePresenter::dataTableLayout() }}"
                                            data-cookie-id-table="categoryConsumableTable"
                                            id="categoryConsumableTable"
                                            data-buttons="consumableButtons"
                                            data-id-table="categoryConsumableTable"
                                            data-export-options='{
                      "fileName": "export-{{ str_slug($category->name) }}-consumables-{{ date('Y-m-d') }}",
                      "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                      }'
                                            @elseif ($category->category_type=='component')
                                            data-columns="{{ \App\Presenters\ComponentPresenter::dataTableLayout() }}"
                                            data-cookie-id-table="categoryCompomnentTable"
                                            id="categoryCompomnentTable"
                                            data-buttons="componentButtons"
                                            data-id-table="categoryCompomnentTable"
                                            data-export-options='{
                      "fileName": "export-{{ str_slug($category->name) }}-components-{{ date('Y-m-d') }}",
                      "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                      }'
                                            @elseif ($category->category_type=='license')
                                            data-columns="{{ \App\Presenters\LicensePresenter::dataTableLayout() }}"
                                            data-cookie-id-table="categoryLicenseTable"
                                            id="categoryLicenseTable"
                                            data-buttons="licenseButtons"
                                            data-id-table="categoryLicenseTable"
                                            data-export-options='{
                      "fileName": "export-{{ str_slug($category->name) }}-licenses-{{ date('Y-m-d') }}",
                      "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                      }'
                                            @endif
                                            data-show-footer="true"
                                            data-side-pagination="server"
                                            data-sort-order="asc"
                                            class="table table-striped snipe-table"
                                            data-url="{{ route('api.'.$category_type_route.'.index',['category_id'=> $category->id]) }}">

                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="models">
                        <div class="row">
                            <div class="col-md-12">

                                <p class="text-muted">This table includes one column for each technical specification configured for the category.</p>
                                <div class="clearfix" style="margin-bottom:8px;">
                                    <span class="pull-right text-muted">Type a technical field to add a filter.</span>
                                </div>
                                <div id="assetModelSpecificationChips" class="form-inline" style="margin-bottom:8px;" aria-live="polite"></div>

                                @php
                                    $specificationFields = $category->fieldset
                                        ? $category->fieldset->fields
                                            ->filter(fn ($field) => ! $field->is_unique && ! $field->field_encrypted)
                                            ->values()
                                        : collect();
                                @endphp

                                @can('update', \App\Models\AssetModel::class)
                                @if ($category->models->count() > 0)
                                    @if ($category->category_type=='asset')
                                        @include('partials.models-bulk-actions')
                                    @endif
                                @endif
                                @endcan

                                    <table
                                            data-columns="{{ \App\Presenters\AssetModelPresenter::dataTableLayout($specificationFields) }}"
                                            data-cookie-id-table="assetModelsTable"
                                            data-id-table="assetModelsTable"
                                            data-extra-query-params="categoryModelSpecificationQueryParams"
                                            data-show-footer="true"
                                            data-side-pagination="server"
                                            data-toolbar="#modelsBulkEditToolbar"
                                            data-bulk-button-id="#bulkModelsEditButton"
                                            data-bulk-form-id="#modelsBulkForm"
                                            data-sort-order="asc"
                                            id="assetModelsTable"
                                            data-buttons="modelButtons"
                                            class="table table-striped snipe-table"
                                            data-url="{{ route('api.models.index', ['status' => request('status'), 'category_id' => $category->id]) }}"
                                            data-export-options='{
              "fileName": "export-models-{{ date('Y-m-d') }}",
              "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
              }'>
                                    </table>

                            </div>
                        </div>
                    </div>

                </div> <!-- .tab-content-->
            </div> <!-- .nav-tabs-custom -->
        </div> <!-- .col-md-12> -->
    </div> <!-- .row -->
@stop





@section('moar_scripts')
@include ('partials.bootstrap-table')

<script nonce="{{ csrf_token() }}">
    var categoryModelSpecificationFields = @json($specificationSearchFields);
    var categoryModelSpecificationTokens = [];
    var categoryModelFreeTextSearch = '';

    window.categoryModelSpecificationQueryParams = function (params) {
        if (categoryModelFreeTextSearch) {
            params.search = categoryModelFreeTextSearch;
        }

        if (categoryModelSpecificationTokens.length) {
            params.specification_filters = JSON.stringify(categoryModelSpecificationTokens.map(function (token) {
                return {
                    field_id: token.field.id,
                    operator: token.operator,
                    value: token.value
                };
            }));
        }

        return params;
    };

    $(function () {
        var $table = $('#assetModelsTable');
        var $wrapper = $table.closest('.bootstrap-table');
        var $input = $wrapper.find('.fixed-table-toolbar .search input');
        var $chips = $('#assetModelSpecificationChips');
        var $menu = $('<div class="dropdown-menu" id="categoryModelSpecificationSuggestions"></div>').css({
            maxHeight: '240px',
            overflowY: 'auto',
            zIndex: 1050
        });
        var hideSuggestionsTimer;
        var wasTypingSpecificationExpression = false;
        var isResettingSearch = false;

        if (!$table.length || !$input.length) {
            return;
        }

        $input.attr('placeholder', 'Search models or add a technical field filter');
        $input.parent().css('position', 'relative').append($menu);
        $menu.on('mousedown', function (event) {
            clearTimeout(hideSuggestionsTimer);
            event.preventDefault();
        });

        function fieldFor(value) {
            var lowerValue = value.toLowerCase();

            return categoryModelSpecificationFields
                .filter(function (field) {
                    var remainder = lowerValue.slice(field.name.length);

                    return lowerValue.indexOf(field.name.toLowerCase()) === 0
                        && /^(?:\s|$|:|=|>|<)/.test(remainder);
                })
                .sort(function (first, second) {
                    return second.name.length - first.name.length;
                })[0];
        }

        function isSpecificationExpression(value) {
            var field = fieldFor(value);

            return !!field && (/^\s*$/.test(value.slice(field.name.length))
                || /^(>=|<=|=|:)/.test($.trim(value.slice(field.name.length))));
        }

        function isPotentialSpecificationField(value) {
            return !!value && categoryModelSpecificationFields.some(function (field) {
                return field.name.toLowerCase().indexOf(value.toLowerCase()) === 0;
            });
        }

        function clearFreeTextSearch() {
            isResettingSearch = true;
            $table.bootstrapTable('resetSearch', '');
            isResettingSearch = false;
        }

        function refreshResults() {
            $table.bootstrapTable('refresh', { pageNumber: 1 });
        }

        function renderChips() {
            $chips.empty();

            if (categoryModelSpecificationTokens.length || categoryModelFreeTextSearch) {
                $('<span class="text-primary" style="margin-right:8px; font-weight:600"></span>')
                    .text('Active filters:')
                    .appendTo($chips);
            }

            categoryModelSpecificationTokens.forEach(function (token, index) {
                var operatorLabel = token.operator === ':'
                    ? (token.field.multiple ? 'includes' : (token.field.enumerated ? 'is' : 'contains'))
                    : token.operator;
                var displayValue = token.field.enumerated
                    ? ((token.field.values.find(function (option) { return option.value === token.value; }) || {}).label || token.value)
                    : token.value;
                var $chip = $('<button type="button" class="btn btn-primary btn-sm" style="margin-right:6px; margin-bottom:6px; border-radius:16px"></button>')
                    .text(token.field.name + ' ' + operatorLabel + ' ' + displayValue + ' ×')
                    .on('click', function () {
                        categoryModelSpecificationTokens.splice(index, 1);
                        renderChips();
                        refreshResults();
                    });

                $chips.append($chip);
            });

            if (categoryModelFreeTextSearch) {
                $('<button type="button" class="btn btn-primary btn-sm" style="margin-right:6px; margin-bottom:6px; border-radius:16px"></button>')
                    .text('Text contains ' + categoryModelFreeTextSearch + ' ×')
                    .on('click', function () {
                        categoryModelFreeTextSearch = '';
                        clearFreeTextSearch();
                        renderChips();
                        refreshResults();
                    })
                    .appendTo($chips);
            }
        }

        function addToken(field, operator, value) {
            value = $.trim(value);

            if (!value || (field.numeric && !isFinite(Number(value)))) {
                return;
            }

            categoryModelSpecificationTokens.push({
                field: field,
                operator: operator,
                value: value
            });
            clearFreeTextSearch();
            $input.val('');
            $menu.hide();
            renderChips();
            refreshResults();
        }

        function showSuggestions(items) {
            clearTimeout(hideSuggestionsTimer);
            $menu.empty();

            items.forEach(function (item) {
                $('<button type="button" class="dropdown-item btn btn-link btn-block text-left"></button>')
                    .text(item.label)
                    .on('click', item.action)
                    .appendTo($menu);
            });

            $menu.toggle(items.length > 0);
        }

        function suggest() {
            var value = $.trim($input.val());
            var field = fieldFor(value);

            if (!field) {
                var matches = categoryModelSpecificationFields.filter(function (candidate) {
                    return candidate.name.toLowerCase().indexOf(value.toLowerCase()) !== -1;
                });

                showSuggestions(matches.slice(0, 8).map(function (candidate) {
                    return {
                        label: candidate.name,
                        action: function () {
                            clearFreeTextSearch();
                            $input.val(candidate.name + (candidate.numeric ? ' ' : ': ')).focus();
                            suggest();
                        }
                    };
                }));

                return;
            }

            var remainder = $.trim(value.slice(field.name.length));
            if (!remainder && field.numeric) {
                showSuggestions(['>=', '<=', '='].map(function (operator) {
                    return {
                        label: operator,
                        action: function () {
                            clearFreeTextSearch();
                            $input.val(field.name + ' ' + operator + ' ').focus();
                            suggest();
                        }
                    };
                }));

                return;
            }

            if (!remainder) {
                showSuggestions([{
                    label: field.name + ': value',
                    action: function () {
                        clearFreeTextSearch();
                        $input.val(field.name + ': ').focus();
                        suggest();
                    }
                }]);

                return;
            }

            var operator = (remainder.match(/^(>=|<=|=|:)/) || [])[1];
            if (field.numeric && !operator) {
                showSuggestions(['>=', '<=', '='].map(function (choice) {
                    return {
                        label: choice,
                        action: function () {
                            clearFreeTextSearch();
                            $input.val(field.name + ' ' + choice + ' ').focus();
                            suggest();
                        }
                    };
                }));

                return;
            }

            var typedValue = $.trim(remainder.replace(/^(>=|<=|=|:)/, ''));
            if (field.values.length) {
                showSuggestions(field.values
                    .filter(function (candidate) {
                        return candidate.label.toLowerCase().indexOf(typedValue.toLowerCase()) !== -1;
                    })
                    .slice(0, 8)
                    .map(function (candidate) {
                        return {
                            label: candidate.label,
                            action: function () {
                                addToken(field, operator || ':', candidate.value);
                            }
                        };
                    }));

                return;
            }

            $menu.hide();
        }

        function commitSearch() {
            var value = $.trim($input.val());
            var field = fieldFor(value);

            if (!field) {
                return false;
            }

            var remainder = $.trim(value.slice(field.name.length));
            var match = remainder.match(/^(>=|<=|=|:)\s*(.+)$/);
            if (!match) {
                return false;
            }

            addToken(field, match[1], match[2]);

            return true;
        }

        $input[0].addEventListener('input', function (event) {
            var value = $.trim($input.val());
            if (isSpecificationExpression(value) || isPotentialSpecificationField(value)) {
                event.stopImmediatePropagation();
                if (!wasTypingSpecificationExpression) {
                    clearFreeTextSearch();
                    $input.val(value);
                }
                wasTypingSpecificationExpression = true;
                suggest();
            } else {
                wasTypingSpecificationExpression = false;
                if (!isResettingSearch) {
                    categoryModelFreeTextSearch = value;
                    renderChips();
                }
            }
        }, true);

        categoryModelFreeTextSearch = $table.bootstrapTable('getOptions').searchText || '';
        renderChips();

        $wrapper.find('.search')[0].addEventListener('click', function (event) {
            if (event.target.closest('button[name="clearSearch"]')) {
                categoryModelFreeTextSearch = '';
                wasTypingSpecificationExpression = false;
                renderChips();
            }
        }, true);

        $input[0].addEventListener('keyup', function (event) {
            if (isSpecificationExpression($.trim($input.val())) || isPotentialSpecificationField($.trim($input.val()))) {
                event.stopImmediatePropagation();
            }
        }, true);

        $input
            .on('input', suggest)
            .on('keydown', function (event) {
                if (event.key === 'Enter') {
                    if (commitSearch()) {
                        event.preventDefault();
                    } else if (isPotentialSpecificationField($.trim($input.val()))) {
                        categoryModelFreeTextSearch = $.trim($input.val());
                        $table.bootstrapTable('resetSearch', categoryModelFreeTextSearch);
                        renderChips();
                        $menu.hide();
                        event.preventDefault();
                    }
                }

                if (event.key === 'Escape') {
                    $menu.hide();
                }
            })
            .on('blur', function () {
                hideSuggestionsTimer = setTimeout(function () {
                    $menu.hide();
                }, 150);
            });
    });

</script>
@stop
