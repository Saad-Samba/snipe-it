<div id="discipline" class="form-group{{ $errors->has($fieldname) ? ' has-error' : '' }}">

    <label for="{{ $fieldname }}" class="col-md-3 control-label">{{ $translated_name }}</label>

    <div class="col-md-6">
        <select class="js-data-ajax" data-endpoint="disciplines" data-placeholder="{{ trans('general.select_discipline') }}" name="{{ $fieldname }}" style="width: 100%" id="discipline_select" aria-label="{{ $fieldname }}"{{ (isset($multiple) && ($multiple=='true')) ? " multiple='multiple'" : '' }}>
            @isset ($selected)
                @if (!is_iterable($selected))
                    @php
                        $selected = [$selected];
                    @endphp
                @endif
                @foreach ($selected as $discipline_id)
                    <option value="{{ $discipline_id }}" selected="selected" role="option" aria-selected="true">
                        {{ \App\Models\Discipline::find($discipline_id)->name }}
                    </option>
                @endforeach
            @endisset
            @if ($discipline_id = old($fieldname, (isset($item)) ? $item->{$fieldname} : ''))
                <option value="{{ $discipline_id }}" selected="selected" role="option" aria-selected="true" role="option">
                    {{ (\App\Models\Discipline::find($discipline_id)) ? \App\Models\Discipline::find($discipline_id)->name : '' }}
                </option>
            @endif
        </select>
    </div>


    {!! $errors->first($fieldname, '<div class="col-md-8 col-md-offset-3"><span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}

</div>
