@if ($showPlanningFieldsIntro ?? true)
    <div class="text-muted" style="margin-bottom:8px;">
        <strong>Reuse planning fields</strong>
        <x-new-feature-label />
        <span class="small">Reusable Now, Due Back, Reserved, Estimated Savings, Shortfall, and Pending to Buy.</span>
    </div>
@endif
<table
        data-cookie-id-table="{{ $tableId }}"
        data-id-table="{{ $tableId }}"
        data-side-pagination="server"
        data-show-footer="{{ !empty($showFooter) ? 'true' : 'false' }}"
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
        <th data-field="request_id" data-sortable="true" data-visible="true" data-switchable="false" data-formatter="requestDetailLinkFormatter"@if (!empty($showFooter)) data-footer-formatter="requestPageTotalLabelFormatter"@endif>ID</th>
        <th data-field="requested_discipline" data-sortable="true">Discipline</th>
        <th data-field="company" data-sortable="true">{{ trans('general.company') }}</th>
        <th data-field="image" data-sortable="true" data-formatter="imageFormatter">{{ trans('general.image') }}</th>
        <th data-field="category" data-sortable="true">{{ trans('general.category') }}</th>
        <th data-field="name" data-sortable="true" data-formatter="requestModelLinkFormatter">Model</th>
        <th data-field="reference_price_snapshot" data-sortable="true" data-formatter="requestReferencePriceFormatter">Reference Price</th>
        <th data-field="qty" data-sortable="true" data-request-tooltip="Total quantity needed for this model request."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Quantity</th>
        <th data-field="total_need_cost" data-sortable="true" data-formatter="requestTotalNeedCostFormatter"@if (!empty($showFooter)) data-footer-formatter="sumFormatter"@endif>Total Need Cost</th>
        <th data-field="project" data-sortable="true" data-formatter="requestProjectLinkFormatter">{{ trans('general.project') }}</th>
        <th data-field="needed_by_date" data-sortable="true" data-formatter="dateDisplayFormatter">Needed By</th>
        <th data-field="reusable_quantity" data-sortable="true" data-formatter="requestReusableNowFormatter" data-request-tooltip="Unassigned deployable assets available immediately."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Reusable Now</th>
        <th data-field="due_back_before_needed_by_quantity" data-sortable="true" data-formatter="requestDueBackFormatter" data-request-tooltip="Assigned assets in active use that are expected back on or before the needed-by date. RFQ-reserved assets are excluded."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Due Back</th>
        <th data-field="reserved_count" data-sortable="true" data-formatter="requestReservedFormatter" data-request-tooltip="Assets marked in the RFQ reserved status for this same project with an expected checkin date."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Reserved</th>
        <th data-field="reserved_by_other_rfqs_count" data-sortable="true" data-formatter="requestReservedByOtherProjectFormatter" data-request-tooltip="Assets marked in the RFQ reserved status for a different project. This bucket is project-based only; discipline is not considered."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Reserved by Other Project</th>
        <th data-field="estimated_savings" data-sortable="true" data-formatter="requestSavingsFormatter" data-request-tooltip="The model cost used here is the one saved at the time of the request."@if (!empty($showFooter)) data-footer-formatter="sumFormatter"@endif>Estimated Savings</th>
        <th data-field="procurement_shortfall" data-sortable="true" data-request-tooltip="Remaining quantity not covered by reusable now plus due back."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Shortfall</th>
        <th data-field="amount_to_buy" data-sortable="true" data-formatter="requestAmountToBuyFormatter"@if (!empty($showFooter)) data-footer-formatter="sumFormatter"@endif>Pending to Buy</th>
        <th data-field="status" data-sortable="true" data-formatter="requestRequesterStatusFormatter">Status</th>
        <th data-field="request_date" data-sortable="true" data-formatter="dateDisplayFormatter">{{ trans('general.requested_date') }}</th>
        <th data-field="updated_at" data-sortable="true" data-formatter="dateDisplayFormatter">Updated</th>
        <th data-field="actions" data-switchable="false" data-searchable="false" data-sortable="false" data-visible="true" data-formatter="requestWorkflowActionsFormatter">{{ trans('table.actions') }}</th>
    </tr>
    </thead>
</table>
