@extends('layouts/edit-form', [
    'createText' => trans('admin/disciplines/form.create'),
    'updateText' => trans('admin/disciplines/form.update'),
    'helpPosition' => 'right',
    'formAction' => ($item->id) ? route('disciplines.update', $item) : route('disciplines.store'),
    'index_route' => 'disciplines.index',
    'topSubmit' => true,
    'options' => [
                'back' => trans('admin/hardware/form.redirect_to_type',['type' => trans('general.previous_page')]),
                'index' => trans('admin/hardware/form.redirect_to_all', ['type' => trans('general.disciplines')]),
                'item' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('general.discipline')]),
               ]
])

{{-- Page content --}}
@section('inputFields')
    @include ('partials.forms.edit.name', ['translated_name' => trans('general.name')])
    @include ('partials.forms.edit.notes')
@stop
