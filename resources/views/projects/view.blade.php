@extends('layouts/default')

{{-- Page title --}}
@section('title')
  {{ $project->name }}
  @parent
@stop

@section('header_right')
  @can('update', $project)
      <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary pull-right">
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
                                {!! ($project->assets_count > 0) ? '<span class="badge badge-secondary">'.number_format($project->assets_count).'</span>' : '' !!}
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
                                {!! ($project->licenses_count > 0) ? '<span class="badge badge-secondary">'.number_format($project->licenses_count).'</span>' : '' !!}
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
                                data-cookie-id-table="projectAssetsTable"
                                data-id-table="projectAssetsTable"
                                data-side-pagination="server"
                                data-show-columns-search="true"
                                data-sort-order="asc"
                                data-toolbar="#assetsBulkEditToolbar"
                                data-bulk-button-id="#bulkAssetEditButton"
                                data-bulk-form-id="#assetsBulkForm"
                                id="projectAssetsTable"
                                class="table table-striped snipe-table"
                                data-url="{{ route('api.assets.index', ['project_id' => $project->id]) }}"
                                data-export-options='{
                                  "fileName": "export-projects-{{ str_slug($project->name) }}-assets-{{ date('Y-m-d') }}",
                                  "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                                  }'>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane" id="licenses_tab">
                        <div class="table-responsive">
                            <table
                                data-columns="{{ \App\Presenters\LicensePresenter::dataTableLayout() }}"
                                data-cookie-id-table="projectLicensesTable"
                                data-id-table="projectLicensesTable"
                                data-side-pagination="server"
                                data-sort-order="asc"
                                id="projectLicensesTable"
                                class="table table-striped snipe-table"
                                data-url="{{ route('api.licenses.index', ['project_id' => $project->id]) }}"
                                data-export-options='{
                                  "fileName": "export-projects-{{ str_slug($project->name) }}-licenses-{{ date('Y-m-d') }}",
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
