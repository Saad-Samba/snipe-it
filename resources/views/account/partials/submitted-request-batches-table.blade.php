<table
    data-cookie-id-table="{{ $tableId }}"
    data-id-table="{{ $tableId }}"
    data-side-pagination="server"
    data-sort-order="desc"
    data-sort-name="submitted_at"
    id="{{ $tableId }}"
    class="table table-striped snipe-table"
    data-url="{{ $dataUrl }}"
    data-export-options='{
      "fileName": "{{ $exportFileName }}",
      "ignoreColumn": ["actions"]
    }'>
    <thead>
    <tr>
        <th data-field="submission_reference" data-sortable="true" data-formatter="requestBatchLinkFormatter">Submission</th>
        <th data-field="project" data-sortable="true" data-formatter="requestProjectLinkFormatter">{{ trans('general.project') }}</th>
        <th data-field="submitted_at" data-sortable="true" data-formatter="dateDisplayFormatter">Submitted At</th>
        <th data-field="needed_by_date" data-sortable="true" data-formatter="dateDisplayFormatter">Needed By</th>
        <th data-field="models_count" data-sortable="true">Models</th>
        <th data-field="total_quantity" data-sortable="true">Total Needed</th>
        <th data-field="reusable_quantity" data-sortable="true">Reusable Now</th>
        <th data-field="due_back_before_needed_by_quantity" data-sortable="true">Due Back</th>
        <th data-field="procurement_shortfall" data-sortable="true">Shortfall</th>
        <th data-field="estimated_savings" data-sortable="true" data-formatter="requestSavingsFormatter">Estimated Savings</th>
        <th data-field="amount_to_buy" data-sortable="true" data-formatter="requestAmountToBuyFormatter">Pending to Buy</th>
        <th data-field="status" data-sortable="true" data-formatter="requestStatusFormatter">Status</th>
        <th data-field="rac_routing_status" data-sortable="true" data-formatter="requestStatusFormatter">RAC Routing</th>
        <th data-field="actions" data-switchable="false" data-searchable="false" data-sortable="false" data-formatter="requestBatchActionsFormatter">{{ trans('table.actions') }}</th>
    </tr>
    </thead>
</table>
