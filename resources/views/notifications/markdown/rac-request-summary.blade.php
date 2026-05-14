@component('mail::message')
# {{ trans('mail.hello') }},

{{ trans('mail.rac_request_scope_match_intro') }}

@if ($project_name)
**{{ trans('general.project') }}:** {{ $project_name }}
@endif

**Requestor:** {{ $requester->display_name }}

**{{ trans('general.requested') }}:** {{ $submitted_at }}

@component('mail::table')
| Model | {{ trans('general.qty') }} | Needed By |
| ------------- | ------------- | ------------- |
@foreach($lines as $line)
| [{{ $line['model_name'] }}]({{ $line['model_show_url'] }}) | {{ $line['requested_quantity'] }} | {{ $line['needed_by_date'] }} |
@endforeach
@endcomponent

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
