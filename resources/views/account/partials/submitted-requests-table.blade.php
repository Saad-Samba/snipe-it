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
        <th data-field="reference_price_snapshot" data-sortable="true" data-formatter="requestReferencePriceFormatter">Reference Price</th>
        <th data-field="qty" data-sortable="true" data-request-tooltip="Total quantity needed for this model request.">Quantity</th>
        <th data-field="requested_discipline" data-sortable="true">Discipline</th>
        <th data-field="project" data-sortable="true" data-formatter="requestProjectLinkFormatter">{{ trans('general.project') }}</th>
        <th data-field="needed_by_date" data-sortable="true" data-formatter="dateDisplayFormatter">Needed By</th>
        <th data-field="reusable_quantity" data-sortable="true" data-formatter="requestReusableNowFormatter" data-request-tooltip="Unassigned deployable assets available immediately.">Reusable Now</th>
        <th data-field="due_back_before_needed_by_quantity" data-sortable="true" data-formatter="requestDueBackFormatter" data-request-tooltip="Assigned assets in active use that are expected back on or before the needed-by date. RFQ-reserved assets are excluded.">Due Back</th>
        <th data-field="reserved_count" data-sortable="true" data-formatter="requestReservedFormatter" data-request-tooltip="Assets marked in the RFQ reserved status for this same project with an expected checkin date.">Reserved</th>
        <th data-field="reserved_by_other_rfqs_count" data-sortable="true" data-formatter="requestReservedByOtherProjectFormatter" data-request-tooltip="Assets marked in the RFQ reserved status for a different project. This bucket is project-based only; discipline is not considered.">Reserved by Other Project</th>
        <th data-field="procurement_shortfall" data-sortable="true" data-request-tooltip="Remaining quantity not covered by reusable now plus due back.">Shortfall</th>
        <th data-field="estimated_savings" data-sortable="true" data-formatter="requestSavingsFormatter" data-request-tooltip="The model cost used here is the one saved at the time of the request.">Estimated Savings</th>
        <th data-field="amount_to_buy" data-sortable="true" data-formatter="requestAmountToBuyFormatter">Amount to Buy</th>
        <th data-field="status" data-sortable="true" data-formatter="requestStatusFormatter">Status</th>
        <th data-field="request_date" data-sortable="true" data-formatter="dateDisplayFormatter">{{ trans('general.requested_date') }}</th>
        <th data-field="updated_at" data-sortable="true" data-formatter="dateDisplayFormatter">Updated</th>
        <th data-field="actions" data-switchable="false" data-searchable="false" data-sortable="false" data-visible="true" data-formatter="requestWorkflowActionsFormatter">{{ trans('table.actions') }}</th>
    </tr>
    </thead>
</table>
