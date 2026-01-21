@extends('layouts/default')

{{-- Page title --}}
@section('title')
  {{ $discipline->name }}
  @parent
@stop

@section('header_right')
  @can('update', $discipline)
      <a href="{{ route('disciplines.edit', $discipline) }}" class="btn btn-primary pull-right">
          <x-icon type="edit" /> {{ trans('general.edit') }}</a>
  @endcan
@stop

{{-- Page content --}}
@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#asset_tab" data-toggle="tab">
                            <span class="hidden-lg hidden-md">
                                <i class="fas fa-barcode" aria-hidden="true"></i>
                            </span>
                            <span class="hidden-xs hidden-sm">
                                {{ trans('general.assets') }}
                                {!! ($discipline->assets_count > 0) ? '<span class="badge badge-secondary">'.number_format($discipline->assets_count).'</span>' : '' !!}
                            </span>
                        </a>
                    </li>

                    <li>
                        <a href="#licenses_tab" data-toggle="tab">
                            <span class="hidden-lg hidden-md">
                                <i class="far fa-save"></i>
                            </span>
                            <span class="hidden-xs hidden-sm">
                                {{ trans('general.licenses') }}
                                {!! ($discipline->licenses_count > 0) ? '<span class="badge badge-secondary">'.number_format($discipline->licenses_count).'</span>' : '' !!}
                            </span>
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade in active" id="asset_tab">
                        <div class="table table-responsive">
                            @include('partials.asset-bulk-actions')

                            <table
                                data-columns="{{ \App\Presenters\AssetPresenter::dataTableLayout() }}"
                                data-cookie-id-table="disciplineAssetsTable"
                                data-id-table="disciplineAssetsTable"
                                data-side-pagination="server"
                                data-show-columns-search="true"
                                data-sort-order="asc"
                                data-toolbar="#assetsBulkEditToolbar"
                                data-bulk-button-id="#bulkAssetEditButton"
                                data-bulk-form-id="#assetsBulkForm"
                                id="disciplineAssetsTable"
                                class="table table-striped snipe-table"
                                data-url="{{ route('api.assets.index', ['discipline_id' => $discipline->id]) }}"
                                data-export-options='{
                                  "fileName": "export-disciplines-{{ str_slug($discipline->name) }}-assets-{{ date('Y-m-d') }}",
                                  "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                                  }'>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane" id="licenses_tab">
                        <div class="table-responsive">
                            <table
                                data-columns="{{ \App\Presenters\LicensePresenter::dataTableLayout() }}"
                                data-cookie-id-table="disciplineLicensesTable"
                                data-id-table="disciplineLicensesTable"
                                data-side-pagination="server"
                                data-sort-order="asc"
                                id="disciplineLicensesTable"
                                class="table table-striped snipe-table"
                                data-url="{{ route('api.licenses.index', ['discipline_id' => $discipline->id]) }}"
                                data-export-options='{
                                  "fileName": "export-disciplines-{{ str_slug($discipline->name) }}-licenses-{{ date('Y-m-d') }}",
                                  "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                                  }'>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('moar_scripts')
    @include ('partials.bootstrap-table')
@stop
