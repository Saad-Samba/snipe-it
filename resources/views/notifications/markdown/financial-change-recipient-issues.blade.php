@component('mail::message')
# Financial Report Recipient Issues

The latest finance report run completed with recipient resolution issues:

@foreach($issues as $issue)
- **{{ $issue['email'] }}**: {{ $issue['reason'] }}
@endforeach

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name ?? config('app.name') }}
@endcomponent
