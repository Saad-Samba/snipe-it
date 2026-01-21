<div id="{{ (isset($id_divname)) ? $id_divname : 'assetsBulkEditToolbar' }}" style="min-width:400px">
    <form
    method="POST"
    action="{{ route('hardware/bulkedit') }}"
    accept-charset="UTF-8"
    class="form-inline"
    id="{{ (isset($id_formname)) ? $id_formname : 'assetsBulkForm' }}"
>
    @csrf

    {{-- The sort and order will only be used if the cookie is actually empty (like on first-use) --}}
    <input name="sort" type="hidden" value="assets.id">
    <input name="order" type="hidden" value="asc">
    <label for="bulk_actions">
        <span class="sr-only">
            {{ trans('button.bulk_actions') }}
        </span>
    </label>
    @isset($from_user_id)
        <input type="hidden" name="from_user_id" value="{{ $from_user_id }}">
    @endisset
    @isset($allow_transfer)
        <input type="hidden" name="transfer_target_user_id" value="">
    @endisset
    <select name="bulk_actions" class="form-control select2" aria-label="bulk_actions" style="min-width: 350px !important;">
        @if ((isset($status)) && ($status == 'Deleted'))
            @can('delete', \App\Models\Asset::class)
                <option value="restore">{{trans('button.restore')}}</option>
            @endcan
        @else

            @can('update', \App\Models\Asset::class)
                <option value="edit">{{ trans('button.edit') }}</option>
                <option value="maintenance">{{ trans('button.add_maintenance') }}</option>
            @endcan

            @if((!isset($status)) || (($status != 'Deployed') && ($status != 'Archived')))
                @can('checkout', \App\Models\Asset::class)
                    <option value="checkout">{{ trans('general.bulk_checkout') }}</option>
                @endcan
            @endif
            @if (!empty($allow_transfer))
                <option value="transfer">{{ trans('button.transfer_assets') }}</option>
            @endif

            @can('checkin', \App\Models\Asset::class)
                <option value="checkin">{{ trans('admin/hardware/general.bulk_checkin') }}</option>
            @endcan

            @can('delete', \App\Models\Asset::class)
                <option value="delete">{{ trans('button.delete') }}</option>
            @endcan

            <option value="labels" {{$snipeSettings->shortcuts_enabled == 1 ? "accesskey=l" : ''}}>{{ trans_choice('button.generate_labels', 2) }}</option>
        @endif
    </select>

    <button class="btn btn-primary" id="{{ (isset($id_button)) ? $id_button : 'bulkAssetEditButton' }}" disabled>{{ trans('button.go') }}</button>
    </form>
</div>
