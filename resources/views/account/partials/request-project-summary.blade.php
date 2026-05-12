<div class="well well-sm" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:15px;">
    <div><strong>Requests</strong><br>{{ $summary['requests_count'] }}</div>
    <div><strong>Total Needed</strong><br>{{ $summary['total_needed'] }}</div>
    <div><strong>Reusable Now</strong><br>{{ $summary['reusable_now'] }}</div>
    <div><strong>Due Back</strong><br>{{ $summary['due_back_before_needed_by'] }}</div>
    <div><strong>Shortfall</strong><br>{{ $summary['shortfall'] }}</div>
    <div><strong>Booked</strong><br>{{ $summary['booked_count'] }}</div>
    <div><strong>Reserved</strong><br>{{ $summary['reserved_count'] }}</div>
    <div><strong>Estimated Savings</strong><br>{{ \App\Helpers\Helper::formatCurrencyOutput($summary['estimated_savings']) }}</div>
</div>
