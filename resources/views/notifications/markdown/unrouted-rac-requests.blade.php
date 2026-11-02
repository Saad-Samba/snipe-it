@component('mail::message')
# {{ trans('mail.hello') }},

{{ trans('mail.unrouted_rac_request_intro') }}

@foreach ($lines as $line)
**Request #{{ $line['request_id'] }} — {{ $line['model_name'] }}**

@if ($line['project_name'])
{{ trans('general.project') }}: {{ $line['project_name'] }}
@endif

@foreach ($line['unrouted_scopes'] as $scope)
- {{ $scope['company_name'] ?: 'Site #'.$scope['company_id'] }} / {{ $scope['discipline_name'] ?: 'Discipline #'.$scope['discipline_id'] }} ({{ $scope['reusable_quantity'] }} reusable)
@endforeach

@endforeach

{{ trans('mail.unrouted_rac_request_action') }}

@if ($review_url)
@component('mail::button', ['url' => $review_url])
{{ trans('mail.unrouted_rac_request_cta') }}
@endcomponent
@endif

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
