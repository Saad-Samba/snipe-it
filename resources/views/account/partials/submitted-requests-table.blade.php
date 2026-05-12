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
        <th data-field="qty" data-sortable="true">Quantity <a href="#" data-tooltip="true" title="Total quantity needed for this model request."><x-icon type="info-circle" /><span class="sr-only">Total quantity needed for this model request.</span></a></th>
        <th data-field="requested_discipline" data-sortable="true">Discipline</th>
        <th data-field="project" data-sortable="true" data-formatter="requestProjectLinkFormatter">{{ trans('general.project') }}</th>
        <th data-field="needed_by_date" data-sortable="true" data-formatter="dateDisplayFormatter">Needed By</th>
        <th data-field="reusable_quantity" data-sortable="true"><i class="fas fa-recycle" aria-hidden="true"></i> Reusable Now <a href="#" data-tooltip="true" title="Unassigned deployable assets available immediately."><x-icon type="info-circle" /><span class="sr-only">Unassigned deployable assets available immediately.</span></a></th>
        <th data-field="due_back_before_needed_by_quantity" data-sortable="true"><i class="fas fa-calendar-check" aria-hidden="true"></i> Due Back <a href="#" data-tooltip="true" title="Assigned assets in active use that are expected back on or before the needed-by date. RFQ-reserved assets are excluded."><x-icon type="info-circle" /><span class="sr-only">Assigned assets in active use that are expected back on or before the needed-by date. RFQ-reserved assets are excluded.</span></a></th>
        <th data-field="reserved_count" data-sortable="true">Reserved <a href="#" data-tooltip="true" title="Assets marked in the RFQ reserved status for this same project with an expected checkin date."><x-icon type="info-circle" /><span class="sr-only">Assets marked in the RFQ reserved status for this same project with an expected checkin date.</span></a></th>
        <th data-field="reserved_by_other_rfqs_count" data-sortable="true">Reserved by Other Project <a href="#" data-tooltip="true" title="Assets marked in the RFQ reserved status for a different project, regardless of whether they are due back before this request."><x-icon type="info-circle" /><span class="sr-only">Assets marked in the RFQ reserved status for a different project, regardless of whether they are due back before this request.</span></a></th>
        <th data-field="procurement_shortfall" data-sortable="true">Shortfall <a href="#" data-tooltip="true" title="Remaining quantity not covered by reusable now plus due back."><x-icon type="info-circle" /><span class="sr-only">Remaining quantity not covered by reusable now plus due back.</span></a></th>
        <th data-field="estimated_savings" data-sortable="true" data-formatter="requestSavingsFormatter">Estimated Savings <a href="#" data-tooltip="true" title="The model cost used here is the one saved at the time of the request."><x-icon type="info-circle" /><span class="sr-only">The model cost used here is the one saved at the time of the request.</span></a></th>
        <th data-field="amount_to_buy" data-sortable="true" data-formatter="requestAmountToBuyFormatter">Amount to Buy</th>
        <th data-field="status" data-sortable="true" data-formatter="requestStatusFormatter">Status</th>
        <th data-field="request_date" data-sortable="true" data-formatter="dateDisplayFormatter">{{ trans('general.requested_date') }}</th>
        <th data-field="updated_at" data-sortable="true" data-formatter="dateDisplayFormatter">Updated</th>
        <th data-field="actions" data-switchable="false" data-searchable="false" data-sortable="false" data-visible="true" data-formatter="requestWorkflowActionsFormatter">{{ trans('table.actions') }}</th>
    </tr>
    </thead>
</table>
