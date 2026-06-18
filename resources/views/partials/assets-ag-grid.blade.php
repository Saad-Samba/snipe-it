<link rel="stylesheet" href="{{ url('css/dist/ag-grid.css') }}">
<link rel="stylesheet" href="{{ url('css/dist/ag-theme-alpine.css') }}">

@php
    $assetIndexGridConfig = [
        'apiUrl' => $apiUrl,
        'apiToken' => $apiToken ?? null,
        'baseUrl' => url('/hardware'),
        'modelsUrl' => url('/models'),
        'categoriesUrl' => url('/categories'),
        'companiesUrl' => url('/companies'),
        'locationsUrl' => url('/locations'),
        'usersUrl' => url('/users'),
        'initialPage' => max(1, (int) request()->input('page', 1)),
        'initialSize' => max(1, (int) request()->input('size', 20)),
        'initialSearch' => (string) request()->input('search', session()->get('search', '')),
        'initialSort' => (string) request()->input('sort', 'name'),
        'initialOrder' => request()->input('order') === 'desc' ? 'desc' : 'asc',
        'query' => [
            'status' => request()->input('status'),
            'assignment' => request()->input('assignment'),
            'model_obsolete' => request()->input('model_obsolete'),
            'order_number' => request()->input('order_number'),
            'company_id' => request()->input('company_id'),
            'status_id' => request()->input('status_id'),
        ],
        'labels' => [
            'searchPlaceholder' => trans('general.search'),
            'rowsPerPage' => 'rows per page',
            'loading' => 'Loading...',
            'showing' => 'Showing',
            'to' => 'to',
            'of' => 'of',
            'rows' => 'rows',
            'previous' => trans('pagination.previous'),
            'next' => trans('pagination.next'),
            'refresh' => 'Refresh',
            'clear' => 'Clear',
            'filtersHint' => 'Set filters use the current loaded page values.',
            'empty' => 'No results',
            'image' => trans('admin/hardware/table.image'),
            'assetName' => trans('admin/hardware/form.name'),
            'assetTag' => trans('admin/hardware/table.asset_tag'),
            'serial' => trans('admin/hardware/form.serial'),
            'company' => trans('general.company'),
            'model' => trans('admin/hardware/form.model'),
            'category' => trans('general.category'),
            'status' => trans('admin/hardware/table.status'),
            'checkedOutTo' => trans('admin/hardware/form.checkedout_to'),
            'owner' => trans('general.owner'),
            'location' => trans('admin/hardware/table.location'),
            'purchaseCost' => trans('general.purchase_cost'),
        ],
    ];
@endphp

