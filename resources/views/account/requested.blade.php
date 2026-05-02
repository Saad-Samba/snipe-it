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
                            <th data-field="project" data-sortable="true">{{ trans('general.project') }}</th>
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
@stop
