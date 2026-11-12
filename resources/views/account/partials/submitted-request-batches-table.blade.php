<table
    data-cookie-id-table="{{ $tableId }}"
    data-id-table="{{ $tableId }}"
    data-side-pagination="server"
    data-export-data-type="all"
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
        <th data-field="total_quantity" data-sortable="true">Total Needed</th>
        <th data-field="allocated_quantity" data-formatter="requestSubmissionOutcomeFormatter">Allocated</th>
        <th data-field="remaining_quantity" data-formatter="requestSubmissionOutcomeFormatter">Remaining</th>
        <th data-field="amount_to_buy" data-formatter="requestSubmissionPendingToBuyFormatter">Pending to Buy</th>
        <th data-field="status" data-formatter="requestRequesterStatusFormatter">Review Status</th>
        <th data-field="actions" data-switchable="false" data-searchable="false" data-sortable="false" data-formatter="requestBatchActionsFormatter">{{ trans('table.actions') }}</th>
    </tr>
    </thead>
</table>
