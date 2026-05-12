<table
        data-cookie-id-table="{{ $tableId }}"
        data-id-table="{{ $tableId }}"
        data-side-pagination="server"
        data-sort-order="desc"
        data-request-mode="{{ $requestMode ?? 'requester' }}"
        id="{{ $tableId }}"
        class="table table-striped snipe-table"
        data-url="{{ $dataUrl }}"
        data-export-options='{
          "fileName": "{{ $exportFileName }}",
          "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
        }'>
    <thead>
    <tr>
        <th data-field="request_id" data-sortable="true" data-visible="true" data-switchable="false" data-formatter="requestDetailLinkFormatter">ID</th>
        <th data-field="image" data-sortable="true" data-formatter="imageFormatter">{{ trans('general.image') }}</th>
        <th data-field="name" data-sortable="true" data-formatter="requestModelLinkFormatter">Model</th>
        <th data-field="qty" data-sortable="true" data-title-tooltip="Total quantity needed for this model request.">Quantity</th>
        <th data-field="project" data-sortable="true" data-formatter="requestProjectLinkFormatter" data-title-tooltip="Project used to group and track these model requests.">{{ trans('general.project') }}</th>
        <th data-field="needed_by_date" data-sortable="true" data-formatter="dateDisplayFormatter" data-title-tooltip="Date by which this requested quantity is needed.">Needed By</th>
        <th data-field="reusable_quantity" data-sortable="true" data-title-tooltip="Unassigned deployable assets available immediately."><i class="fas fa-recycle" aria-hidden="true"></i> Reusable Now</th>
        <th data-field="due_back_before_needed_by_quantity" data-sortable="true" data-title-tooltip="Assigned assets expected back on or before the needed-by date."><i class="fas fa-calendar-check" aria-hidden="true"></i> Due Back</th>
        <th data-field="procurement_shortfall" data-sortable="true" data-title-tooltip="Remaining quantity not covered by reusable now plus due back.">Shortfall</th>
        <th data-field="estimated_savings" data-sortable="true" data-formatter="requestSavingsFormatter" data-title-tooltip="Savings snapshot captured at request time from reusable coverage multiplied by the model reference price.">Estimated Savings</th>
        <th data-field="booked_count" data-sortable="true" data-title-tooltip="Assets currently checked out or assigned for this model and project.">Booked</th>
        <th data-field="reserved_count" data-sortable="true" data-title-tooltip="Assets marked in the RFQ reserved status for this project with an expected checkin date.">Reserved</th>
        <th data-field="status" data-sortable="true" data-formatter="requestStatusFormatter">Status</th>
        <th data-field="request_date" data-sortable="true" data-formatter="dateDisplayFormatter">{{ trans('general.requested_date') }}</th>
        <th data-field="updated_at" data-sortable="true" data-formatter="dateDisplayFormatter">Updated</th>
        <th data-field="actions" data-switchable="false" data-searchable="false" data-sortable="false" data-visible="true" data-formatter="requestWorkflowActionsFormatter">{{ trans('table.actions') }}</th>
    </tr>
    </thead>
</table>
