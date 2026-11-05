@if ($showPlanningFieldsIntro ?? true)
    <div class="text-muted" style="margin-bottom:8px;">
        <strong>Reuse planning fields</strong>
        <x-new-feature-label />
        <span class="small">Available Now, Due Back, RFQ Reservations, Estimated Savings, Estimated Qty to Procure, and Estimated Purchase Cost. All Sites figures are aggregate counts across Sites; asset details remain role-scoped.</span>
    </div>
@endif
<table
        data-cookie-id-table="{{ $tableId }}"
        data-id-table="{{ $tableId }}"
        data-side-pagination="{{ $sidePagination ?? 'server' }}"
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
        <th data-field="request_id" data-sortable="true" data-visible="true" data-switchable="false" data-formatter="requestDetailLinkFormatter"@if (!empty($showFooter)) data-footer-formatter="{{ $footerLabelFormatter ?? 'requestPageTotalLabelFormatter' }}"@endif>ID</th>
        <th data-field="requested_discipline" data-sortable="true">Discipline</th>
        <th data-field="company" data-sortable="true">{{ trans('general.company') }}</th>
        <th data-field="image" data-sortable="true" data-formatter="imageFormatter">{{ trans('general.image') }}</th>
        <th data-field="category" data-sortable="true">{{ trans('general.category') }}</th>
        <th data-field="name" data-sortable="true" data-formatter="requestModelLinkFormatter">Model</th>
        <th data-field="reference_price_snapshot" data-sortable="true" data-formatter="requestReferencePriceFormatter">Reference Price</th>
        <th data-field="qty" data-sortable="true" data-request-tooltip="Total quantity needed for this model request."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Requested Qty</th>
        <th data-field="total_need_cost" data-sortable="true" data-formatter="requestTotalNeedCostFormatter"@if (!empty($showFooter)) data-footer-formatter="sumFormatter"@endif>Total Need Cost</th>
        @if ($showProject ?? true)
            <th data-field="project" data-sortable="true" data-formatter="requestProjectLinkFormatter">{{ trans('general.project') }}</th>
        @endif
        @if ($showRequester ?? false)
            <th data-field="requested_by" data-sortable="true">Requester</th>
        @endif
        <th data-field="needed_by_date" data-sortable="true" data-formatter="dateDisplayFormatter">Needed By</th>
        <th data-field="reusable_quantity" data-sortable="true" data-formatter="requestReusableNowFormatter" data-request-tooltip="Aggregate unassigned deployable assets available across all Sites. Asset details remain role-scoped."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Available Now</th>
        <th data-field="due_back_before_needed_by_quantity" data-sortable="true" data-formatter="requestDueBackFormatter" data-request-tooltip="Aggregate assigned assets across all Sites expected back by the needed-by date. RFQ-reserved assets are excluded."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Due Back</th>
        <th data-field="assigned_candidates_count" data-sortable="false" data-formatter="requestAssignedCandidatesFormatter" data-request-tooltip="Matching assets currently assigned, including assets with missing project links. Release confirmation is required; excludes assets due back by the needed date. Counts and details follow your asset permissions.">In Use</th>
        <th data-field="reserved_count" data-sortable="true" data-formatter="requestReservedFormatter" data-request-tooltip="Assets held in the RFQ reserved status for this request’s RFQ. Not applicable to requests for active projects."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Reserved for This RFQ</th>
        <th data-field="reserved_by_other_rfqs_count" data-sortable="true" data-formatter="requestReservedByOtherProjectFormatter" data-request-tooltip="Assets held in the RFQ reserved status for other RFQs; discipline is not considered."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Reserved for Other RFQs</th>
        <th data-field="estimated_savings" data-sortable="true" data-formatter="requestSavingsFormatter" data-request-tooltip="The model cost used here is the one saved at the time of the request."@if (!empty($showFooter)) data-footer-formatter="sumFormatter"@endif>Estimated Savings</th>
        <th data-field="procurement_shortfall" data-sortable="true" data-request-tooltip="Estimated quantity not covered by available assets plus assets due back by the needed date. In-use assets do not reduce this estimate until released."@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Estimated Qty to Procure</th>
        <th data-field="amount_to_buy" data-sortable="true" data-formatter="requestAmountToBuyFormatter"@if (!empty($showFooter)) data-footer-formatter="sumFormatter"@endif>Estimated Purchase Cost</th>
        <th data-field="status" data-sortable="true" data-formatter="requestRequesterStatusFormatter">Status</th>
        <th data-field="request_date" data-sortable="true" data-formatter="dateDisplayFormatter">{{ trans('general.requested_date') }}</th>
        <th data-field="updated_at" data-sortable="true" data-formatter="dateDisplayFormatter">Updated</th>
        @if ($showActions ?? true)
            <th data-field="actions" data-switchable="false" data-searchable="false" data-sortable="false" data-visible="true" data-formatter="requestWorkflowActionsFormatter">{{ trans('table.actions') }}</th>
        @endif
    </tr>
    </thead>
</table>
