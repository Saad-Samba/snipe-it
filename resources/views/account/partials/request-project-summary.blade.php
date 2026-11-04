<div class="well well-sm" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:15px;">
    <div style="grid-column:1/-1;">
        <strong>Reuse Summary</strong>
        <x-new-feature-label />
    </div>
    @if (isset($summary['models_count']))
        <div><strong>Models</strong><br>{{ $summary['models_count'] }}</div>
    @endif
    <div><strong>{{ $requestsLabel ?? 'Requests' }}</strong><br>{{ $summary['requests_count'] }}</div>
    <div><strong>Requested Qty</strong><br>{{ $summary['total_needed'] }}</div>
    <div><strong>Available Now</strong><br>{{ $summary['reusable_now'] }}</div>
    <div><strong title="Assigned assets expected back on or before the request’s Needed By date; excludes RFQ-reserved assets.">Due Back</strong><br>{{ $summary['due_back_before_needed_by'] }}</div>
    <div><strong>Estimated Qty to Procure</strong><br>{{ $summary['shortfall'] }}</div>
    <div><strong>Reserved for This RFQ</strong><br>{{ isset($filteredProject) && $filteredProject->is_rfq ? $summary['reserved_count'] : '—' }}</div>
    <div><strong>Reserved for Other RFQs</strong><br>{{ $summary['reserved_by_other_rfqs_count'] }}</div>
    <div><strong>Estimated Savings</strong><br>{{ \App\Helpers\Helper::formatCurrencyOutput($summary['estimated_savings']) }}</div>
    <div><strong>Estimated Purchase Cost</strong><br>{{ \App\Helpers\Helper::formatCurrencyOutput($summary['amount_to_buy']) }}</div>
</div>
