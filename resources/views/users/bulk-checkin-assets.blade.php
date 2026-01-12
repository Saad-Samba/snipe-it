@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/hardware/general.bulk_checkin') }}
    @parent
@stop

{{-- Page content --}}
@section('content')

    <style>
        .input-group {
            padding-left: 0px !important;
        }

        .bulk-checkin-tag-display {
            background-color: #f0f0f0;
        }
    </style>

    <div class="row">
        <div class="col-md-7">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h2 class="box-title">{{ trans('admin/hardware/general.bulk_checkin') }}</h2>
                </div>
                <div class="box-body">
                    <form class="form-horizontal" method="post" action="{{ route('users.checkin.assets.all', $user) }}" autocomplete="off">
                        {{ csrf_field() }}
                        <input type="hidden" name="back_url" value="{{ $back_url }}">
                        @foreach ($assets as $asset)
                            <input type="hidden" name="ids[]" value="{{ $asset->id }}">
                        @endforeach

                        <div class="form-group">
                            <label for="asset_tags" class="col-md-3 control-label">{{ trans('general.asset_tag') }}</label>
                            <div class="col-md-9">
                                <textarea class="form-control bulk-checkin-tag-display" id="asset_tags" rows="3" readonly>{{ $asset_tags }}</textarea>
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('status_id') ? 'error' : '' }}">
                            <label for="status_id" class="col-md-3 control-label">
                                {{ trans('admin/hardware/form.status') }}
                            </label>
                            <div class="col-md-7">
                                <x-input.select
                                    name="status_id"
                                    id="status_id"
                                    :options="$statusLabel_list"
                                    style="width:100%"
                                    aria-label="status_id"
                                />
                                {!! $errors->first('status_id', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('note') ? 'error' : '' }}">
                            <label for="note" class="col-md-3 control-label">{{ trans('admin/hardware/form.notes') }}</label>
                            <div class="col-md-8">
                                <textarea class="col-md-6 form-control" id="note" name="note">{{ old('note') }}</textarea>
                                {!! $errors->first('note', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                            </div>
                        </div>

                        <div class="box-footer">
                            <a class="btn btn-link" href="{{ $back_url }}"> {{ trans('button.cancel') }}</a>
                            <button type="submit" class="btn btn-success pull-right"><x-icon type="checkmark" /> {{ trans('general.checkin') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@stop
