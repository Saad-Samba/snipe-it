@extends('layouts/default')

@section('title0')
@if (isset($requestContext) && $requestContext)
  Request #{{ $requestContext->id }}
@else

  @if ((Request::get('company_id')) && ($company))
    {{ $company->name }}
  @endif



@if (Request::get('status'))
  @if (Request::get('status')=='Pending')
    {{ trans('general.pending') }}
  @elseif (Request::get('status')=='RTD')
    {{ trans('general.ready_to_deploy') }}
  @elseif (Request::get('status')=='Deployed')
    {{ trans('general.deployed') }}
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
          @if (isset($requestContext) && $requestContext && isset($requestReviewSummary))
            <div class="alert alert-info" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
              <div><strong>Project</strong><br>{{ $requestReviewSummary['project'] ?: '-' }}</div>
              <div><strong>Needed By</strong><br>{{ $requestReviewSummary['needed_by_date'] ?: '-' }}</div>
              <div><strong>Total Needed</strong><br>{{ $requestReviewSummary['total_needed'] }}</div>
              <div><strong>Reusable Now</strong><br>{{ $requestReviewSummary['reusable_now'] }}</div>
              <div><strong>Due Back</strong><br>{{ $requestReviewSummary['due_back_before_needed_by'] }}</div>
              <div><strong>Potentially Coverable</strong><br>{{ $requestReviewSummary['potentially_coverable'] }}</div>
              <div><strong>Shortfall</strong><br>{{ $requestReviewSummary['shortfall'] }}</div>
              <div><strong>Booked</strong><br>{{ $requestReviewSummary['booked_count'] }}</div>
              <div><strong>Reserved for RFQ</strong><br>{{ $requestReviewSummary['reserved_count'] }}</div>
            </div>
          @endif
          <div class="row">
            <div class="col-md-12">

                @include('partials.asset-bulk-actions', ['status' => Request::get('status')])
                   
              <table
                data-columns="{{ \App\Presenters\AssetPresenter::dataTableLayout() }}"
                data-cookie-id-table="{{ request()->has('status') ? e(request()->input('status')) : ''  }}assetsListingTable"
                data-id-table="{{ request()->has('status') ? e(request()->input('status')) : ''  }}assetsListingTable"
                data-side-pagination="server"
                data-show-footer="true"
                data-sort-order="asc"
                data-sort-name="name"
                data-search-text="{{ session()->get('search') }}"
                data-show-columns-search="true"
                data-toolbar="#assetsBulkEditToolbar"
                data-bulk-button-id="#bulkAssetEditButton"
                data-bulk-form-id="#assetsBulkForm"
                data-buttons="assetButtons"
                id="{{ request()->has('status') ? e(request()->input('status')) : ''  }}assetsListingTable"
                class="table table-striped snipe-table"
                data-url="{{ route('api.assets.index',
                    array('status' => e(Request::get('status')),
                    'request_id'=>e(Request::get('request_id')),
                    'model_id'=>e(Request::get('model_id')),
                    'category_id'=>e(Request::get('category_id')),
                    'reusable_assets'=>e(Request::get('reusable_assets')),
                    'order_number'=>e(strval(Request::get('order_number'))),
                    'company_id'=>e(Request::get('company_id')),
                    'discipline_id'=>e(Request::get('discipline_id')),
                    'status_id'=>e(Request::get('status_id')))) }}"
                data-export-options='{
                "fileName": "export{{ (Request::has('status')) ? '-'.str_slug(Request::get('status')) : '' }}-assets-{{ date('Y-m-d') }}",
                "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                }'>
              </table>

            </div><!-- /.col -->
          </div><!-- /.row -->
        
      </div><!-- ./box-body -->
    </div><!-- /.box -->
  </div>
</div>
@stop

@section('moar_scripts')
@include('partials.bootstrap-table')

@stop
