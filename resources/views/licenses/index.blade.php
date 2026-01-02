@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('admin/licenses/general.software_licenses') }}
@parent
@stop


{{-- Page content --}}
@section('content')


<div class="row">
  <div class="col-md-12">
    <div class="box">
      <div class="box-body">

          @if($hasDashboardFilters ?? false)
            <div class="alert alert-info" role="alert">
              <strong>{{ __('Filters from dashboard applied') }}</strong>
              <div class="small" style="margin-top: 4px;">
                  @if(request('company_id'))
                    <span class="label label-default">{{ __('Company') }}: {{ optional(\App\Models\Company::find(request('company_id')))->name }}</span>
                  @endif
                  @if(request('discipline'))
                    <span class="label label-default">{{ __('Discipline') }}: {{ request('discipline') }}</span>
                  @endif
                  <a class="btn btn-xs btn-default" style="margin-left: 6px;" href="{{ route('licenses.index') }}">
                    {{ __('Clear filters') }}
                  </a>
              </div>
            </div>
          @endif

          <table
              data-columns="{{ \App\Presenters\LicensePresenter::dataTableLayout() }}"
              data-cookie-id-table="licensesTable"
              data-side-pagination="server"
              data-footer-style="footerStyle"
              data-show-footer="true"
              data-sort-order="asc"
              data-sort-name="name"
              id="licensesTable"
              data-buttons="licenseButtons"
              class="table table-striped snipe-table"
              data-url="{{ route('api.licenses.index', ['status' => e(request('status')), 'company_id' => e(request('company_id')), 'discipline' => e(request('discipline'))]) }}"
              data-export-options='{
            "fileName": "export-licenses-{{ date('Y-m-d') }}",
            "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
            }'>
          </table>

      </div><!-- /.box-body -->

      <div class="box-footer clearfix">
      </div>
    </div><!-- /.box -->
  </div>
</div>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table')

@stop
