@extends('layouts/default')

{{-- Page title --}}
@section('title')

    {{ $discipline->name }}
    {{ trans('general.discipline') }}
    @parent
@stop

@section('header_right')
    <a href="{{ route('disciplines.edit', ['discipline' => $discipline->id]) }}" class="btn btn-sm btn-primary pull-right">{{ trans('admin/disciplines/table.update') }} </a>
@stop

{{-- Page content --}}
@section('content')

    <div class="row">
        <div class="col-md-12">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#assets" data-toggle="tab">
                            {{ trans('general.assets') }}
                            {!! ($discipline->assets()->AssetsForShow()->count() > 0 ) ? '<span class="badge badge-secondary">'.number_format($discipline->assets()->AssetsForShow()->count()).'</span>' : '' !!}
                        </a>
                    </li>
                    <li>
                        <a href="#licenses" data-toggle="tab">
                            {{ trans('general.licenses') }}
                            {!! ($discipline->licenses()->count() > 0 ) ? '<span class="badge badge-secondary">'.number_format($discipline->licenses()->count()).'</span>' : '' !!}
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade in active" id="assets">
                        @include('partials.asset-bulk-actions')
                        <div class="table table-responsive">
                            <table
                                    data-columns="{{ \App\Presenters\AssetPresenter::dataTableLayout() }}"
                                    data-show-columns-search="true"
                                    data-cookie-id-table="disciplineAssetsTable"
                                    data-id-table="disciplineAssetsTable"
                                    data-toolbar="#assetsBulkEditToolbar"
                                    data-bulk-button-id="#bulkAssetEditButton"
                                    data-bulk-form-id="#assetsBulkForm"
                                    data-side-pagination="server"
                                    data-sort-order="asc"
                                    id="disciplineAssetsTable"
                                    class="table table-striped snipe-table"
                                    data-url="{{ route('api.assets.index', ['discipline_id' => $discipline->id, 'itemtype' => 'assets']) }}"
                                    data-export-options='{
                              "fileName": "export-disciplines-{{ str_slug($discipline->name) }}-assets-{{ date('Y-m-d') }}",
                              "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                              }'>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="licenses">
                        <table
                                data-columns="{{ \App\Presenters\LicensePresenter::dataTableLayout() }}"
                                data-cookie-id-table="disciplineLicensesTable"
                                data-id-table="disciplineLicensesTable"
                                data-show-footer="true"
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

@stop

@section('moar_scripts')
    @include ('partials.bootstrap-table')
@stop
