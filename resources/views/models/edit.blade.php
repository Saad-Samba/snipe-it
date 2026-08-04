@extends('layouts/edit-form', [
    'createText' => trans('admin/models/table.create') ,
    'updateText' => trans('admin/models/table.update'),
    'topSubmit' => true,
    'helpPosition' => 'right',
    'helpText' => trans('admin/models/general.about_models_text'),
    'formAction' => (isset($item->id)) ? route('models.update', ['model' => $item->id]) : route('models.store'),
])

{{-- Page content --}}
@section('inputFields')
@include ('partials.forms.edit.name', ['translated_name' => trans('admin/models/table.name'), 'required' => 'true'])
<div id="category_id" class="form-group{{ $errors->has('category_id') ? ' has-error' : '' }}">
    <label for="category_id" class="col-md-3 control-label">{{ trans('admin/categories/general.category_name') }}</label>

    <div class="col-md-7">
        <select class="select2" name="category_id" id="category_id" style="width: 100%" required aria-label="category_id">
            @foreach ($availableCategories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $item->category_id) === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-1 col-sm-1 text-left">
        @can('create', \App\Models\Category::class)
            <a href='{{ route('modal.show',['type' => 'category', 'category_type' => 'asset' ]) }}' data-toggle="modal" data-target="#createModal" data-select='category_id' class="btn btn-sm btn-primary">{{ trans('button.new') }}</a>
        @endcan
    </div>

    {!! $errors->first('category_id', '<div class="col-md-8 col-md-offset-3"><span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}
    {!! $errors->first('category_type', '<div class="col-md-8 col-md-offset-3"><span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}
</div>
@include ('partials.forms.edit.manufacturer-select', ['translated_name' => trans('general.manufacturer'), 'fieldname' => 'manufacturer_id'])
@include ('partials.forms.edit.model_number')
<div class="form-group {{ $errors->has('reference_price') ? ' has-error' : '' }}">
    <label for="reference_price" class="col-md-3 control-label">Reference Price</label>
    <div class="col-md-7">
        <input class="form-control" type="number" name="reference_price" min="0.00" max="99999999999999999.99" step="0.01" aria-label="reference_price" id="reference_price" value="{{ old('reference_price', $item->reference_price) }}" />
        {!! $errors->first('reference_price', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<div class="form-group">
    <label for="obsolete" class="col-md-3 control-label">
        {{ trans('admin/models/table.obsolete') }}
    </label>

    <div class="col-md-9">
        <div class="form-inline" style="display: flex; align-items: center; gap: 8px;">
            <input type="checkbox" name="obsolete" value="1" @checked(old('obsolete', $item->obsolete)) id="obsolete" aria-label="obsolete" />
            <a
                    href="#"
                    data-tooltip="true"
                    title="{{ trans('admin/models/general.obsolete_help') }}"
                    style="display: inline-flex; align-items: center;"
            >
                <x-icon type="info-circle" />
                <span class="sr-only">{{ trans('admin/models/general.obsolete_help') }}</span>
            </a>
        </div>
    </div>
</div>

<!-- require serial boolean -->
<div class="form-group">
    <label for="require_serial" class="col-md-3 control-label">
        {{ trans('admin/hardware/general.require_serial') }}
    </label>

    <div class="col-md-9">
        <div class="form-inline" style="display: flex; align-items: center; gap: 8px;">
            <input type="checkbox" name="require_serial" value="1" @checked(old('require_serial', $item->require_serial)) id="require_serial" aria-label="require_serial" />
            <a
                    href="#"
                    data-tooltip="true"
                    title="{{ trans('admin/hardware/general.require_serial_help') }}"
                    style="display: inline-flex; align-items: center;"
            >
                <x-icon type="info-circle" />
                <span class="sr-only">{{ trans('admin/hardware/general.require_serial_help') }}</span>
            </a>
        </div>
    </div>
</div>
<!-- EOL -->

<div class="form-group {{ $errors->has('eol') ? ' has-error' : '' }}">
    <label for="eol" class="col-md-3 control-label">{{ trans('general.eol') }}</label>
    <div class="col-md-3 col-sm-4 col-xs-7">
        <div style="display: flex; align-items: center; gap: 8px;">
            <div class="input-group" style="flex: 1;">
                <input class="form-control" type="text" name="eol" id="eol" value="{{ old('eol', isset($item->eol)) ? $item->eol : ''  }}" />
                <span class="input-group-addon">
                    {{ trans('general.months') }}
                </span>
            </div>
            <a
                    href="#"
                    data-tooltip="true"
                    title="{{ trans('admin/models/general.eol_help') }}"
                    style="display: inline-flex; align-items: center;"
            >
                <x-icon type="info-circle" />
                <span class="sr-only">{{ trans('admin/models/general.eol_help') }}</span>
            </a>
        </div>
    </div>
    <div class="col-md-9 col-md-offset-3">
        {!! $errors->first('eol', '<span class="alert-msg" aria-hidden="true"><br><i class="fas fa-times"></i> :message</span>') !!}
    </div>
</div>

<!-- Custom Fieldset -->
<!-- If $item->id is null we are cloning the model and we need the $model_id variable -->
@livewire('custom-field-set-default-values-for-model', ["model_id" => $item->id ?? $model_id ?? null])

@include ('partials.forms.edit.notes')
@include ('partials.forms.edit.image-upload', ['image_path' => app('models_upload_path')])


@stop
