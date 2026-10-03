@extends('layouts/edit-form', [
    'createText' => 'Create Software Model',
    'updateText' => 'Update Software Model',
    'formAction' => $item->id ? route('software-models.update', $item) : route('software-models.store'),
    'index_route' => 'software-models.index',
])

@section('inputFields')
    <x-form-row label="Software Model" :item="$item" name="name" required="true" help_text="The canonical product name used by linked licenses." />

    <div class="form-group {{ $errors->has('category_id') ? ' has-error' : '' }}">
        <label for="category_id" class="col-md-3 control-label">{{ trans('general.category') }} *</label>
        <div class="col-md-7">
            {{ Form::select('category_id', $categories, old('category_id', $item->category_id), ['class' => 'form-control select2', 'placeholder' => trans('general.select_category'), 'required' => true]) }}
            {!! $errors->first('category_id', '<span class="alert-msg">:message</span>') !!}
        </div>
    </div>

    <div class="form-group {{ $errors->has('manufacturer_id') ? ' has-error' : '' }}">
        <label for="manufacturer_id" class="col-md-3 control-label">{{ trans('general.manufacturer') }}</label>
        <div class="col-md-7">{{ Form::select('manufacturer_id', $manufacturers, old('manufacturer_id', $item->manufacturer_id), ['class' => 'form-control select2', 'placeholder' => trans('general.select_manufacturer')]) }}</div>
    </div>

    <div class="form-group {{ $errors->has('discipline_id') ? ' has-error' : '' }}">
        <label for="discipline_id" class="col-md-3 control-label">{{ trans('general.discipline') }}</label>
        <div class="col-md-7">{{ Form::select('discipline_id', $disciplines, old('discipline_id', $item->discipline_id), ['class' => 'form-control select2', 'placeholder' => trans('general.select_discipline')]) }}</div>
    </div>

    <div class="form-group">
        <div class="col-md-7 col-md-offset-3"><label class="form-control"><input type="checkbox" name="active" value="1" @checked(old('active', $item->id ? $item->active : true))> {{ trans('general.active') }}</label></div>
    </div>

    <x-form-row :label="trans('general.notes')" :item="$item" name="notes" type="textarea" />
@stop
