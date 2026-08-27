@component('mail::message')
# Financial Asset Change Report

@if($company)
{{ trans('general.company') }}: **{{ $company->name }}**
@endif

This report covers **{{ $totalEventCount }}** financial change event(s).

- Financially relevant status changes: **{{ $statusEventCount }}**
- {{ trans('general.company') }} changes: **{{ $companyEventCount }}**

The detailed event export is attached as a CSV file.

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name ?? config('app.name') }}
@endcomponent
