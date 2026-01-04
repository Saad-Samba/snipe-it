@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('general.projects') }}
    @parent
@stop

{{-- Page content --}}
@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-body">
                        <table
                                data-columns="{{ \App\Presenters\ProjectPresenter::dataTableLayout() }}"
                                data-cookie-id-table="projectsTable"
                                data-id-table="projectsTable"
                                data-side-pagination="server"
                                data-sort-order="asc"
                                id="projectsTable"
                                data-advanced-search="false"
                                data-buttons="projectButtons"
                                class="table table-striped snipe-table"
                                data-url="{{ route('api.projects.index') }}"
                                data-export-options='{
                              "fileName": "export-projects-{{ date('Y-m-d') }}",
                              "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                              }'>

                        </table>
                </div>
            </div>
        </div>
    </div>

@stop

@section('moar_scripts')
    @include ('partials.bootstrap-table')

@stop
