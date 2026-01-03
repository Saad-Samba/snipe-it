@extends('layouts/default')

@section('title')
    {{ __('Disciplines') }}
    @parent
@stop

@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Add Discipline') }}</h3>
                </div>
                <form method="POST" action="{{ route('disciplines.store') }}">
                    @csrf
                    <div class="box-body">
                        <div class="form-group {{ $errors->has('name') ? 'has-error' : '' }}">
                            <label for="name">{{ __('Name') }}</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="255">
                            {!! $errors->first('name', '<span class="alert-msg"><i class="fas fa-times"></i> :message</span>') !!}
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Existing Disciplines') }}</h3>
                </div>
                <div class="box-body">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($disciplines as $discipline)
                            <tr>
                                <td>{{ $discipline->name }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('disciplines.destroy', $discipline) }}" style="display:inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-xs"
                                                onclick="return confirm('{{ __('Are you sure?') }}')">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">{{ __('No disciplines yet.') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
