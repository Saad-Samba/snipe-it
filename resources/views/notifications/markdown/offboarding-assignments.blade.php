@component('mail::message')
# {{ trans('mail.hello') }},

@if($test_mode)
**{{ trans('mail.offboarding_test_delivery') }}:** {{ $intended_recipient }}

@endif
{{ trans('mail.offboarding_assignments_intro') }}

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; width:100%; margin-top:18px; margin-bottom:18px;">
    <thead>
    <tr>
        <th align="left" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368;">{{ trans('mail.offboarding_user') }}</th>
        <th align="left" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368;">{{ trans('general.type') }}</th>
        <th align="left" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368;">{{ trans('mail.item') }}</th>
        <th align="left" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368;">{{ trans('general.company') }}</th>
        <th align="left" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368;">{{ trans('mail.offboarding_discipline') }}</th>
        <th align="left" style="padding:0 0 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368;">{{ trans('general.action') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lines as $line)
        <tr>
            <td align="left" style="padding:12px 12px 12px 0; border-bottom:1px solid #f1f3f4;">
                {{ $line['user_name'] }}<br><small>{{ $line['username'] }}</small>
            </td>
            <td align="left" style="padding:12px 12px 12px 0; border-bottom:1px solid #f1f3f4;">{{ ucfirst($line['kind']) }}</td>
            <td align="left" style="padding:12px 12px 12px 0; border-bottom:1px solid #f1f3f4;">
                <a href="{{ $line['item_url'] }}">{{ $line['item_name'] }}</a><br><small>{{ $line['item_reference'] }}</small>
            </td>
            <td align="left" style="padding:12px 12px 12px 0; border-bottom:1px solid #f1f3f4;">{{ $line['company_name'] ?: '-' }}</td>
            <td align="left" style="padding:12px 12px 12px 0; border-bottom:1px solid #f1f3f4;">
                {{ $line['discipline_name'] ?: '-' }}
                @if($line['routing_warning'] ?? null)
                    <br><small><strong>{{ trans('mail.offboarding_routing_warning') }}:</strong> {{ $line['routing_warning'] }}</small>
                @endif
            </td>
            <td align="left" style="padding:12px 0; border-bottom:1px solid #f1f3f4;"><a href="{{ $line['item_url'] }}">{{ trans('mail.offboarding_review_item') }}</a></td>
        </tr>
    @endforeach
    </tbody>
</table>

{{ trans('mail.offboarding_assignments_action') }}

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
