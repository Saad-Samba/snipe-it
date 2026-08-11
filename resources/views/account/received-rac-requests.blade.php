@extends('layouts/default')

@section('title')
    {{ $pageTitle }}
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-body">
                    <div class="alert alert-info">
                        These requests were routed to you because reusable inventory falls within one or more of your company-and-discipline RAC scopes. Open a request to review and allocate the matching inventory.
                    </div>

                    <table
                        data-cookie-id-table="racReceivedRequests"
                        data-id-table="racReceivedRequests"
                        data-side-pagination="server"
                        data-sort-order="desc"
                        data-sort-name="received_at"
                        id="racReceivedRequests"
                        class="table table-striped snipe-table"
                        data-url="{{ $dataUrl }}"
                        data-export-options='{
                          "fileName": "rac-received-requests-{{ date('Y-m-d') }}",
                          "ignoreColumn": ["actions"]
                        }'>
                        <thead>
                        <tr>
                            <th data-field="request_id" data-sortable="true" data-formatter="requestDetailLinkFormatter">ID</th>
                            <th data-field="name" data-sortable="true" data-formatter="requestModelLinkFormatter">Inventory</th>
                            <th data-field="project" data-sortable="true" data-formatter="requestProjectLinkFormatter">{{ trans('general.project') }}</th>
                            <th data-field="requested_by" data-sortable="true">Requestor</th>
                            <th data-field="requested_for_display" data-sortable="true">Requested For</th>
                            <th data-field="company" data-sortable="true">Destination Company</th>
                            <th data-field="inventory_disciplines" data-sortable="true">Inventory Discipline(s)</th>
                            <th data-field="qty" data-sortable="true">Quantity</th>
                            <th data-field="remaining_quantity" data-sortable="true">Remaining</th>
                            <th data-field="needed_by_date" data-sortable="true" data-formatter="dateDisplayFormatter">Needed By</th>
                            <th data-field="rac_status" data-sortable="true" data-formatter="requestStatusFormatter">Status</th>
                            <th data-field="received_at" data-sortable="true" data-formatter="dateDisplayFormatter">Received</th>
                            <th data-field="updated_at" data-sortable="true" data-formatter="dateDisplayFormatter">Updated</th>
                            <th data-field="actions" data-switchable="false" data-searchable="false" data-sortable="false" data-formatter="requestWorkflowActionsFormatter">{{ trans('table.actions') }}</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table')
@stop
