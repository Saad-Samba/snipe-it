@component('mail::message')
# AFM review required

Regional handling is complete for request #{{ $request->id }}, and {{ $remainingQuantity }} requested {{ \Illuminate\Support\Str::plural('item', $remainingQuantity) }} remain unallocated.

**Requestor:** {{ optional($request->user)->display_name ?: '-' }}

**Project:** {{ optional($request->project)->name ?: '-' }}

**Model:** {{ optional($request->requestedItem)->name ?: '-' }}

**Destination company:** {{ optional($request->company)->name ?: '-' }}

**Discipline:** {{ optional($request->requestedDiscipline)->name ?: '-' }}

Review the RAC outcomes and confirm whether the remaining quantity may proceed to procurement.

@component('mail::button', ['url' => $reviewUrl])
Review remaining need
@endcomponent

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}
@endcomponent
