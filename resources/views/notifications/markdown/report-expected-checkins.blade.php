@component('mail::message')
# {{ trans('mail.hello') }},

{{ trans(trans()->hasForLocale('mail.due_to_checkin') ? 'mail.due_to_checkin' : 'general.due_to_checkin', ['count' => $assets->count()]) }}

@component('mail::table')
| {{ trans('general.assets') }} | {{ trans(trans()->hasForLocale('mail.checked_out_to') ? 'mail.checked_out_to' : 'general.checked_out_to') }} | {{ trans(trans()->hasForLocale('mail.expected_checkin') ? 'mail.expected_checkin' : 'general.expected_checkin') }} |
| ------------- | ------------- | ------------- |
@foreach ($assets as $asset)
@php
$checkin = Helper::getFormattedDateObject($asset->expected_checkin, 'date');

$assignedToName = $asset->assignedTo ? $asset->assignedTo->present()->fullName : trans('general.unknown_user');
$assignedToRoute = $asset->assignedTo ? route($asset->targetShowRoute().'.show', [$asset->assignedTo->id]) : '';
@endphp
| [{{ $asset->display_name }}]({{ route('hardware.show', $asset) }}) | @if ($asset->assignedTo) [{{ $assignedToName }}]({{ $assignedToRoute }}) @else {{ $assignedToName }} @endif  | {{ $checkin['formatted'] }}
@endforeach
@endcomponent

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
