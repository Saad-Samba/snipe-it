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
                        <span class="badge" id="modelRequestCartCount">{{ count(session('model_request_cart', [])) }}</span>
                    </button>
                </div>
                <h2 class="box-title">Request Models</h2>
                <p class="help-block" style="margin-bottom:0;">
                    Select a reusable model, add the required quantity and destination scope, then submit the cart for one project and needed-by date.
                </p>
            </div>

            <div class="box-body">
                @if ($models->isEmpty())
                    <div class="alert alert-info fade in" style="margin-bottom:0;">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No models with reusable inventory are currently available.
                    </div>
                @else
                    <div class="table-responsive">
                        <table
                            id="requestableModelsTable"
                            class="table table-striped snipe-table"
                            data-id-table="requestableModelsTable"
                            data-cookie-id-table="requestableModelsTable"
                            data-search="true"
                            data-pagination="true">
                            <thead>
                                <tr>
                                    <th data-sortable="false">{{ trans('general.image') }}</th>
                                    <th data-sortable="true">{{ trans('admin/hardware/table.asset_model') }}</th>
                                    <th data-sortable="true">{{ trans('general.category') }}</th>
                                    <th data-sortable="true">{{ trans('admin/models/table.modelnumber') }}</th>
                                    <th data-sortable="true">Reusable Assets</th>
                                    <th data-sortable="true">Reference Price</th>
                                    <th data-sortable="false" class="text-right">{{ trans('table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($models as $requestableModel)
                                    <tr>
                                        <td>
                                            @if ($requestableModel->image && $requestableModel->getImageUrl())
                                                <img
                                                    src="{{ $requestableModel->getImageUrl() }}"
                                                    alt=""
                                                    style="max-height: {{ $snipeSettings->thumbnail_max_h }}px; width:auto;"
                                                    class="img-responsive">
                                            @endif
                                        </td>
                                        <td>{{ $requestableModel->name }}</td>
                                        <td>{{ $requestableModel->category?->name }}</td>
                                        <td>{{ $requestableModel->model_number }}</td>
                                        <td>{{ $requestableModel->reusable_assets_count }}</td>
                                        <td>
                                            @if ($requestableModel->reference_price !== null)
                                                {{ App\Helpers\Helper::formatCurrencyOutput($requestableModel->reference_price) }}
                                            @else
                                                &mdash;
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm add-model-to-request-cart"
                                                data-model-id="{{ $requestableModel->id }}"
                                                data-model-name="{{ $requestableModel->name }}">
                                                <i class="fas fa-cart-plus" aria-hidden="true"></i>
                                                Add to Request
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="add-model-to-request-cart-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="add-model-to-request-cart-form">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">Add Model to Request</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" id="add-model-to-request-cart-error" style="display:none;"></div>
                    <input type="hidden" id="add-model-to-request-cart-model-id">

                    <div class="form-group">
                        <label>Model</label>
                        <p class="form-control-static" id="add-model-to-request-cart-model-name"></p>
                    </div>

                    <div class="form-group">
                        <label for="add-model-to-request-cart-quantity">Quantity</label>
                        <input type="number" min="1" value="1" class="form-control" id="add-model-to-request-cart-quantity" required>
                    </div>

                    <div class="form-group">
                        <label for="add-model-to-request-cart-discipline">Discipline</label>
                        <select class="form-control" id="add-model-to-request-cart-discipline" required>
                            <option value="">{{ trans('general.select_discipline') }}</option>
                            @foreach (App\Models\Discipline::orderBy('name')->get(['id', 'name']) as $discipline)
                                <option value="{{ $discipline->id }}">{{ $discipline->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="add-model-to-request-cart-company">{{ trans('general.company') }}</label>
                        <select class="form-control" id="add-model-to-request-cart-company" required>
                            <option value="">{{ trans('general.select_company') }}</option>
                            @foreach (App\Models\Company::orderBy('name')->get(['id', 'name']) as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('button.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-cart-plus" aria-hidden="true"></i>
                        Add to Cart
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table', [
        'exportFile' => 'requestable-models-export',
        'search' => true,
        'clientSearch' => true,
    ])

    <script nonce="{{ csrf_token() }}">
        $(document)
            .off('click.add-model-request', '.add-model-to-request-cart')
            .on('click.add-model-request', '.add-model-to-request-cart', function () {
                var button = $(this);

                $('#add-model-to-request-cart-model-id').val(button.data('model-id'));
                $('#add-model-to-request-cart-model-name').text(button.data('model-name'));
                $('#add-model-to-request-cart-quantity').val(1);
                $('#add-model-to-request-cart-discipline').val('');
                $('#add-model-to-request-cart-company').val('');
                $('#add-model-to-request-cart-error').hide().text('');
                $('#add-model-to-request-cart-modal').modal('show');
            });

        $('#add-model-to-request-cart-form').on('submit', function (event) {
            event.preventDefault();

            var modelId = parseInt($('#add-model-to-request-cart-model-id').val(), 10);
            var quantity = parseInt($('#add-model-to-request-cart-quantity').val(), 10);
            var disciplineId = parseInt($('#add-model-to-request-cart-discipline').val(), 10);
            var companyId = parseInt($('#add-model-to-request-cart-company').val(), 10);

            if (!modelId || !quantity || !disciplineId || !companyId) {
                $('#add-model-to-request-cart-error').text('Quantity, discipline, and company are required.').show();
                return;
            }

            addLinesToRequestCart([{
                model_id: modelId,
                quantity: quantity,
                discipline_id: disciplineId,
                company_id: companyId
            }], false).done(function () {
                $('#add-model-to-request-cart-modal').modal('hide');
            });
        });
    </script>
@stop
