@extends('layouts/default')

{{-- Page title --}}
@section('title')
   {{ $pageTitle ?? trans('general.requested_assets') }}
@stop

{{-- Account page content --}}
@section('content')

    <div class="row">
        <div class="col-md-12">

            <div class="box box-default">
                <div class="box-body">
                    @if (!empty($filteredModel))
                        <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <span>
                                Showing requests for model:
                                <strong>{{ $filteredModel->name }}</strong>
                            </span>
                            <a href="{{ route('account.requested') }}" class="btn btn-default btn-sm">View all submitted requests</a>
                        </div>
                    @endif
                    @if (!empty($filteredProject))
                        <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <span>
                                Showing requests for project:
                                <strong>{{ $filteredProject->name }}</strong>
                            </span>
                            <a href="{{ route('account.requested') }}" class="btn btn-default btn-sm">View all submitted requests</a>
                        </div>
                    @endif
                    <div id="request-advanced-filters-indicator" class="alert alert-warning" style="display:none;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <span>
                            Advanced filters active:
                            <span id="request-advanced-filters-summary"></span>
                        </span>
                        <button type="button" id="request-advanced-filters-clear" class="btn btn-default btn-sm">Clear advanced filters</button>
                    </div>

                    <table

                            data-cookie-id-table="userRequests"
                            data-id-table="userRequests"
                            data-side-pagination="server"
                            data-sort-order="desc"
                            data-request-mode="{{ $requestMode ?? 'requester' }}"
                            id="userRequests"
                            class="table table-striped snipe-table"
                            data-url="{{ $dataUrl ?? route('api.assets.requested') }}"
                            data-export-options='{
                  "fileName": "my-requested-assets-{{ date('Y-m-d') }}",
                  "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                }'>
                        <thead>
                        <tr>
                            <th data-field="request_id" data-sortable="true" data-visible="true" data-switchable="false" data-formatter="requestDetailLinkFormatter">ID</th>
                            <th data-field="image" data-sortable="true" data-formatter="imageFormatter">{{ trans('general.image') }}</th>
                            <th data-field="name" data-sortable="true" data-formatter="requestModelLinkFormatter">Model</th>
                            <th data-field="qty" data-sortable="true">{{ trans('general.qty') }}</th>
                            <th data-field="project" data-sortable="true" data-formatter="requestProjectLinkFormatter">{{ trans('general.project') }}</th>
                            <th data-field="booked_count" data-sortable="true">Booked</th>
                            <th data-field="status" data-sortable="true" data-formatter="requestStatusFormatter">Status</th>
                            <th data-field="request_date" data-sortable="true" data-formatter="dateDisplayFormatter"> {{ trans('general.requested_date') }}</th>
                            <th data-field="updated_at" data-sortable="true" data-formatter="dateDisplayFormatter">Updated</th>
                            <th data-field="actions" data-switchable="false" data-searchable="false" data-sortable="false" data-visible="true" data-formatter="requestWorkflowActionsFormatter">{{ trans('table.actions') }}</th>
                        </tr>
                        </thead>
                    </table>

                </div> <!-- .box-body -->
            </div> <!-- .box-default -->
        </div> <!-- .col-md-9 -->
    </div> <!-- .row-->

@stop
@section('moar_scripts')
    @include ('partials.bootstrap-table')
    <script nonce="{{ csrf_token() }}">
        $(function () {
            var $table = $('#userRequests');
            var $indicator = $('#request-advanced-filters-indicator');
            var $summary = $('#request-advanced-filters-summary');
            var $clear = $('#request-advanced-filters-clear');

            function getActiveAdvancedFilters() {
                var bootstrapTableInstance = $table.data('bootstrap.table');
                var filters = (bootstrapTableInstance && bootstrapTableInstance.filterColumnsPartial) || {};
                var activeFilters = {};

                Object.keys(filters).forEach(function (key) {
                    if (filters[key] !== undefined && filters[key] !== null && String(filters[key]).trim() !== '') {
                        activeFilters[key] = String(filters[key]).trim();
                    }
                });

                return activeFilters;
            }

            function humanizeFilterName(key) {
                var labels = {
                    request_id: 'ID',
                    image: '{{ trans('general.image') }}',
                    name: 'Model',
                    qty: '{{ trans('general.qty') }}',
                    project: '{{ trans('general.project') }}',
                    booked_count: 'Booked',
                    status: 'Status',
                    request_date: '{{ trans('general.requested_date') }}',
                    updated_at: 'Updated',
                };

                return labels[key] || key;
            }

            function renderAdvancedFilterIndicator() {
                var activeFilters = getActiveAdvancedFilters();
                var keys = Object.keys(activeFilters);

                if (!keys.length) {
                    $indicator.hide();
                    $summary.empty();
                    return;
                }

                var badges = keys.map(function (key) {
                    return '<span class="label label-default" style="margin-right:6px;">' + humanizeFilterName(key) + ': ' + $('<div>').text(activeFilters[key]).html() + '</span>';
                });

                $summary.html(badges.join(' '));
                $indicator.css('display', 'flex');
            }

            $table.on('load-success.bs.table column-advanced-search.bs.table', renderAdvancedFilterIndicator);

            $clear.on('click', function () {
                var bootstrapTableInstance = $table.data('bootstrap.table');

                if (!bootstrapTableInstance) {
                    return;
                }

                bootstrapTableInstance.filterColumnsPartial = {};
                $('#avdSearchModal_userRequests').find('input').val('');
                $table.bootstrapTable('refresh', {
                    pageNumber: 1,
                });
                renderAdvancedFilterIndicator();
            });

            renderAdvancedFilterIndicator();
        });
    </script>
@stop
