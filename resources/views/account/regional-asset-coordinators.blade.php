@extends('layouts/default')

@section('title')
    {{ trans('general.regional_asset_coordinators') }}
    @parent
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h2 class="box-title">
                        {{ trans('general.regional_asset_coordinators') }}
                        <x-new-feature-label />
                    </h2>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table
                                data-cookie-id-table="regionalAssetCoordinatorsTable"
                                data-side-pagination="server"
                                data-sort-name="company"
                                data-sort-order="asc"
                                id="regionalAssetCoordinatorsTable"
                                class="table table-striped snipe-table"
                                data-url="{{ route('api.regional-asset-coordinators.index') }}">
                            <thead>
                                <tr>
                                    <th data-field="coordinator" data-sortable="true">
                                        {{ trans('general.coordinator') }}
                                    </th>
                                    <th data-field="company" data-sortable="true">
                                        {{ trans('general.company') }}
                                    </th>
                                    <th data-field="discipline" data-sortable="true">
                                        {{ trans('general.discipline') }}
                                    </th>
                                    <th data-field="email" data-sortable="true" data-formatter="emailFormatter">
                                        {{ trans('general.email') }}
                                    </th>
                                    <th data-field="phone" data-sortable="true" data-formatter="phoneFormatter">
                                        {{ trans('general.phone') }}
                                    </th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table', ['search' => true])
@stop
