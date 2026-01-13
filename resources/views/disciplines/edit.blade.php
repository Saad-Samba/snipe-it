@extends('layouts/edit-form', [
    'createText' => trans('admin/disciplines/table.create') ,
    'updateText' => trans('admin/disciplines/table.update'),
    'formAction' => (isset($item->id)) ? route('disciplines.update', ['discipline' => $item->id]) : route('disciplines.store'),
])

{{-- Page content --}}
@section('inputFields')

    @include ('partials.forms.edit.name', ['translated_name' => trans('admin/disciplines/table.name')])

@stop
