@extends('layouts/default')

{{-- Page title --}}
@section('title')
     {{ trans('admin/licenses/general.checkout') }}
@parent
@stop

@section('header_right')
    <a href="{{ URL::previous() }}" class="btn btn-primary pull-right">
        {{ trans('general.back') }}</a>
@stop

{{-- Page content --}}
@section('content')
<div class="row">
        <!-- left column -->
    <div class="col-md-8">
        @if (!empty($requestContext))
            <div class="alert alert-info">
                <strong>Coordinator review</strong>
                <p style="margin:8px 0 12px;">
                    @if ($coordinatorTarget && $coordinatorTarget->resolvedStatus() === \App\Models\CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK)
                        You marked this request as having no more reusable seats available in your scope.
                    @else
                        Allocate the reusable seats that are operationally available. When no further seats can be allocated, complete your review below.
                    @endif
                </p>
                @if ($coordinatorTarget && $coordinatorTarget->resolvedStatus() !== \App\Models\CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK)
                    <form method="POST" action="{{ route('licenses.requests.coordinator-resolution', ['license' => $license, 'checkoutRequest' => $requestContext]) }}" style="display:inline-block;">
                        {{ csrf_field() }}
                        <input type="hidden" name="resolution_status" value="{{ \App\Models\CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK }}">
                        <button type="submit" class="btn btn-default">No more reusable seats available</button>
                    </form>
                @endif
            </div>
        @endif
        @if ($license->availCount()->count() > 0)
        <form class="form-horizontal" method="post" action="" autocomplete="off">
            {{csrf_field()}}
            @if (!empty($requestContext))
                <input type="hidden" name="request_id" value="{{ $requestContext->id }}">
            @endif

            <div class="box box-default">
                <div class="box-header with-border">
                    <h2 class="box-title"> {{ $license->name }} ({{ trans('admin/licenses/message.seats_available', ['seat_count' => $license->availCount()->count()]) }})</h2>
                </div>
                <div class="box-body">

                    @if (!empty($requestContext))
                        <div class="alert alert-info">
                            Fulfilling request <strong>#{{ $requestContext->id }}</strong>.
                            @if ($requestContext->requested_for_type && $requestContext->requested_for_id)
                                Requested for <strong>{{ $requestContext->requested_for_display }}</strong>.
                                The checkout target must match this request.
                            @else
                                Select the user or asset that should receive this license seat.
                            @endif
                        </div>
                    @endif


                    <!-- License name -->
                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ trans('admin/hardware/form.name') }}</label>
                        <div class="col-md-9">
                            <p class="form-control-static">{{ $license->name }}</p>
                        </div>
                    </div>

                    @if ($license->company)
                        <!-- company name -->
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{ trans('general.company') }}</label>
                            <div class="col-md-6">
                                <p class="form-control-static">{!! $license->company->present()->formattedNameLink  !!}</p>
                            </div>
                        </div>
                    @endif


                    @if ($license->category)
                        <!-- category name -->
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{ trans('general.category') }}</label>
                            <div class="col-md-6">
                                <p class="form-control-static">{!! $license->category->present()->formattedNameLink  !!}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Serial -->
                    @can('viewKeys', $license)
                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ trans('admin/licenses/form.license_key') }}

                        </label>
                        <div class="col-md-9">
                            <p class="form-control-static">
                                <x-copy-to-clipboard copy_what="license_key" style="white-space: pre-wrap">
                                    <code>{!! nl2br(e($license->serial)) !!}</code>
                                </x-copy-to-clipboard>
                            </p>
                        </div>
                    </div>
                    @endcan

                    @include ('partials.forms.checkout-selector', ['user_select' => 'true','asset_select' => 'true', 'location_select' => 'false'])
                    @include ('partials.forms.edit.user-select', ['translated_name' => trans('general.user'), 'fieldname' => 'assigned_to', 'style' => (session('checkout_to_type') ?: 'user') == 'user' ? '' : 'display: none;'])
                    @include ('partials.forms.edit.asset-select', ['translated_name' => trans('general.select_asset'), 'fieldname' => 'asset_id', 'style' => session('checkout_to_type') == 'asset' ? '' : 'display: none;'])

                    <div class="form-group {{ $errors->has('expected_release_date') ? 'error' : '' }}">
                        <label for="expected_release_date" class="col-md-3 control-label">Expected Release Date</label>
                        <div class="col-md-8">
                            <input
                                type="date"
                                class="form-control"
                                id="expected_release_date"
                                name="expected_release_date"
                                value="{{ old('expected_release_date') }}">
                            {!! $errors->first('expected_release_date', '<span class="alert-msg"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="form-group {{ $errors->has('notes') ? 'error' : '' }}">
                        <label for="note" class="col-md-3 control-label">{{ trans('general.checkout_note') }}</label>
                        <div class="col-md-8">
                            <textarea class="col-md-6 form-control" id="notes" name="notes" rows="5">{{ old('note') }}</textarea>
                            {!! $errors->first('note', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                        </div>
                    </div>
                </div>


                @if ((($license->category) && ($license->category->checkin_email)) || ($snipeSettings->webhook_endpoint!=''))
                    <div class="form-group notification-callout">
                        <div class="col-md-8 col-md-offset-3">
                            <div class="callout callout-info">

                                @if (($license->category) && ($license->category->checkin_email))
                                    <i class="far fa-envelope"></i>
                                    {{ trans('admin/categories/general.checkin_email_notification') }}
                                    <br>
                                @endif

                                @if ($snipeSettings->webhook_endpoint!='')
                                    <i class="fab fa-slack"></i>
                                    {{ trans('general.webhook_msg_note') }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <x-redirect_submit_options
                        index_route="licenses.index"
                        :button_label="trans('general.checkout')"
                        :options="[
                                'index' => trans('admin/hardware/form.redirect_to_all', ['type' => trans('general.licenses')]),
                                'item' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('general.license')]),
                                'target' => trans('admin/hardware/form.redirect_to_checked_out_to'),
                               ]"
                />
            </div> <!-- /.box-->
        </form>
        @else
            <div class="alert alert-warning">There are currently no unassigned seats to allocate. Complete the coordinator review above if no further reuse is possible.</div>
        @endif
    </div> <!-- /.col-md-7-->
</div>

@stop
