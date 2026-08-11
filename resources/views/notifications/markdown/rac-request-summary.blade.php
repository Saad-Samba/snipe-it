@component('mail::message')
# {{ trans('mail.hello') }},

{{ trans($is_reminder ? 'mail.rac_request_scope_match_reminder_intro' : ($is_update ? 'mail.rac_request_scope_match_update_intro' : 'mail.rac_request_scope_match_intro')) }}

@if ($project_name)
**{{ trans('general.project') }}:** {{ $project_name }}
@endif

@if ($requester)
**Requestor:** {{ $requester->display_name }}
@endif

**{{ trans($is_update ? 'mail.rac_request_updated_at' : 'general.requested') }}:** {{ $submitted_at }}

{{ trans($is_reminder ? 'mail.rac_request_scope_match_reminder_action' : ($is_update ? 'mail.rac_request_scope_match_update_action' : 'mail.rac_request_scope_match_action')) }}

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; width:100%; margin-top:18px; margin-bottom:18px;">
    <thead>
    <tr>
        <th align="left" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368; width:38%;">Inventory</th>
        <th align="left" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368; width:18%;">Requested For</th>
        <th align="left" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368; width:18%;">{{ trans('general.company') }}</th>
        <th align="left" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368; width:20%;">Inventory Discipline(s)</th>
        <th align="center" style="padding:0 12px 10px 0; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368; width:10%;">{{ trans('general.qty') }}</th>
        <th align="right" style="padding:0 0 10px 12px; border-bottom:1px solid #e8eaed; font-weight:700; color:#5f6368; width:14%;">Needed By</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lines as $line)
        <tr>
            <td align="left" style="padding:14px 12px 14px 0; border-bottom:1px solid #f1f3f4;">
                <a href="{{ $line['model_show_url'] }}">{{ $line['model_name'] }}</a>
            </td>
            <td align="left" style="padding:14px 12px 14px 0; border-bottom:1px solid #f1f3f4;">
                {{ $line['requested_for_display'] ?? '-' }}
            </td>
            <td align="left" style="padding:14px 12px 14px 0; border-bottom:1px solid #f1f3f4; white-space:nowrap;">
                {{ $line['company_name'] }}
            </td>
            <td align="left" style="padding:14px 12px 14px 0; border-bottom:1px solid #f1f3f4;">
                {{ implode(', ', $line['inventory_discipline_names'] ?? []) }}
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

@if (!empty($review_url))
@component('mail::button', ['url' => $review_url])
{{ $review_label }}
@endcomponent
@endif

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
