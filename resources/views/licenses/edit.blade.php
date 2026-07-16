@extends('layouts/edit-form', [
    'createText' => trans('admin/licenses/form.create'),
    'updateText' => trans('admin/licenses/form.update'),
    'topSubmit' => true,
    'formAction' => ($item->id) ? route('licenses.update', ['license' => $item->id]) : route('licenses.store'),
     'index_route' => 'licenses.index',
    'options' => [
                'back' => trans('admin/hardware/form.redirect_to_type',['type' => trans('general.previous_page')]),
                'index' => trans('admin/hardware/form.redirect_to_all', ['type' => 'licenses']),
                'item' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('general.license')]),
               ]
])

{{-- Page content --}}
@section('inputFields')
@include ('partials.forms.edit.name', ['translated_name' => trans('admin/licenses/form.name')])
@include ('partials.forms.edit.category-select', ['translated_name' => trans('admin/categories/general.category_name'), 'fieldname' => 'category_id', 'required' => 'true', 'category_type' => 'license'])



<!-- Seats -->
<div class="form-group {{ $errors->has('seats') ? ' has-error' : '' }}">
    <label for="seats" class="col-md-3 control-label">{{ trans('admin/licenses/form.seats') }}</label>
    <div class="col-md-7 col-sm-12">
        <div class="col-md-12" style="padding-left:0px">
            <input class="form-control" type="text" name="seats" id="seats" value="{{ old('seats', $item->seats) }}" minlength="1" required style="width: 97px;">
        </div>
    </div>
    {!! $errors->first('seats', '<div class="col-md-8 col-md-offset-3"><span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}
</div>
@include ('partials.forms.edit.minimum_quantity')

<!-- Serial-->
@can('viewKeys', $item)
    <div class="form-group {{ $errors->has('serial') ? ' has-error' : '' }}">
        <label for="serial" class="col-md-3 control-label">{{ trans('admin/licenses/form.license_key') }}</label>
        <div class="col-md-7">
            <textarea class="form-control" type="text" name="serial" id="serial" rows="5"{{  (Helper::checkIfRequired($item, 'serial')) ? ' required' : '' }}>{{ old('serial', $item->serial) }}</textarea>
            {!! $errors->first('serial', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>
@endcan

<div class="form-group {{ $errors->has('serial_number') ? ' has-error' : '' }}">
    <label for="serial_number" class="col-md-3 control-label">{{ trans('general.serial_number') }}</label>
    <div class="col-md-7">
        <input class="form-control" type="text" name="serial_number" id="serial_number" value="{{ old('serial_number', $item->serial_number) }}" maxlength="191" />
        {!! $errors->first('serial_number', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.company-select', ['translated_name' => trans('general.company'), 'fieldname' => 'company_id'])
@include ('partials.forms.edit.project-select', ['translated_name' => trans('general.project'), 'fieldname' => 'project_id'])
@include ('partials.forms.edit.discipline-select', ['translated_name' => trans('general.discipline'), 'fieldname' => 'discipline_id'])
@include ('partials.forms.edit.manufacturer-select', ['translated_name' => trans('general.manufacturer'), 'fieldname' => 'manufacturer_id',])

<!-- Licensed to name -->
<div class="form-group {{ $errors->has('license_name') ? ' has-error' : '' }}">
    <label for="license_name" class="col-md-3 control-label">{{ trans('admin/licenses/form.to_name') }}</label>
    <div class="col-md-7">
        <input class="form-control" type="text" name="license_name" id="license_name" value="{{ old('license_name', $item->license_name) }}" />
        {!! $errors->first('license_name', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Licensed to email -->
<div class="form-group {{ $errors->has('license_email') ? ' has-error' : '' }}">
    <label for="license_email" class="col-md-3 control-label">{{ trans('admin/licenses/form.to_email') }}</label>
    <div class="col-md-7">
        <input class="form-control" type="email" name="license_email" id="license_email" value="{{ old('license_email', $item->license_email) }}" />
        {!! $errors->first('license_email', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Reassignable -->
<div class="form-group {{ $errors->has('reassignable') ? ' has-error' : '' }}">
    <div class="col-md-3 control-label">
        <strong>{{ trans('admin/licenses/form.reassignable') }}</strong>
    </div>
    <div class="col-md-7">
        <label class="form-control">
            <input type="checkbox" name="reassignable" value="1" aria-label="reassignable" @checked(old('reassignable', $item->id ? $item->reassignable : '1'))>
        {{ trans('general.yes') }}
        </label>
    </div>
</div>


@include ('partials.forms.edit.supplier-select', ['translated_name' => trans('general.supplier'), 'fieldname' => 'supplier_id'])
@include ('partials.forms.edit.order_number')
@include ('partials.forms.edit.purchase_cost')
@include ('partials.forms.edit.datepicker', ['translated_name' => trans('general.purchase_date'),'fieldname' => 'purchase_date'])

<!-- Perpetual -->
<div class="form-group {{ $errors->has('perpetual') ? ' has-error' : '' }}">
    <div class="col-md-3 control-label">
        <strong>{{ trans('admin/licenses/form.perpetual') }}</strong>
    </div>
    <div class="col-md-9">
        <div class="col-md-4" style="padding-left:0px;">
            <label class="form-control">
                <input type="checkbox" name="perpetual" id="perpetual" value="1" aria-label="perpetual" @checked(old('perpetual', $item->perpetual))>
                {{ trans('general.yes') }}
            </label>
        </div>
        <div class="col-md-7" style="margin-left: -15px; padding-top: 8px;">
            <a href="#" data-tooltip="true" title="{{ trans('admin/licenses/form.perpetual_help') }}">
                <x-icon type="info-circle" />
                <span class="sr-only">{{ trans('admin/licenses/form.perpetual_help') }}</span>
            </a>
        </div>
        <div class="col-md-12">
            {!! $errors->first('perpetual', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>
</div>

<!-- Expiration Date -->
<div class="form-group {{ $errors->has('expiration_date') ? ' has-error' : '' }}">
    <label for="expiration_date" class="col-md-3 control-label">{{ trans('admin/licenses/form.expiration') }}</label>

    <div class="col-md-9">
        <div class="col-md-4" id="expiration_date_wrapper" style="padding-left:0px;">
            <div class="input-group date" id="expiration_date_picker" data-provide="datepicker" data-date-format="yyyy-mm-dd" data-autoclose="true" data-date-clear-btn="true">
                <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="expiration_date" id="expiration_date" value="{{ old('expiration_date', ($item->expiration_date) ? $item->expiration_date->format('Y-m-d') : '') }}" maxlength="10" @required(! old('perpetual', $item->perpetual))>
                <span class="input-group-addon"><x-icon type="calendar" /></span>
            </div>
        </div>
        <div class="col-md-7" style="margin-left: -15px; padding-top: 8px;">
            <a href="#" data-tooltip="true" title="{{ trans('admin/licenses/form.expiration_help') }}">
                <x-icon type="info-circle" />
                <span class="sr-only">{{ trans('admin/licenses/form.expiration_help') }}</span>
            </a>
        </div>
        <div class="col-md-12">
            {!! $errors->first('expiration_date', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>

</div>

<!-- Termination Date -->
<div class="form-group {{ $errors->has('termination_date') ? ' has-error' : '' }}">
    <label for="termination_date" class="col-md-3 control-label">{{ trans('admin/licenses/form.termination_date') }}</label>

    <div class="col-md-9">
        <div class="col-md-4" style="padding-left:0px;">
            <div class="input-group date" data-provide="datepicker" data-date-format="yyyy-mm-dd" data-autoclose="true" data-date-clear-btn="true">
                <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="termination_date" id="termination_date" value="{{ old('termination_date', ($item->termination_date) ? $item->termination_date->format('Y-m-d') : '') }}" maxlength="10">
                <span class="input-group-addon"><x-icon type="calendar" /></span>
            </div>
        </div>
        <div class="col-md-7" style="margin-left: -15px; padding-top: 8px;">
            <a href="#" data-tooltip="true" title="{{ trans('admin/licenses/form.termination_date_help') }}">
                <x-icon type="info-circle" />
                <span class="sr-only">{{ trans('admin/licenses/form.termination_date_help') }}</span>
            </a>
        </div>
        <div class="col-md-12">
            {!! $errors->first('termination_date', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>
</div>

{{-- @TODO How does this differ from Order #? --}}
<!-- Purchase Order -->
<div class="form-group {{ $errors->has('purchase_order') ? ' has-error' : '' }}">
    <label for="purchase_order" class="col-md-3 control-label">{{ trans('admin/licenses/form.purchase_order') }}</label>
    <div class="col-md-3 text-right">
        <input class="form-control" type="text" name="purchase_order" id="purchase_order" value="{{ old('purchase_order', $item->purchase_order) }}" maxlength="191" />
        {!! $errors->first('purchase_order', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.depreciation')

<!-- Maintained -->
<div class="form-group {{ $errors->has('maintained') ? ' has-error' : '' }}">
    <div class="col-md-3 control-label"><strong>{{ trans('admin/licenses/form.maintained') }}</strong></div>
    <div class="col-md-7">
        <label class="form-control">
            <input type="checkbox" name="maintained" value="1" aria-label="maintained" @checked(old('maintained', $item->maintained))>
        {{ trans('general.yes') }}
        </label>
    </div>
</div>

@include ('partials.forms.edit.notes')

@stop


@section('moar_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var perpetualCheckbox = document.getElementById('perpetual');
        var expirationInput = document.getElementById('expiration_date');
        var expirationPicker = document.getElementById('expiration_date_picker');
        var expirationWrapper = document.getElementById('expiration_date_wrapper');

        if (!perpetualCheckbox || !expirationInput || !expirationPicker || !expirationWrapper) {
            return;
        }

        function syncPerpetualState() {
            var isPerpetual = perpetualCheckbox.checked;
            expirationInput.disabled = isPerpetual;
            expirationInput.required = !isPerpetual;
            expirationPicker.classList.toggle('text-muted', isPerpetual);
            expirationPicker.style.opacity = isPerpetual ? '0.65' : '1';

            if (isPerpetual) {
                expirationInput.value = '';
            }
        }

        perpetualCheckbox.addEventListener('change', syncPerpetualState);
        syncPerpetualState();
    });
</script>
@stop
