@component('mail::message')
# Financial Asset Change Report

@if($company)
Company: **{{ $company->name }}**
@endif

@if($statusEvents->isNotEmpty())
## Financially Relevant Status Changes

@foreach($statusEvents as $event)
- Asset #{{ $event->asset_id }} ({{ $event->asset?->asset_tag ?? 'No tag' }}) changed from **{{ $event->previousStatus?->name ?? 'Unassigned' }}** to **{{ $event->newStatus?->name ?? 'Unassigned' }}** on {{ $event->effective_at->format('Y-m-d H:i') }} by {{ $event->changedBy?->display_name ?? $event->changedBy?->username ?? 'System' }}.
@endforeach
@endif

@if($companyEvents->isNotEmpty())
## Company Changes

@foreach($companyEvents as $event)
- Asset #{{ $event->asset_id }} ({{ $event->asset?->asset_tag ?? 'No tag' }}) {{ $event->direction === 'entered' ? 'entered' : 'left' }} your company on {{ $event->effective_at->format('Y-m-d H:i') }}. Previous company: **{{ $event->previousCompany?->name ?? 'Unassigned' }}**. New company: **{{ $event->newCompany?->name ?? 'Unassigned' }}**. Changed by {{ $event->changedBy?->display_name ?? $event->changedBy?->username ?? 'System' }}.
@endforeach
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
