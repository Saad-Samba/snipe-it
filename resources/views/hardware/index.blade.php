@extends('layouts/default')

@section('title0')

  @if ((Request::get('company_id')) && ($company))
    {{ $company->name }}
  @endif



@if (Request::get('status'))
  @if (Request::get('status')=='Pending')
    {{ trans('general.pending') }}
  @elseif (Request::get('status')=='RTD')
    {{ trans('general.unassigned') }}
  @elseif (Request::get('status')=='Deployed')
    {{ trans('general.assigned') }}
  @elseif (Request::get('status')=='Undeployable')
    {{ trans('general.undeployable') }}
  @elseif (Request::get('status')=='Deployable')
    {{ trans('general.deployed') }}
  @elseif (Request::get('status')=='Requestable')
    {{ trans('admin/hardware/general.requestable') }}
  @elseif (Request::get('status')=='Archived')
    {{ trans('general.archived') }}
  @elseif (Request::get('status')=='Deleted')
    {{ ucfirst(trans('general.deleted')) }}
  @elseif (Request::get('status')=='byod')
    {{ strtoupper(trans('general.byod')) }}
  @endif
@else
{{ trans('general.all') }}
@endif
{{ trans('general.assets') }}

  @if (Request::has('order_number'))
    : Order #{{ strval(Request::get('order_number')) }}
  @endif
@stop

{{-- Page title --}}
@section('title')
@yield('title0')  @parent
@stop


{{-- Page content --}}
@section('content')



<div class="row">
  <div class="col-md-12">
    <div class="box">
      <div class="box-body">
       
          <div class="row">
            <div class="col-md-12">

                @include('partials.asset-bulk-actions', ['status' => Request::get('status')])

                @include('partials.assets-ag-grid', [
                    'apiUrl' => route('api.assets.index', [
                        'status' => e(Request::get('status')),
                        'assignment' => e(Request::get('assignment')),
                        'model_obsolete' => e(Request::get('model_obsolete')),
                        'order_number' => e(strval(Request::get('order_number'))),
                        'company_id' => e(Request::get('company_id')),
                        'status_id' => e(Request::get('status_id')),
                    ]),
                    'apiToken' => $agGridApiToken ?? null,
                ])

            </div><!-- /.col -->
          </div><!-- /.row -->
        
      </div><!-- ./box-body -->
    </div><!-- /.box -->
  </div>
</div>
@stop

@section('moar_scripts')


@stop
