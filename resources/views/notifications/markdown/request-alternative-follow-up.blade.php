@component('mail::message')
# Reusable-stock review complete

The RAC review for request **#{{ $request->id }}** is complete, but the requested quantity was not fully covered.

**Requested model:** {{ optional($request->requestedItem)->name ?: '-' }}

**Requested quantity:** {{ $request->quantity }}

**Fulfilled quantity:** {{ $fulfilledQuantity }}

**Remaining quantity:** {{ $remainingQuantity }}

@if ($afm)
Please coordinate with **{{ $afm->display_name }}**, copied on this email, to determine whether an alternative model is acceptable.
@else
Please coordinate with the appropriate Asset Family Manager to determine whether an alternative model is acceptable.
@endif

If an alternative is agreed, submit a new request for the remaining quantity and reference original request **#{{ $request->id }}**.
The reusable-stock review of this original request is complete for the requested model.

@component('mail::button', ['url' => $requestsUrl])
View submitted requests
@endcomponent

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
