<div class="form-group{{ $errors->has($fieldname) ? ' has-error' : '' }}">

    <label for="{{ $fieldname }}" class="col-md-3 control-label">{{ $translated_name }}</label>

    <div class="col-md-6">
        <select class="js-data-ajax" data-endpoint="projects" data-placeholder="{{ trans('general.select_project') }}" name="{{ $fieldname }}" style="width: 100%" id="project_select" aria-label="{{ $fieldname }}"{{ (isset($multiple) && ($multiple=='true')) ? " multiple='multiple'" : '' }}>
            @isset ($selected)
                @if (!is_iterable($selected))
                    @php
                        $selected = [$selected];
                    @endphp
                @endif
                @foreach ($selected as $project_id)
                    <option value="{{ $project_id }}" selected="selected" role="option" aria-selected="true">
                        {{ \App\Models\Project::find($project_id)?->name }}
                    </option>
                @endforeach
            @endisset
            @if ($project_id = old($fieldname, (isset($item)) ? $item->{$fieldname} : ''))
                <option value="{{ $project_id }}" selected="selected" role="option" aria-selected="true"  role="option">
                    {{ (\App\Models\Project::find($project_id)) ? \App\Models\Project::find($project_id)->name : '' }}
                </option>
            @endif
        </select>
    </div>


    {!! $errors->first($fieldname, '<div class="col-md-8 col-md-offset-3"><span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}

</div>
