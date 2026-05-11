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
                            <th data-field="reusable_quantity" data-sortable="true">Reusable</th>
                            <th data-field="procurement_shortfall" data-sortable="true">Shortfall</th>
                            <th data-field="estimated_savings" data-sortable="true" data-formatter="requestSavingsFormatter">Estimated Savings</th>
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

            function getAdvancedSearchButton() {
                var $toolbar = $table.closest('.bootstrap-table').find('.fixed-table-toolbar');
                return $toolbar.find('.fa-search-plus').closest('button');
            }

            function renderAdvancedSearchState() {
                var activeFilters = getActiveAdvancedFilters();
                var keys = Object.keys(activeFilters);
                var $button = getAdvancedSearchButton();

                if (!$button.length) {
                    return;
                }

                $button.toggleClass('btn-warning', keys.length > 0);
                $button.toggleClass('btn-primary', keys.length === 0);

                if (keys.length > 0) {
                    $button.attr('title', 'Advanced search active');
                } else {
                    $button.attr('title', 'Advanced search');
                }

                if ($button.data('bs.tooltip')) {
                    $button.tooltip('fixTitle');
                }
            }

            $table.on('load-success.bs.table column-advanced-search.bs.table post-header.bs.table', renderAdvancedSearchState);
            renderAdvancedSearchState();
        });
    </script>
@stop
