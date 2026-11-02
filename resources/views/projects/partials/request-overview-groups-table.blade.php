<table
        data-cookie-id-table="{{ $tableId }}"
        data-id-table="{{ $tableId }}"
        data-side-pagination="client"
        data-show-footer="{{ !empty($showFooter) ? 'true' : 'false' }}"
        data-sort-order="asc"
        id="{{ $tableId }}"
        class="table table-striped snipe-table"
        data-url="{{ $dataUrl }}"
        data-export-options='{
          "fileName": "{{ $exportFileName }}",
          "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
        }'>
    <thead>
    <tr>
        <th data-field="requested_discipline" data-sortable="true"@if (!empty($showFooter)) data-footer-formatter="requestProjectTotalLabelFormatter"@endif>Discipline</th>
        <th data-field="total_needed" data-sortable="true"@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>Total Needed</th>
        <th data-field="reusable_now" data-sortable="true"@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>All Sites Reusable Now</th>
        <th data-field="shortfall" data-sortable="true"@if (!empty($showFooter)) data-footer-formatter="qtySumFormatter"@endif>All Sites Shortfall</th>
        <th data-field="status" data-sortable="true" data-formatter="projectRequestGroupStatusFormatter">Status</th>
        <th data-field="drill_down_url" data-switchable="false" data-searchable="false" data-sortable="false" data-visible="true" data-formatter="projectRequestGroupDrillDownFormatter">{{ trans('table.actions') }}</th>
    </tr>
    </thead>
</table>
