@component('mail::message')
# {{ trans('mail.hello') }},

{{ trans('mail.rac_request_scope_match_intro') }}

@if ($project_name)
**{{ trans('general.project') }}:** {{ $project_name }}
@endif

**{{ trans('mail.user') }}:** {{ $requester->display_name }}

**{{ trans('general.requested') }}:** {{ $submitted_at }}

@component('mail::table')
| {{ trans('general.asset_model') }} | {{ trans('general.project') }} | {{ trans('general.discipline') }} | {{ trans('general.qty') }} | {{ trans('mail.rac_reusable_now_in_scope') }} | Needed By | Actions |
| ------------- | ------------- | ------------- | ------------- | ------------- | ------------- | ------------- |
@foreach($lines as $line)
| {{ $line['model_name'] }} | {{ $line['project_name'] }} | {{ $line['discipline_name'] }} | {{ $line['requested_quantity'] }} | {{ $line['reusable_quantity'] }} | {{ $line['needed_by_date'] }} | [{{ trans('mail.rac_model_link') }}]({{ $line['model_show_url'] }}) / [{{ trans('mail.rac_project_link') }}]({{ $line['project_requests_url'] }}) / [{{ trans('mail.rac_review_link') }}]({{ $line['request_detail_url'] }}) |
@endforeach
@endcomponent

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
