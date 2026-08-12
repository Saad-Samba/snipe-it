@component('mail::message')
# Reusable-stock review complete

The RAC review is complete for the following requested items, but the requested quantities were not fully covered.

@component('mail::table')
| Request | Type | Item | Requested | Fulfilled | Remaining |
|:--|:--|:--|--:|--:|--:|
@foreach ($requests as $request)
| #{{ $request->id }} | {{ class_basename($request->requestable_type) === 'License' ? 'License' : 'Model' }} | {{ optional($request->requestedItem)->name ?: '-' }} | {{ $request->quantity }} | {{ $request->allocatedQuantity() }} | {{ $request->remainingAllocationQuantity() }} |
@endforeach
@endcomponent

@if ($afms->isNotEmpty())
Please coordinate with the concerned catalogue managers copied on this email
({{ $afms->pluck('display_name')->join(', ') }})
to determine whether alternative items are acceptable.
@else
Please coordinate with the appropriate catalogue manager to determine whether an alternative item is acceptable.
@endif

If alternatives are agreed, submit new requests for the remaining quantities and reference the corresponding original request numbers shown above.
The reusable-stock review of these original requests is complete.

@component('mail::button', ['url' => $requestsUrl])
View submitted requests
@endcomponent

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
