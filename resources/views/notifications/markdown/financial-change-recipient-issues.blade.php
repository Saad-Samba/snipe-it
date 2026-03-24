@component('mail::message')
# Financial Report Recipient Issues

The latest finance report run completed with recipient resolution issues:

@foreach($issues as $issue)
- **{{ $issue['email'] }}**: {{ $issue['reason'] }}
@endforeach

Thanks,<br>
{{ config('app.name') }}
@endcomponent
