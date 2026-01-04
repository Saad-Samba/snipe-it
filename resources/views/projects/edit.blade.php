@extends('layouts/edit-form', [
    'createText' => trans('admin/projects/form.create'),
    'updateText' => trans('admin/projects/form.update'),
    'helpPosition' => 'right',
    'formAction' => ($item->id) ? route('projects.update', $item) : route('projects.store'),
    'index_route' => 'projects.index',
    'topSubmit' => true,
    'options' => [
                'back' => trans('admin/hardware/form.redirect_to_type',['type' => trans('general.previous_page')]),
                'index' => trans('admin/hardware/form.redirect_to_all', ['type' => trans('general.projects')]),
                'item' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('general.project')]),
               ]
])

{{-- Page content --}}
@section('inputFields')
    @include ('partials.forms.edit.name', ['translated_name' => trans('general.name')])
    @include ('partials.forms.edit.company-select', ['translated_name' => trans('general.company'), 'fieldname' => 'company_id'])
    @include ('partials.forms.edit.notes')
@stop
