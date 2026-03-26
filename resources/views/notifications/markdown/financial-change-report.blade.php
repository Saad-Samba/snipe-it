@component('mail::message')
# Financial Asset Change Report

@if($company)
Company: **{{ $company->name }}**
@endif

@if($statusEvents->isNotEmpty())
## Financially Relevant Status Changes

<table class="table" width="100%" cellpadding="6" cellspacing="0" role="presentation">
    <thead>
    <tr>
        <th align="left">Asset ID</th>
        <th align="left">Asset Tag</th>
        <th align="left">Previous Status</th>
        <th align="left">New Status</th>
        <th align="left">Effective At</th>
        <th align="left">Changed By</th>
    </tr>
    </thead>
    <tbody>
    @foreach($statusEvents as $event)
    <tr>
        <td>{{ $event->asset_id }}</td>
        <td>{{ $event->asset?->asset_tag ?? 'No tag' }}</td>
        <td>{{ $event->previousStatus?->name ?? 'Unassigned' }}</td>
        <td>{{ $event->newStatus?->name ?? 'Unassigned' }}</td>
        <td>{{ $event->effective_at->format('Y-m-d H:i') }}</td>
        <td>{{ $event->changedBy?->display_name ?? $event->changedBy?->username ?? 'System' }}</td>
    </tr>
    @endforeach
    </tbody>
</table>
@endif

@if($companyEvents->isNotEmpty())
## Company Changes

<table class="table" width="100%" cellpadding="6" cellspacing="0" role="presentation">
    <thead>
    <tr>
        <th align="left">Asset ID</th>
        <th align="left">Asset Tag</th>
        <th align="left">Direction</th>
        <th align="left">Previous Company</th>
        <th align="left">New Company</th>
        <th align="left">Effective At</th>
        <th align="left">Changed By</th>
    </tr>
    </thead>
    <tbody>
    @foreach($companyEvents as $event)
    <tr>
        <td>{{ $event->asset_id }}</td>
        <td>{{ $event->asset?->asset_tag ?? 'No tag' }}</td>
        <td>{{ $event->direction === 'entered' ? 'Entered' : 'Left' }}</td>
        <td>{{ $event->previousCompany?->name ?? 'Unassigned' }}</td>
        <td>{{ $event->newCompany?->name ?? 'Unassigned' }}</td>
        <td>{{ $event->effective_at->format('Y-m-d H:i') }}</td>
        <td>{{ $event->changedBy?->display_name ?? $event->changedBy?->username ?? 'System' }}</td>
    </tr>
    @endforeach
    </tbody>
</table>
@endif

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name ?? config('app.name') }}
@endcomponent
