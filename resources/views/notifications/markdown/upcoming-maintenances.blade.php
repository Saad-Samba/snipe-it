@component('mail::message')
# {{ trans_choice('mail.upcoming-maintenances', $total, ['count' => $total, 'threshold' => $threshold]) }}

{{ trans('mail.upcoming-maintenance_click') }}

@component('mail::table')
| {{ trans('general.name') }} | {{ trans('admin/maintenances/form.start_date') }} | {{ trans('admin/hardware/form.tag') }} | {{ trans('admin/maintenances/table.asset_name') }} | {{ trans('general.supplier') }} |
| :--- | :--- | :--- | :--- | :--- |
@foreach ($maintenances as $maintenance)
| {{ $maintenance->name }} | {{ \App\Helpers\Helper::getFormattedDateObject($maintenance->start_date, 'date', false) }} | {{ $maintenance->asset?->asset_tag }} | {{ $maintenance->asset?->name }} | {{ $maintenance->supplier?->name }} |
@endforeach
@endcomponent

@component('mail::button', ['url' => route('maintenances.index')])
{{ trans('general.maintenance') }}
@endcomponent

@endcomponent
