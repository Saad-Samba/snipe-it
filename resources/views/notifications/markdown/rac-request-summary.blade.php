@component('mail::message')
# {{ trans('mail.hello') }},

{{ trans('mail.rac_request_scope_match_intro') }}

@if ($project_name)
**{{ trans('general.project') }}:** {{ $project_name }}
@endif

**Requestor:** {{ $requester->display_name }}

**{{ trans('general.requested') }}:** {{ $submitted_at }}

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; width:100%; margin-top:18px; margin-bottom:18px;">
    <thead>
    <tr>
        <th align="left" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368; width:58%;">Model</th>
        <th align="center" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368; width:12%;">{{ trans('general.qty') }}</th>
        <th align="right" style="padding:0 0 10px 12px; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368; width:30%;">Needed By</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lines as $line)
        <tr>
            <td align="left" style="padding:14px 12px 14px 0; border-bottom:1px solid #f1f3f4;">
                <a href="{{ $line['model_show_url'] }}">{{ $line['model_name'] }}</a>
            </td>
            <td align="center" style="padding:14px 12px; border-bottom:1px solid #f1f3f4; white-space:nowrap;">
                {{ $line['requested_quantity'] }}
            </td>
            <td align="right" style="padding:14px 0 14px 12px; border-bottom:1px solid #f1f3f4; white-space:nowrap;">
                {{ $line['needed_by_date'] }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
