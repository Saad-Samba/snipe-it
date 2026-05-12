<div id="modelsBulkEditToolbar">
    <form
        method="POST"
        action="{{route('models.bulkedit.index')}}"
        accept-charset="UTF-8"
        class="form-inline"
        id="modelsBulkForm"
    >
    @csrf
    @if (request('status')!='deleted')
        @can('delete', \App\Models\AssetModel::class)
            <div id="models-toolbar">
                <label for="bulk_actions" class="sr-only">{{ trans('general.bulk_actions') }}</label>
                <select name="bulk_actions" class="form-control select2" style="width: 200px;" aria-label="bulk_actions">
                    <option value="edit">{{ trans('general.bulk_edit') }}</option>
                    @if (auth()->check() && auth()->user()->hasAccess('models.request'))
                    <option value="request">Add Selected to Cart</option>
                    @endif
                    <option value="delete">{{ trans('general.bulk_delete') }}</option>
                </select>
                <button class="btn btn-primary" id="bulkModelsEditButton" disabled>{{ trans('button.go') }}</button>
                @if (auth()->check() && auth()->user()->hasAccess('models.request'))
                    <button type="button" class="btn btn-default" id="modelRequestCartButton" style="margin-left:8px;">
                        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                        Cart
                        <span class="badge" id="modelRequestCartCount">{{ count(session('model_request_cart', [])) }}</span>
                    </button>
                @endif
            </div>
        @endcan
    @endif
    </form>
</div>
