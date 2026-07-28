@php
    $validationField = $validation_name ?? rtrim($fieldname, '[]');
    $selectedValues = old(
        $validationField,
        $selected ?? ((isset($item) && isset($item->{$validationField})) ? $item->{$validationField} : [])
    );
    $selectedValues = is_iterable($selectedValues) ? $selectedValues : [$selectedValues];
@endphp

<div class="form-group{{ $errors->has($validationField) ? ' has-error' : '' }}">

    <label for="{{ $fieldname }}" class="col-md-3 control-label">{{ $translated_name }}</label>

    <div class="col-md-6">
        <select class="js-data-ajax" data-endpoint="disciplines" data-placeholder="{{ trans('general.select_discipline') }}" name="{{ $fieldname }}" style="width: 100%" id="discipline_select" aria-label="{{ $fieldname }}"{{ (isset($multiple) && ($multiple=='true')) ? " multiple='multiple'" : '' }}>
            @foreach ($selectedValues as $discipline_id)
                @if ($discipline_id)
                    <option value="{{ $discipline_id }}" selected="selected" role="option" aria-selected="true">
                        {{ \App\Models\Discipline::find($discipline_id)?->name }}
                    </option>
                @endif
            @endforeach
        </select>
    </div>


    {!! $errors->first($validationField, '<div class="col-md-8 col-md-offset-3"><span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}

</div>
