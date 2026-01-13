@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('general.disciplines') }}
    @parent
@stop

{{-- Page content --}}
@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-body">
                        <table
                                data-columns="{{ \App\Presenters\DisciplinePresenter::dataTableLayout() }}"
                                data-cookie-id-table="disciplinesTable"
                                data-id-table="disciplinesTable"
                                data-side-pagination="server"
                                data-sort-order="asc"
                                id="disciplinesTable"
                                data-advanced-search="false"
                                data-buttons="disciplineButtons"
                                class="table table-striped snipe-table"
                                data-url="{{ route('api.disciplines.index') }}"
                                data-export-options='{
                              "fileName": "export-disciplines-{{ date('Y-m-d') }}",
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
