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
          @if (!empty($requestContext))
              <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                  <span>
                      Reviewing request <strong>#{{ $requestContext->id }}</strong>
                      for <strong>{{ $requestContext->name() }}</strong>
                      @if ($requestContext->project)
                          on project <strong>{{ $requestContext->project->name }}</strong>
                      @endif
                  </span>
                  <a href="{{ route('account.requested') }}" class="btn btn-default btn-sm">View all submitted requests</a>
              </div>
          @elseif (!empty($filteredLicense))
              <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                  <span>
                      Showing requests for license:
                      <strong>{{ $filteredLicense->name }}</strong>
                  </span>
                  <a href="{{ route('account.requested') }}" class="btn btn-default btn-sm">View all submitted requests</a>
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
              data-url="{{ route('api.licenses.index', ['status' => e(request('status')), 'license_id' => e(request('license_id'))]) }}"
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
