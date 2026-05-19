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
                            <a href="{{ route('requests.index') }}" class="btn btn-default btn-sm">View all submitted requests</a>
                        </div>
                    @endif
                    @if (!empty($filteredProject))
                        <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <span>
                                Showing requests for project:
                                <strong>{{ $filteredProject->name }}</strong>
                            </span>
                            <span style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a href="{{ route('projects.show', ['project' => $filteredProject->id, 'tab' => 'requests']) }}" class="btn btn-default btn-sm">Open project requests</a>
                                <a href="{{ route('requests.index') }}" class="btn btn-default btn-sm">View all submitted requests</a>
                            </span>
                        </div>
                    @endif
                    @if (!empty($filteredProject) && !empty($projectSummary))
                        @include('account.partials.request-project-summary', ['summary' => $projectSummary])
                    @endif
                    @include('account.partials.submitted-requests-table', [
                        'tableId' => 'userRequests',
                        'requestMode' => $requestMode ?? 'requester',
                        'dataUrl' => $dataUrl ?? route('api.requests.index'),
                        'exportFileName' => 'my-requested-assets-'.date('Y-m-d'),
                    ])

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
