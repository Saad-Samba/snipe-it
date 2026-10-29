@extends('layouts/default')

@section('title0')
    Request Models
@stop

@section('title')
    @yield('title0') @parent
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right">
                    <a href="{{ route('requests.index') }}" class="btn btn-default">
                        <i class="fas fa-list" aria-hidden="true"></i>
                        Submitted Requests
                    </a>
                    <button type="button" class="btn btn-primary" id="modelRequestCartButton">
                        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                        Request Cart
                        <x-new-feature-label />
                        <span class="badge" id="modelRequestCartCount">{{ count(session('model_request_cart', [])) }}</span>
                    </button>
                </div>
                <h2 class="box-title">Request Models</h2>
            </div>

            <div class="box-body">
                <p class="text-muted">Request the model and quantity your project needs. Reuse and procurement options are assessed after submission.</p>
                <div id="requestableModelsToolbar" class="form-inline">
                    <form id="requestableCatalogueFilters" method="GET" action="{{ route('requestable-assets') }}" style="display:inline-block;margin-right:8px;">
                        <input type="hidden" name="search" value="{{ $search }}">
                        <label class="sr-only" for="catalogue-category">Category</label>
                        <select id="catalogue-category" name="category_id" class="form-control">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ (string) $categoryId === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @if ($search !== '' || $categoryId)
                            <a href="{{ route('requestable-assets') }}" class="btn btn-link">Clear filters</a>
                        @endif
                    </form>
                    <form id="requestableModelsBulkForm" style="display:inline-block;">
                        <button type="submit" class="btn btn-primary" id="requestableModelsBulkAddButton" disabled>
                            <i class="fas fa-cart-plus" aria-hidden="true"></i>
                            Add Selected to Cart
                        </button>
                    </form>
                </div>

                    <div class="table-responsive">
                        <table
                            id="requestableModelsTable"
                            class="table table-striped snipe-table"
                            data-id-table="requestableModelsTable"
                            data-cookie-id-table="requestableModelsDemandTable"
                            data-bulk-button-id="#requestableModelsBulkAddButton"
                            data-bulk-form-id="#requestableModelsBulkForm"
                            data-click-to-select="false"
                            data-toolbar="#requestableModelsToolbar"
                            data-search="true"
                            data-search-text="{{ $search }}"
                            data-server-search-url="{{ route('requestable-assets', array_filter(['category_id' => $categoryId])) }}"
                            data-advanced-search="false"
                            data-cookie="false"
                            data-addrbar="false"
                            data-empty-message="No catalogue models match your search."
                            data-pagination="false">
                            <thead>
                                <tr>
                                    <th data-field="state" data-checkbox="true"></th>
                                    <th data-field="id" data-visible="false">ID</th>
                                    <th data-sortable="false">{{ trans('general.image') }}</th>
                                    <th data-sortable="true">{{ trans('admin/hardware/table.asset_model') }}</th>
                                    <th data-sortable="true">{{ trans('general.category') }}</th>
                                    <th data-sortable="true">{{ trans('admin/models/table.modelnumber') }}</th>
                                    <th data-sortable="false">Specifications</th>
                                    <th data-sortable="false">Total Needed <x-new-feature-label /></th>
                                    <th data-sortable="false">Discipline <x-new-feature-label /></th>
                                    <th data-sortable="false">Destination Site <x-new-feature-label /></th>
                                    <th data-sortable="false" class="text-right">{{ trans('table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($models as $requestableModel)
                                    <tr>
                                        <td></td>
                                        <td>{{ $requestableModel->id }}</td>
                                        <td>
                                            @if ($requestableModel->image && $requestableModel->getImageUrl())
                                                <img
                                                    src="{{ $requestableModel->getImageUrl() }}"
                                                    alt=""
                                                    style="max-height: {{ $snipeSettings->thumbnail_max_h }}px; width:auto;"
                                                    class="img-responsive">
                                            @endif
                                        </td>
                                        <td>
                                            {{ $requestableModel->name }}
                                            @if ($requestableModel->obsolete)<span class="label label-warning">Obsolete</span>@endif
                                        </td>
                                        <td>{{ $requestableModel->category?->name }}</td>
                                        <td>{{ $requestableModel->model_number }}</td>
                                        <td>
                                            @php
                                                $specificationFields = ($requestableModel->fieldset?->fields ?? collect())->filter(fn ($field) => $field->show_in_requestable_list && ! $field->field_encrypted);
                                                $modelDefaults = $requestableModel->defaultValues->keyBy('id');
                                            @endphp
                                            @if ($specificationFields->isNotEmpty())
                                                <details>
                                                    <summary>View specifications</summary>
                                                    <dl>
                                                        @foreach ($specificationFields as $field)
                                                            <dt>{{ $field->name }}</dt>
                                                            <dd>{{ $modelDefaults->get($field->id)?->pivot?->default_value ?? '—' }}</dd>
                                                        @endforeach
                                                    </dl>
                                                </details>
                                            @else
                                                <span class="text-muted">No specifications recorded</span>
                                            @endif
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                min="1"
                                                value="1"
                                                id="model-booking-quantity-{{ $requestableModel->id }}"
                                                class="form-control input-sm model-request-inline-control"
                                                style="width:70px;">
                                        </td>
                                        <td>
                                            <select
                                                id="model-booking-discipline-{{ $requestableModel->id }}"
                                                class="form-control input-sm model-request-inline-control"
                                                style="width:160px;">
                                                <option value="">{{ trans('general.select_discipline') }}</option>
                                                @foreach ($disciplines as $discipline)
                                                    <option value="{{ $discipline->id }}">{{ $discipline->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select
                                                id="model-booking-company-{{ $requestableModel->id }}"
                                                class="form-control input-sm model-request-inline-control"
                                                style="width:160px;">
                                                <option value="">{{ trans('general.select_company') }}</option>
                                                @foreach ($companies as $company)
                                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-right">
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm add-model-to-request-cart"
                                                data-model-id="{{ $requestableModel->id }}">
                                                <i class="fas fa-cart-plus" aria-hidden="true"></i>
                                                Add to Request
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted">Showing {{ $models->firstItem() ?? 0 }}–{{ $models->lastItem() ?? 0 }} of {{ $models->total() }} models. Selection applies to this page; your cart is retained across pages.</p>
                    {{ $models->links() }}
            </div>
        </div>
    </div>
</div>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table', [
        'exportFile' => 'requestable-models-export',
        'search' => true,
        'clientSearch' => false,
    ])

    <script nonce="{{ csrf_token() }}">
        $('#catalogue-category').on('change', function () {
            this.form.submit();
        });

        function requestLineForModel(modelId) {
            var quantity = parseInt($('#model-booking-quantity-' + modelId).val(), 10);
            var disciplineId = parseInt($('#model-booking-discipline-' + modelId).val(), 10);
            var companyId = parseInt($('#model-booking-company-' + modelId).val(), 10);

            if (!quantity) {
                window.alert('Enter a total needed quantity for each model.');
                return null;
            }

            if (!disciplineId) {
                window.alert('Select a discipline for each model.');
                return null;
            }

            if (!companyId) {
                window.alert('Select a site for each model.');
                return null;
            }

            return {
                model_id: modelId,
                quantity: quantity,
                discipline_id: disciplineId,
                company_id: companyId
            };
        }

        $(document)
            .off('click.add-model-request', '.add-model-to-request-cart')
            .on('click.add-model-request', '.add-model-to-request-cart', function () {
                var line = requestLineForModel(parseInt($(this).data('model-id'), 10));

                if (line) {
                    addLinesToRequestCart([line], false);
                }
            });

        $('#requestableModelsBulkForm').on('submit', function (event) {
            event.preventDefault();

            var rows = $('#requestableModelsTable').bootstrapTable('getSelections');

            if (!rows.length) {
                window.alert('Select at least one model.');
                return false;
            }

            var lines = [];

            for (var i = 0; i < rows.length; i++) {
                var line = requestLineForModel(parseInt(rows[i].id, 10));

                if (!line) {
                    return false;
                }

                lines.push(line);
            }

            addLinesToRequestCart(lines, false);
            return false;
        });
    </script>
@stop
