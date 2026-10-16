@extends('layouts/default')

@section('title')
    Software Models
    @parent
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Software Models</h3>
                    @if (auth()->user()->hasAccess('licenses.create'))
                        <div class="box-tools pull-right">
                            <a class="btn btn-primary btn-sm" href="{{ route('software-models.create') }}">{{ trans('general.create') }}</a>
                        </div>
                    @endif
                </div>
                <div class="box-body">
                    <p class="text-muted">Software Models provide a reusable product definition for licenses. Selecting one fills the license name, category, manufacturer, and (when set) discipline.</p>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ trans('general.name') }}</th>
                                <th>{{ trans('general.category') }}</th>
                                <th>{{ trans('general.manufacturer') }}</th>
                                <th>{{ trans('general.discipline') }}</th>
                                <th>{{ trans('general.status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($softwareModels as $softwareModel)
                                <tr>
                                    <td>{{ $softwareModel->name }}</td>
                                    <td>{{ $softwareModel->category?->name }}</td>
                                    <td>{{ $softwareModel->manufacturer?->name }}</td>
                                    <td>{{ $softwareModel->discipline?->name }}</td>
                                    <td>{{ $softwareModel->active ? trans('general.active') : trans('general.inactive') }}</td>
                                    <td class="text-right">
                                        @if (auth()->user()->hasAccess('licenses.edit'))
                                            <a href="{{ route('software-models.edit', $softwareModel) }}">{{ trans('button.edit') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No Software Models have been created yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $softwareModels->links() }}
                </div>
            </div>
        </div>
    </div>
@stop