<style>
  .asset-grid-shell {
    border: 1px solid #d2d6de;
    border-radius: 4px;
    background: #fff;
    margin-top: 18px;
  }

  .asset-grid-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px 10px;
    border-bottom: 1px solid #edf0f5;
  }

  .asset-grid-toolbar__group {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
  }

  .asset-grid-toolbar__search {
    min-width: 280px;
  }

  .asset-grid-toolbar__hint {
    color: #6b7280;
    font-size: 12px;
    margin: 0;
  }

  .asset-grid-filterbar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    padding: 0 18px 16px;
  }

  .asset-grid-filterbar label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    color: #6b7280;
  }

  .asset-grid-filterbar .select2-container {
    width: 100% !important;
  }

  .asset-grid-filterbar .select2-selection--multiple {
    min-height: 42px;
    border-color: #d2d6de;
  }

  .asset-grid-filterbar select {
    display: none;
  }

  .asset-grid-filter-control {
    position: relative;
  }

  .asset-grid-filter-control__trigger {
    align-items: center;
    background: #fff;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    color: #374151;
    display: flex;
    font-size: 14px;
    gap: 8px;
    height: 40px;
    justify-content: space-between;
    overflow: hidden;
    padding: 0 12px;
    text-align: left;
    width: 100%;
  }

  .asset-grid-filter-control__trigger.is-empty,
  .asset-grid-filter-control.is-disabled .asset-grid-filter-control__trigger {
    background: #f7f8fa;
    color: #9aa3af;
  }

  .asset-grid-filter-control__label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .asset-grid-filter-control__caret {
    color: #9aa3af;
    flex: 0 0 auto;
    font-size: 11px;
  }

  .asset-grid-filter-control__menu {
    background: #fff;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
    display: none;
    left: 0;
    margin-top: 6px;
    max-height: 240px;
    overflow: auto;
    padding: 8px;
    position: absolute;
    right: 0;
    z-index: 20;
  }

  .asset-grid-filter-control.is-open .asset-grid-filter-control__menu {
    display: block;
  }

  .asset-grid-filter-control__option {
    align-items: center;
    border-radius: 3px;
    cursor: pointer;
    display: flex;
    font-size: 13px;
    gap: 8px;
    margin: 0;
    padding: 6px 8px;
  }

  .asset-grid-filter-control__option:hover {
    background: #f4f7fb;
  }

  .asset-grid-filter-control__option input {
    margin: 0;
  }

  .asset-grid-filter-control__empty {
    color: #9aa3af;
    font-size: 13px;
    margin: 0;
    padding: 6px 8px;
  }

  .asset-grid-statusbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 0 18px 14px;
  }

  .asset-grid-statusbar__summary {
    font-size: 16px;
  }

  .asset-grid-statusbar__pager {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
  }

  .asset-grid-statusbar__pages {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .asset-grid-statusbar__pages .btn.active {
    background: #3c8dbc;
    border-color: #367fa9;
    color: #fff;
  }

  .asset-grid-frame {
    padding: 0 18px 18px;
  }

  .asset-grid-table.ag-theme-alpine {
    --ag-font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    --ag-font-size: 14px;
    --ag-row-height: 64px;
    --ag-header-height: 44px;
    --ag-header-background-color: #ffffff;
    --ag-odd-row-background-color: #fcfcfd;
    --ag-border-color: #e8ecf3;
    --ag-alpine-active-color: #3c8dbc;
    --ag-selected-row-background-color: rgba(60, 141, 188, 0.12);
  }

  .asset-grid-table.ag-theme-alpine .ag-root-wrapper {
    border: 1px solid #e8ecf3;
    border-radius: 4px;
  }

  .asset-grid-table.ag-theme-alpine .ag-root-wrapper-body,
  .asset-grid-table.ag-theme-alpine .ag-body-viewport {
    min-height: 0;
  }

  .asset-grid-table.ag-theme-alpine .ag-header-cell,
  .asset-grid-table.ag-theme-alpine .ag-header-group-cell {
    font-size: 13px;
    font-weight: 600;
  }

  .asset-grid-table.ag-theme-alpine .ag-floating-filter-input {
    --ag-input-focus-border-color: #3c8dbc;
  }

  .asset-grid-table.ag-theme-alpine .ag-floating-filter-body input {
    height: 32px;
  }

  .asset-grid-link {
    color: #2b6a94;
    font-weight: 500;
    text-decoration: none;
  }

  .asset-grid-link:hover,
  .asset-grid-link:focus {
    color: #1d4f70;
    text-decoration: underline;
  }

  .asset-grid-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .asset-grid-chip__swatch {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    flex: 0 0 16px;
  }

  .asset-grid-image {
    width: 64px;
    max-height: 44px;
    object-fit: contain;
  }

  .asset-grid-empty {
    padding: 24px;
    text-align: center;
    color: #6b7280;
  }
</style>

<div class="asset-grid-shell">
  <div class="asset-grid-toolbar">
    <div class="asset-grid-toolbar__group">
      <input type="search" class="form-control asset-grid-toolbar__search" id="asset-grid-search" placeholder="{{ trans('general.search') }}" value="{{ request()->input('search', session()->get('search', '')) }}">
      <button type="button" class="btn btn-default" id="asset-grid-clear-search">Clear</button>
      <button type="button" class="btn btn-default" id="asset-grid-refresh">Refresh</button>
    </div>
    <p class="asset-grid-toolbar__hint">Current-page multi-select filters are enabled for the categorical columns below.</p>
  </div>

  <div class="asset-grid-filterbar">
    <div>
      <label for="asset-grid-company-filter">{{ trans('general.company') }}</label>
      <select id="asset-grid-company-filter" class="form-control asset-grid-page-filter" multiple></select>
    </div>
    <div>
      <label for="asset-grid-model-filter">{{ trans('admin/hardware/form.model') }}</label>
      <select id="asset-grid-model-filter" class="form-control asset-grid-page-filter" multiple></select>
    </div>
    <div>
      <label for="asset-grid-category-filter">{{ trans('general.category') }}</label>
      <select id="asset-grid-category-filter" class="form-control asset-grid-page-filter" multiple></select>
    </div>
    <div>
      <label for="asset-grid-status-filter">{{ trans('admin/hardware/table.status') }}</label>
      <select id="asset-grid-status-filter" class="form-control asset-grid-page-filter" multiple></select>
    </div>
    <div>
      <label for="asset-grid-assigned-filter">{{ trans('admin/hardware/form.checkedout_to') }}</label>
      <select id="asset-grid-assigned-filter" class="form-control asset-grid-page-filter" multiple></select>
    </div>
    <div>
      <label for="asset-grid-owner-filter">{{ trans('general.owner') }}</label>
      <select id="asset-grid-owner-filter" class="form-control asset-grid-page-filter" multiple></select>
    </div>
    <div>
      <label for="asset-grid-location-filter">{{ trans('admin/hardware/table.location') }}</label>
      <select id="asset-grid-location-filter" class="form-control asset-grid-page-filter" multiple></select>
    </div>
  </div>

  <div class="asset-grid-statusbar">
    <div class="asset-grid-statusbar__summary" id="asset-grid-summary">{{ trans('general.loading') }}</div>
    <div class="asset-grid-statusbar__pager">
      <label class="sr-only" for="asset-grid-page-size">rows per page</label>
      <select class="form-control" id="asset-grid-page-size">
        @foreach ([20, 50, 100] as $pageSize)
          <option value="{{ $pageSize }}" @selected((int) request()->input('size', 20) === $pageSize)>{{ $pageSize }}</option>
        @endforeach
      </select>
      <span>rows per page</span>
      <button type="button" class="btn btn-default" id="asset-grid-prev">{{ trans('pagination.previous') }}</button>
      <div class="asset-grid-statusbar__pages" id="asset-grid-pages"></div>
      <button type="button" class="btn btn-default" id="asset-grid-next">{{ trans('pagination.next') }}</button>
    </div>
  </div>

  <div class="asset-grid-frame">
    <div id="assetsAgGrid" class="ag-theme-alpine asset-grid-table"></div>
  </div>
</div>

<script>
  window.assetsAgGridConfig = @json($assetIndexGridConfig);
</script>
<script src="{{ url('js/dist/ag-grid-community.min.js') }}"></script>
<script>
  (function () {
    if (!window.agGrid || !window.assetsAgGridConfig) {
      return;
    }

    var config = window.assetsAgGridConfig;
    var state = {
      page: Number(config.initialPage) || 1,
      size: Number(config.initialSize) || 20,
      search: config.initialSearch || '',
      sort: config.initialSort || 'name',
      order: config.initialOrder || 'asc',
      total: 0,
      loading: false,
      rows: [],
      debounceTimer: null,
      pageFilters: {
        company: [],
        model: [],
        category: [],
        status_label: [],
        assigned_to: [],
        owner: [],
        location: [],
      },
    };

    var gridElement = document.getElementById('assetsAgGrid');
    var summaryElement = document.getElementById('asset-grid-summary');
    var searchInput = document.getElementById('asset-grid-search');
    var clearSearchButton = document.getElementById('asset-grid-clear-search');
    var refreshButton = document.getElementById('asset-grid-refresh');
    var pageSizeSelect = document.getElementById('asset-grid-page-size');
    var prevButton = document.getElementById('asset-grid-prev');
    var nextButton = document.getElementById('asset-grid-next');
    var pagesElement = document.getElementById('asset-grid-pages');
    var bulkForm = document.getElementById('assetsBulkForm');
    var bulkButton = document.getElementById('bulkAssetEditButton');
    var pageFilterSelects = {
      company: document.getElementById('asset-grid-company-filter'),
      model: document.getElementById('asset-grid-model-filter'),
      category: document.getElementById('asset-grid-category-filter'),
      status_label: document.getElementById('asset-grid-status-filter'),
      assigned_to: document.getElementById('asset-grid-assigned-filter'),
      owner: document.getElementById('asset-grid-owner-filter'),
      location: document.getElementById('asset-grid-location-filter'),
    };
    var pageFilterOrder = ['company', 'model', 'category', 'status_label', 'assigned_to', 'owner', 'location'];
    var pageFilterControls = {};

    function decodeHtml(value) {
      if (!value) {
        return '';
      }
      var textarea = document.createElement('textarea');
      textarea.innerHTML = String(value);
      return textarea.value;
    }

    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function objectName(value) {
      return value && value.name ? decodeHtml(value.name) : '';
    }

    function buildLink(baseUrl, value, fallbackText) {
      var text = fallbackText || objectName(value);
      if (!text) {
        return '';
      }
      if (!value || !value.id) {
        return '<span>' + escapeHtml(text) + '</span>';
      }
      return '<a class="asset-grid-link" href="' + baseUrl + '/' + value.id + '">' + escapeHtml(text) + '</a>';
    }

    function colorChip(value, baseUrl, fallbackText) {
      var text = fallbackText || objectName(value);
      if (!text) {
        return '';
      }
      var swatch = value && value.tag_color
        ? '<span class="asset-grid-chip__swatch" style="background:' + escapeHtml(value.tag_color) + '"></span>'
        : '';
      return '<span class="asset-grid-chip">' + swatch + buildLink(baseUrl, value, text) + '</span>';
    }

    function assignedToLabel(value) {
      if (!value) {
        return '';
      }
      return decodeHtml(value.name || value.username || value.asset_tag || '');
    }

    function extractFilterLabel(field, row) {
      if (!row) {
        return '';
      }
      if (field === 'assigned_to') {
        return assignedToLabel(row.assigned_to);
      }
      if (field === 'model' || field === 'category' || field === 'status_label' || field === 'company' || field === 'owner' || field === 'location') {
        return objectName(row[field]);
      }
      return '';
    }

    function syncSelectValue(select, values) {
      if (!select) {
        return;
      }
      Array.prototype.slice.call(select.options).forEach(function (option) {
        option.selected = values.indexOf(option.value) !== -1;
      });
    }

    function filterSummary(field, options) {
      var selected = state.pageFilters[field] || [];
      if (!options.length) {
        return 'No values';
      }
      if (!selected.length) {
        return 'All values';
      }
      if (selected.length === 1) {
        return selected[0];
      }
      return selected.length + ' selected';
    }

    function closePageFilterMenus(exceptField) {
      pageFilterOrder.forEach(function (field) {
        if (field !== exceptField && pageFilterControls[field]) {
          pageFilterControls[field].wrapper.classList.remove('is-open');
        }
      });
    }

    function ensurePageFilterControl(field) {
      if (pageFilterControls[field]) {
        return pageFilterControls[field];
      }

      var select = pageFilterSelects[field];
      var wrapper = document.createElement('div');
      wrapper.className = 'asset-grid-filter-control';

      var trigger = document.createElement('button');
      trigger.type = 'button';
      trigger.className = 'asset-grid-filter-control__trigger';
      trigger.innerHTML = '<span class="asset-grid-filter-control__label"></span><span class="asset-grid-filter-control__caret">&#9662;</span>';

      var menu = document.createElement('div');
      menu.className = 'asset-grid-filter-control__menu';

      var empty = document.createElement('p');
      empty.className = 'asset-grid-filter-control__empty';
      empty.textContent = 'No values on this page';
      menu.appendChild(empty);

      var list = document.createElement('div');
      menu.appendChild(list);

      trigger.addEventListener('click', function (event) {
        event.preventDefault();
        if (wrapper.classList.contains('is-disabled')) {
          return;
        }
        var willOpen = !wrapper.classList.contains('is-open');
        closePageFilterMenus(field);
        wrapper.classList.toggle('is-open', willOpen);
      });

      list.addEventListener('change', function (event) {
        if (!event.target || event.target.type !== 'checkbox') {
          return;
        }
        state.pageFilters[field] = Array.prototype.slice.call(list.querySelectorAll('input:checked')).map(function (input) {
          return input.value;
        });
        updatePageFilterControl(field, currentPageFilterOptions(field));
        gridApi.onFilterChanged();
        updateBulkInputs();
      });

      select.parentNode.appendChild(wrapper);
      wrapper.appendChild(trigger);
      wrapper.appendChild(menu);

      pageFilterControls[field] = {
        wrapper: wrapper,
        trigger: trigger,
        label: trigger.querySelector('.asset-grid-filter-control__label'),
        menu: menu,
        empty: empty,
        list: list,
      };

      return pageFilterControls[field];
    }

    function currentPageFilterOptions(field) {
      return Array.from(new Set(state.rows.map(function (row) {
        return extractFilterLabel(field, row);
      }).filter(Boolean))).sort(function (left, right) {
        return left.localeCompare(right);
      });
    }

    function updatePageFilterControl(field, options) {
      var control = ensurePageFilterControl(field);
      var select = pageFilterSelects[field];
      var retained = state.pageFilters[field].filter(function (value) {
        return options.indexOf(value) !== -1;
      });

      state.pageFilters[field] = retained;
      syncSelectValue(select, retained);
      control.wrapper.classList.toggle('is-disabled', options.length === 0);
      control.trigger.classList.toggle('is-empty', options.length === 0);
      control.empty.style.display = options.length ? 'none' : 'block';
      control.list.innerHTML = options.map(function (value) {
        var checked = retained.indexOf(value) !== -1 ? ' checked' : '';
        return '<label class="asset-grid-filter-control__option"><input type="checkbox" value="' + escapeHtml(value) + '"' + checked + '><span>' + escapeHtml(value) + '</span></label>';
      }).join('');
      control.label.textContent = filterSummary(field, options);
      select.disabled = options.length === 0;
    }

    function populatePageFilters(rows) {
      pageFilterOrder.forEach(function (field) {
        var select = pageFilterSelects[field];
        if (!select) {
          return;
        }
        var options = Array.from(new Set(rows.map(function (row) {
          return extractFilterLabel(field, row);
        }).filter(Boolean))).sort(function (left, right) {
          return left.localeCompare(right);
        });
        select.innerHTML = options.map(function (value) {
          return '<option value="' + escapeHtml(value) + '">' + escapeHtml(value) + '</option>';
        }).join('');
        updatePageFilterControl(field, options);
      });
    }

    function initPageFilters() {
      pageFilterOrder.forEach(function (field) {
        if (!pageFilterSelects[field]) {
          return;
        }
        ensurePageFilterControl(field);
        updatePageFilterControl(field, []);
      });

      document.addEventListener('click', function (event) {
        pageFilterOrder.forEach(function (field) {
          var control = pageFilterControls[field];
          if (control && !control.wrapper.contains(event.target)) {
            control.wrapper.classList.remove('is-open');
          }
        });
      });
    }

    function assignedToLink(value) {
      if (!value) {
        return '';
      }
      var text = assignedToLabel(value);
      if (!text) {
        return '';
      }
      var baseUrl = config.usersUrl;
      if (value.type === 'location') {
        baseUrl = config.locationsUrl;
      } else if (value.type === 'asset') {
        baseUrl = config.baseUrl;
      }
      return buildLink(baseUrl, value, text);
    }

    function updateBulkInputs() {
      if (!bulkForm) {
        return;
      }
      Array.prototype.slice.call(bulkForm.querySelectorAll('input[data-ag-grid-selection="true"]')).forEach(function (input) {
        input.remove();
      });
      var selectedIds = gridApi ? gridApi.getSelectedRows().map(function (row) { return row.id; }) : [];
      selectedIds.forEach(function (id) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        input.setAttribute('data-ag-grid-selection', 'true');
        bulkForm.appendChild(input);
      });
      if (bulkButton) {
        bulkButton.disabled = selectedIds.length === 0;
      }
    }

    function currentPageStart() {
      if (state.total === 0) {
        return 0;
      }
      return ((state.page - 1) * state.size) + 1;
    }

    function currentPageEnd() {
      return Math.min(state.page * state.size, state.total);
    }

    function totalPages() {
      return Math.max(1, Math.ceil(state.total / state.size));
    }

    function updateSummary() {
      if (state.loading) {
        summaryElement.textContent = config.labels.loading;
        return;
      }
      if (!state.total) {
        summaryElement.textContent = config.labels.empty;
        return;
      }
      summaryElement.textContent = config.labels.showing + ' ' + currentPageStart() + ' ' + config.labels.to + ' ' + currentPageEnd() + ' ' + config.labels.of + ' ' + state.total + ' ' + config.labels.rows;
    }

    function renderPager() {
      var pageCount = totalPages();
      pagesElement.innerHTML = '';
      var start = Math.max(1, state.page - 2);
      var end = Math.min(pageCount, state.page + 2);

      if (start > 1) {
        pagesElement.appendChild(buildPageButton(1));
        if (start > 2) {
          pagesElement.appendChild(buildEllipsis());
        }
      }

      for (var page = start; page <= end; page += 1) {
        pagesElement.appendChild(buildPageButton(page));
      }

      if (end < pageCount) {
        if (end < pageCount - 1) {
          pagesElement.appendChild(buildEllipsis());
        }
        pagesElement.appendChild(buildPageButton(pageCount));
      }

      prevButton.disabled = state.page <= 1 || state.loading;
      nextButton.disabled = state.page >= pageCount || state.loading;
    }

    function buildPageButton(page) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-default' + (page === state.page ? ' active' : '');
      button.textContent = page;
      button.disabled = state.loading || page === state.page;
      button.addEventListener('click', function () {
        state.page = page;
        loadPage();
      });
      return button;
    }

    function buildEllipsis() {
      var span = document.createElement('span');
      span.textContent = '...';
      return span;
    }

    function updateUrl() {
      var params = new URLSearchParams(window.location.search);
      params.set('page', String(state.page));
      params.set('size', String(state.size));
      params.set('sort', state.sort);
      params.set('order', state.order);
      params.set('search', state.search);
      window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
    }

    function apiUrl() {
      var url = new URL(config.apiUrl, window.location.origin);
      url.searchParams.set('offset', String((state.page - 1) * state.size));
      url.searchParams.set('limit', String(state.size));
      url.searchParams.set('sort', state.sort);
      url.searchParams.set('order', state.order);
      url.searchParams.set('search', state.search);
      Object.keys(config.query || {}).forEach(function (key) {
        var value = config.query[key];
        if (value !== null && value !== undefined && value !== '') {
          url.searchParams.set(key, value);
        }
      });
      return url.toString();
    }

    function syncSortToServer() {
      if (!gridApi) {
        return;
      }
      var sortModel = gridApi.getColumnState().filter(function (column) {
        return !!column.sort;
      }).sort(function (a, b) {
        return (a.sortIndex || 0) - (b.sortIndex || 0);
      });
      if (!sortModel.length) {
        return;
      }
      var nextSort = sortModel[0].colId;
      if (nextSort === 'checkbox' || nextSort === 'image') {
        return;
      }
      state.sort = nextSort;
      state.order = sortModel[0].sort === 'desc' ? 'desc' : 'asc';
      state.page = 1;
      loadPage();
    }

    function setLoadingOverlay() {
      updateSummary();
      if (gridApi) {
        gridApi.setGridOption('loading', true);
      }
    }

    function setEmptyOverlay() {
      if (gridApi) {
        gridApi.setGridOption('loading', false);
        gridApi.showNoRowsOverlay();
      }
    }

    function currencyComparator(valueA, valueB) {
      var numberA = Number(String(valueA || '').replace(/[^0-9.-]+/g, '')) || 0;
      var numberB = Number(String(valueB || '').replace(/[^0-9.-]+/g, '')) || 0;
      return numberA - numberB;
    }

    var columnDefs = [
      {
        colId: 'checkbox',
        width: 58,
        pinned: 'left',
        sortable: false,
        filter: false,
        lockPosition: true,
      },
      {
        field: 'company',
        headerName: config.labels.company,
        minWidth: 180,
        valueGetter: function (params) { return objectName(params.data.company); },
        cellRenderer: function (params) { return colorChip(params.data.company, config.companiesUrl); },
        filter: false,
      },
      {
        field: 'image',
        headerName: config.labels.image,
        width: 120,
        sortable: false,
        filter: false,
        cellRenderer: function (params) {
          if (!params.value) {
            return '';
          }
          return '<img class="asset-grid-image" alt="" src="' + escapeHtml(params.value) + '">';
        },
      },
      {
        field: 'name',
        headerName: config.labels.assetName,
        minWidth: 220,
        filter: 'agTextColumnFilter',
        cellRenderer: function (params) {
          var text = decodeHtml(params.value || params.data.asset_tag || '');
          return '<a class="asset-grid-link" href="' + config.baseUrl + '/' + params.data.id + '">' + escapeHtml(text) + '</a>';
        },
      },
      {
        field: 'asset_tag',
        headerName: config.labels.assetTag,
        minWidth: 150,
        filter: 'agTextColumnFilter',
        cellRenderer: function (params) {
          return '<a class="asset-grid-link" href="' + config.baseUrl + '/' + params.data.id + '">' + escapeHtml(params.value || '') + '</a>';
        },
      },
      {
        field: 'serial',
        headerName: config.labels.serial,
        minWidth: 180,
        filter: 'agTextColumnFilter',
      },
      {
        field: 'model',
        headerName: config.labels.model,
        minWidth: 200,
        valueGetter: function (params) { return objectName(params.data.model); },
        cellRenderer: function (params) { return buildLink(config.modelsUrl, params.data.model); },
        filter: false,
      },
      {
        field: 'category',
        headerName: config.labels.category,
        minWidth: 170,
        valueGetter: function (params) { return objectName(params.data.category); },
        cellRenderer: function (params) { return colorChip(params.data.category, config.categoriesUrl); },
        filter: false,
      },
      {
        field: 'status_label',
        headerName: config.labels.status,
        minWidth: 190,
        valueGetter: function (params) { return objectName(params.data.status_label); },
        cellRenderer: function (params) { return '<span class="asset-grid-chip">' + buildLink('#', null, objectName(params.data.status_label)) + '</span>'; },
        filter: false,
      },
      {
        field: 'assigned_to',
        headerName: config.labels.checkedOutTo,
        minWidth: 220,
        valueGetter: function (params) { return assignedToLabel(params.data.assigned_to); },
        cellRenderer: function (params) { return assignedToLink(params.data.assigned_to); },
        filter: false,
      },
      {
        field: 'owner',
        headerName: config.labels.owner,
        minWidth: 180,
        valueGetter: function (params) { return objectName(params.data.owner); },
        cellRenderer: function (params) { return buildLink(config.usersUrl, params.data.owner); },
        filter: false,
      },
      {
        field: 'location',
        headerName: config.labels.location,
        minWidth: 180,
        valueGetter: function (params) { return objectName(params.data.location); },
        cellRenderer: function (params) { return colorChip(params.data.location, config.locationsUrl); },
        filter: false,
      },
      {
        field: 'purchase_cost',
        headerName: config.labels.purchaseCost,
        minWidth: 140,
        filter: 'agTextColumnFilter',
        comparator: currencyComparator,
      },
    ];

    var gridOptions = {
      columnDefs: columnDefs,
      rowData: [],
      defaultColDef: {
        sortable: true,
        resizable: true,
        floatingFilter: false,
        filterParams: {
          buttons: ['clear'],
          closeOnApply: true,
        },
      },
      domLayout: 'autoHeight',
      rowSelection: {
        mode: 'multiRow',
        checkboxes: true,
        headerCheckbox: true,
        selectAll: 'filtered',
        enableClickSelection: false,
      },
      animateRows: true,
      ensureDomOrder: true,
      overlayLoadingTemplate: '<span class="asset-grid-empty">' + escapeHtml(config.labels.loading) + '</span>',
      overlayNoRowsTemplate: '<span class="asset-grid-empty">' + escapeHtml(config.labels.empty) + '</span>',
      onSelectionChanged: updateBulkInputs,
      onSortChanged: syncSortToServer,
      isExternalFilterPresent: isExternalFilterPresent,
      doesExternalFilterPass: doesExternalFilterPass,
    };

    var gridApi = agGrid.createGrid ? agGrid.createGrid(gridElement, gridOptions) : new agGrid.Grid(gridElement, gridOptions);
    if (!agGrid.createGrid) {
      gridApi = gridOptions.api;
    }

    function isExternalFilterPresent() {
      return pageFilterOrder.some(function (field) {
        return state.pageFilters[field].length > 0;
      });
    }

    function doesExternalFilterPass(node) {
      return pageFilterOrder.every(function (field) {
        var selected = state.pageFilters[field];
        if (!selected.length) {
          return true;
        }
        var value = extractFilterLabel(field, node.data);
        return selected.indexOf(value) !== -1;
      });
    }

    function loadPage() {
      state.loading = true;
      updateUrl();
      updateSummary();
      renderPager();
      setLoadingOverlay();
      updateBulkInputs();

      var requestHeaders = {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Accept': 'application/json',
      };

      if (config.apiToken) {
        requestHeaders.Authorization = 'Bearer ' + config.apiToken;
      }

      fetch(apiUrl(), {
        credentials: 'same-origin',
        headers: requestHeaders,
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Failed to load assets');
          }
          return response.json();
        })
        .then(function (payload) {
          state.rows = Array.isArray(payload.rows) ? payload.rows : [];
          state.total = Number(payload.total) || 0;
          if (state.page > totalPages()) {
            state.page = totalPages();
            return loadPage();
          }
          gridApi.setGridOption('rowData', state.rows);
          gridApi.setGridOption('loading', false);
          populatePageFilters(state.rows);
          gridApi.onFilterChanged();
          state.loading = false;
          updateSummary();
          renderPager();
          updateBulkInputs();
          if (state.rows.length) {
            gridApi.hideOverlay();
          } else {
            setEmptyOverlay();
          }
        })
        .catch(function (error) {
          console.error(error);
          state.loading = false;
          state.rows = [];
          state.total = 0;
          gridApi.setGridOption('loading', false);
          gridApi.setGridOption('rowData', []);
          populatePageFilters([]);
          gridApi.onFilterChanged();
          updateSummary();
          renderPager();
          setEmptyOverlay();
        });
    }

    searchInput.addEventListener('input', function (event) {
      var value = event.target.value;
      window.clearTimeout(state.debounceTimer);
      state.debounceTimer = window.setTimeout(function () {
        state.search = value;
        state.page = 1;
        loadPage();
      }, 250);
    });

    clearSearchButton.addEventListener('click', function () {
      searchInput.value = '';
      state.search = '';
      state.page = 1;
      loadPage();
    });

    refreshButton.addEventListener('click', function () {
      loadPage();
    });

    pageSizeSelect.addEventListener('change', function (event) {
      state.size = Number(event.target.value) || 20;
      state.page = 1;
      loadPage();
    });

    prevButton.addEventListener('click', function () {
      if (state.page <= 1) {
        return;
      }
      state.page -= 1;
      loadPage();
    });

    nextButton.addEventListener('click', function () {
      if (state.page >= totalPages()) {
        return;
      }
      state.page += 1;
      loadPage();
    });

    initPageFilters();

    if (bulkForm) {
      bulkForm.addEventListener('submit', updateBulkInputs);
    }

    window.assetsAgGridApp = {
      config: config,
      state: state,
      reload: loadPage,
      getRows: function () {
        return state.rows.slice();
      },
    };

    loadPage();
  })();
</script>
