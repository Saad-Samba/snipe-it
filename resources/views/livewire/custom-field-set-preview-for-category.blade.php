<div>
    @if ($this->fieldset)
        <div class="form-group">
            <label class="col-md-3 control-label">
                {{ trans('admin/categories/general.fieldset_fields') }}
            </label>
            <div class="col-md-7">
                <div class="panel panel-default" style="margin-bottom: 8px;">
                    <div class="panel-heading">
                        <strong>{{ $this->fieldset->name }}</strong>
                    </div>
                    @if ($this->fieldset->fields->isEmpty())
                        <div class="panel-body text-muted">
                            {{ trans('admin/categories/general.fieldset_has_no_fields') }}
                        </div>
                    @else
                        <ul class="list-group">
                            @foreach ($this->fieldset->fields as $field)
                                <li class="list-group-item" wire:key="category-field-preview-{{ $field->id }}">
                                    <strong>{{ $field->name }}</strong>
                                    @if ($field->pivot->required)
                                        <span class="label label-danger pull-right">
                                            {{ trans('admin/categories/general.field_required') }}
                                        </span>
                                    @else
                                        <span class="label label-default pull-right">
                                            {{ trans('admin/categories/general.field_optional') }}
                                        </span>
                                    @endif
                                    @if ($field->help_text)
                                        <div class="text-muted small">{{ $field->help_text }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <p class="help-block">
                    {{ trans('admin/categories/general.fieldset_preview_help') }}
                </p>
            </div>
        </div>
    @endif

    <script>
        (() => {
            const componentId = @js($this->getId());
            const namespace = `.category-fieldset-preview-${componentId}`;

            const installFieldsetSync = () => {
                const fieldsetSelect = $('#fieldset_id');
                if (! fieldsetSelect.length) {
                    return;
                }

                fieldsetSelect.off(namespace);

                const sync = () => {
                    const component = Livewire.find(componentId);
                    if (component) {
                        component.set('fieldset_id', fieldsetSelect.val());
                    }
                };

                fieldsetSelect.on(`change${namespace} select2:select${namespace} select2:clear${namespace}`, sync);
            };

            if (window.Livewire) {
                installFieldsetSync();
            } else {
                document.addEventListener('livewire:init', installFieldsetSync, { once: true });
            }
        })();
    </script>
</div>
