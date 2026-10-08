@extends('layouts/default')

@section('title0')
@if (isset($requestContext) && $requestContext)
  Request #{{ $requestContext->id }}
@elseif (($filterModel ?? null) && request()->boolean('reusable_assets'))
  Reusable Assets — {{ $filterModel->name }}
@else

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
@endif
@stop

{{-- Page title --}}
@section('title')
@yield('title0')  @parent
@stop


{{-- Page content --}}
@section('content')

@php
    $coordinatorTarget = null;
    if (isset($requestContext) && $requestContext && auth()->check()) {
        $coordinatorTarget = $requestContext->coordinatorTargets->firstWhere('user_id', auth()->id());
    }
@endphp

<div class="row">
  <div class="col-md-12">
    <div class="box">
      <div class="box-body">
          @php
              $activeTableFilters = [];

              if ($filterModel ?? null) {
                  $activeTableFilters[] = [
                      'label' => 'Model: '.$filterModel->name,
                      'remove_url' => route('hardware.index', request()->except('model_id')),
                  ];
              } elseif (($filterCategory ?? null) && request()->filled('category_id')) {
                  $activeTableFilters[] = [
                      'label' => 'Category: '.$filterCategory->name,
                      'remove_url' => route('hardware.index', request()->except('category_id')),
                  ];
              }

              if (request()->boolean('reusable_assets')) {
                  $activeTableFilters[] = [
                      'label' => 'Availability: Reusable',
                      'remove_url' => route('hardware.index', request()->except('reusable_assets')),
                  ];
              }

              $clearFiltersUrl = route('hardware.index');
              $backToContextUrl = ($filterCategory ?? null)
                  ? route('models.index', ['category_id' => $filterCategory->id, 'available_models' => 1])
                  : null;
              $backToContextLabel = ($filterCategory ?? null) ? 'Back to available models' : null;
          @endphp

          @include('partials.table-filter-context')

          @if ($coordinatorTarget)
            <div class="alert alert-info">
                <strong>Coordinator review</strong>
                <p style="margin:8px 0 12px;">
                    @if ($coordinatorTarget->resolvedStatus() === \App\Models\CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK)
                        You marked this request as having no more reusable stock available in your scope. Reminder emails are paused unless you later allocate more assets.
                    @elseif ($coordinatorTarget->resolvedStatus() === \App\Models\CheckoutRequestCoordinator::RESOLUTION_IN_PROGRESS)
                        You have already started allocating assets for this request. Reminders will keep coming until the remaining quantity is handled or you mark that no more reusable stock is available.
                    @elseif ($coordinatorTarget->resolvedStatus() === \App\Models\CheckoutRequestCoordinator::RESOLUTION_COMPLETED)
                        This request is fully covered from your allocation work. No further reminder emails will be sent.
                    @else
                        Opening this request does not stop reminders by itself. Reminders stop only after the remaining quantity is handled or you mark that no more reusable stock is available.
                    @endif
                </p>

                @if ($requestContext->remainingAllocationQuantity() > 0 && $coordinatorTarget->resolvedStatus() !== \App\Models\CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK)
                    <form method="POST" action="{{ route('hardware.requests.coordinator-resolution', $requestContext) }}" style="display:inline-block;">
                        @csrf
                        <input type="hidden" name="resolution_status" value="{{ \App\Models\CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK }}">
                        <input type="hidden" name="request_bucket" value="{{ request()->input('request_bucket', 'reusable_now') }}">
                        <button type="submit" class="btn btn-default">Mark no more reusable stock available</button>
                    </form>
                @endif
            </div>
          @endif

          <div class="row">
            <div class="col-md-12">
                @php
                    $requestTableCookieId = null;
                    if (Request::filled('request_id')) {
                        $requestTableCookieId = 'request-'.e(Request::get('request_id'));
                        if (Request::filled('request_bucket')) {
                            $requestTableCookieId .= '-'.e(Request::get('request_bucket'));
                        }
                        $requestTableCookieId .= '-assetsListingTable';
                    }
                @endphp

                @include('partials.asset-bulk-actions', ['status' => Request::get('status')])
                   
              <table
                data-columns="{{ \App\Presenters\AssetPresenter::dataTableLayout(Request::get('request_bucket') === 'reusable_now') }}"
                data-cookie-id-table="{{ $requestTableCookieId ?: (request()->has('status') ? e(request()->input('status')) : '').'assetsListingTable' }}"
                data-id-table="{{ $requestTableCookieId ?: (request()->has('status') ? e(request()->input('status')) : '').'assetsListingTable' }}"
                data-side-pagination="server"
                data-export-data-type="all"
                data-show-footer="true"
                data-sort-order="asc"
                data-sort-name="name"
                data-search-text="{{ Request::filled('request_id') ? '' : session()->get('search') }}"
                data-show-columns-search="true"
                data-toolbar="#assetsBulkEditToolbar"
                data-bulk-button-id="#bulkAssetEditButton"
                data-bulk-form-id="#assetsBulkForm"
                data-buttons="assetButtons"
                id="{{ $requestTableCookieId ?: (request()->has('status') ? e(request()->input('status')) : '').'assetsListingTable' }}"
                class="table table-striped snipe-table"
                data-url="{{ route('api.assets.index',
                    array('status' => e(Request::get('status')),
                    'request_id'=>e(Request::get('request_id')),
                    'request_bucket'=>e(Request::get('request_bucket')),
                    'model_id'=>e(Request::get('model_id')),
                    'category_id'=>e(Request::get('category_id')),
                    'reusable_assets'=>e(Request::get('reusable_assets')),
                    'assignment'=>e(Request::get('assignment')),
                    'model_obsolete'=>e(Request::get('model_obsolete')),
                    'order_number'=>e(strval(Request::get('order_number'))),
                    'company_id'=>e(Request::get('company_id')),
                    'project_id'=>e(Request::get('project_id')),
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
