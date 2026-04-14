@component('mail::message')
# Financial Asset Change Report

@if($company)
Company: **{{ $company->name }}**
@endif

This report covers **{{ $totalEventCount }}** financial change event(s).

- Financially relevant status changes: **{{ $statusEventCount }}**
- Company changes: **{{ $companyEventCount }}**

The detailed event export is attached as a CSV file.

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name ?? config('app.name') }}
@endcomponent
