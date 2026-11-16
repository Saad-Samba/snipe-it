@component('mail::message')
# Reusable-stock review complete

The RAC review is complete for the following requested models, but the requested quantities were not fully covered.

@component('mail::table')
| Request | Model | Requested | Fulfilled | Remaining |
|:--|:--|--:|--:|--:|
@foreach ($requests as $request)
| #{{ $request->id }} | {{ optional($request->requestedItem)->name ?: '-' }} | {{ $request->quantity }} | {{ $request->allocatedQuantity() }} | {{ $request->remainingAllocationQuantity() }} |
@endforeach
@endcomponent

@if ($afms->isNotEmpty())
Please coordinate with the concerned Asset Family Managers copied on this email
({{ $afms->pluck('display_name')->join(', ') }})
to determine whether alternative models are acceptable.
@else
Please coordinate with the appropriate Asset Family Manager to determine whether alternative models are acceptable.
@endif

If alternatives are agreed, submit new requests for the remaining quantities and reference the corresponding original request numbers shown above.
The reusable-stock review of these original requests is complete.

@component('mail::button', ['url' => $requestsUrl])
View submitted requests
@endcomponent

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
