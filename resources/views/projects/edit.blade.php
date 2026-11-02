@extends('layouts/edit-form', [
    'createText' => trans('admin/projects/form.create'),
    'updateText' => trans('admin/projects/form.update'),
    'helpPosition' => 'right',
    'formAction' => ($item->id) ? route('projects.update', $item) : route('projects.store'),
    'index_route' => 'projects.index',
    'topSubmit' => true,
    'options' => [
                'back' => trans('admin/hardware/form.redirect_to_type',['type' => trans('general.previous_page')]),
                'index' => trans('admin/hardware/form.redirect_to_all', ['type' => trans('general.projects')]),
                'item' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('general.project')]),
               ]
])

{{-- Page content --}}
@section('inputFields')
    @include ('partials.forms.edit.name', ['translated_name' => trans('general.name')])
    <div class="form-group {{ $errors->has('is_rfq') || $errors->has('rfq_needed_by_date') ? 'has-error' : '' }}">
        <div class="col-sm-3 control-label">RFQ project</div>
        <div class="col-md-7">
            <input type="hidden" name="is_rfq" value="0">
            <label class="form-control" for="is_rfq" style="font-weight:normal;">
                <input type="checkbox" name="is_rfq" id="is_rfq" value="1" {{ old('is_rfq', $item->is_rfq) ? 'checked="checked"' : '' }}>
                This project is in an RFQ.
            </label>
            {!! $errors->first('is_rfq', '<span class="alert-msg">:message</span>') !!}
        </div>
    </div>
    <div class="form-group {{ $errors->has('rfq_needed_by_date') ? 'has-error' : '' }}" id="rfq-needed-by-date-group">
        <label class="col-sm-3 control-label" for="rfq_needed_by_date">RFQ Needed By</label>
        <div class="col-md-7">
            <input type="date" class="form-control" name="rfq_needed_by_date" id="rfq_needed_by_date" value="{{ old('rfq_needed_by_date', optional($item->rfq_needed_by_date)->format('Y-m-d')) }}">
            <span class="help-block">All model requests submitted against this RFQ project will use this date.</span>
            {!! $errors->first('rfq_needed_by_date', '<span class="alert-msg">:message</span>') !!}
        </div>
    </div>
    @include ('partials.forms.edit.notes')
@stop

@section('moar_scripts')
    <script nonce="{{ csrf_token() }}">
        $(function () {
            function toggleRfqNeededByDate() {
                var isRfq = $('#is_rfq').is(':checked');
                $('#rfq-needed-by-date-group').toggle(isRfq);
                $('#rfq_needed_by_date').prop('required', isRfq);
            }

            $('#is_rfq').on('change', toggleRfqNeededByDate);
            toggleRfqNeededByDate();
        });
    </script>
@stop
