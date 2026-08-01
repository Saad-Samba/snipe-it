@extends('layouts/edit-form', [
    'createText' => trans('admin/categories/general.create') ,
    'updateText' => trans('admin/categories/general.update'),
    'helpPosition'  => 'right',
    'helpText' => trans('help.categories'),
    'topSubmit'  => 'true',
    'formAction' => (isset($item->id)) ? route('categories.update', ['category' => $item->id]) : route('categories.store'),
])

@section('inputFields')

    <!-- Name -->
    <x-form-row
            :label="trans('general.name')"
            :$item
            name="name"
    />


<!-- Type -->
<div class="form-group {{ $errors->has('category_type') ? ' has-error' : '' }}">
    <label for="category_type" class="col-md-3 control-label">{{ trans('general.type') }}</label>
    <div class="col-md-7 required">
        <x-input.select
            name="category_type"
            :options="$category_types"
            :selected="old('category_type', $item->category_type)"
            :disabled="$item->category_type!='' || $item->itemCount() > 0"
            style="min-width:350px"
            aria-label="category_type"
        />
        {!! $errors->first('category_type', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
    <div class="col-md-7 col-md-offset-3">
        <p class="help-block">{!! trans('admin/categories/message.update.cannot_change_category_type') !!} </p>
    </div>
</div>

<div class="form-group {{ $errors->has('fieldset_id') ? ' has-error' : '' }}">
    <label for="fieldset_id" class="col-md-3 control-label">{{ trans('admin/models/general.fieldset') }}</label>
    <div class="col-md-7">
        <x-input.select
            name="fieldset_id"
            :options="\App\Helpers\Helper::customFieldsetList()"
            :selected="old('fieldset_id', $item->fieldset_id)"
            style="min-width:350px"
            aria-label="fieldset_id"
        />
        {!! $errors->first('fieldset_id', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
    <div class="col-md-7 col-md-offset-3">
        <p class="help-block">Used as the default fieldset for asset models in this category. A model can still override it.</p>
    </div>
</div>

@if (auth()->user()->isSuperUser() || auth()->user()->isAdmin())
    @include ('partials.forms.edit.user-select', [
        'translated_name' => 'Category Manager',
        'fieldname' => 'manager_id',
        'field_id' => 'category_manager_select',
        'container_id' => 'category_manager',
    ])
@elseif ($item->manager)
    <div class="form-group">
        <label class="col-md-3 control-label">Category Manager</label>
        <div class="col-md-7">
            <p class="form-control-static">{{ $item->manager->display_name }}</p>
        </div>
    </div>
@endif

<div class="form-group">
    <div class="col-md-9 col-md-offset-3">
        <label class="form-control">
            <input
                type="checkbox"
                name="checkin_email"
                value="1"
                @checked(old('checkin_email', $item->checkin_email))
                aria-label="checkin_email"
            />
            {{ trans('admin/categories/general.email_to_user_upon_checkin_and_checkout') }}
        </label>
    </div>
</div>

@include ('partials.forms.edit.image-upload', ['image_path' => app('categories_upload_path')])

<div class="form-group{!! $errors->has('notes') ? ' has-error' : '' !!}">
    <label for="notes" class="col-md-3 control-label">{{ trans('general.notes') }}</label>
    <div class="col-md-8">
        <x-input.textarea
                name="notes"
                id="notes"
                :value="old('notes', $item->notes)"
                placeholder="{{ trans('general.placeholders.notes') }}"
                aria-label="notes"
                rows="5"
        />
        {!! $errors->first('notes', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>



    <fieldset name="color-preferences">
        <x-form-legend help_text="{{ trans('general.tag_color_help') }}">
            {{ trans('general.tag_color') }}
        </x-form-legend>
        <!--  color -->
        <div class="form-group {{ $errors->has('tag_color') ? 'error' : '' }}">
            <label for="tag_color" class="col-md-3 control-label">
                {{ trans('general.tag_color') }}
            </label>
            <div class="col-md-9">
                <x-input.colorpicker :item="$item" id="color" :value="old('color', ($item->color ?? '#f4f4f4'))" name="tag_color" id="tag_color" />
                {!! $errors->first('tag_color', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
            </div>
        </div>
    </fieldset>


@stop
