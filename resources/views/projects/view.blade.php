@extends('layouts/default')

{{-- Page title --}}
@section('title')
  {{ $project->name }}
  @parent
@stop

@section('header_right')
  @can('update', $project)
      <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary pull-right">
          <x-icon type="edit" /> {{ trans('general.edit') }}</a>
  @endcan
@stop

{{-- Page content --}}
@section('content')
  <div class="row">
    <div class="col-md-9">

      <div class="box box-default">
        <div class="box-body">

          <div class="row">
            <div class="col-md-3"><strong>{{ trans('general.name') }}</strong></div>
            <div class="col-md-9">{{ $project->name }}</div>
          </div>

          @if ($project->company)
            <div class="row">
              <div class="col-md-3"><strong>{{ trans('general.company') }}</strong></div>
              <div class="col-md-9">{!! $project->company->present()->formattedNameLink !!}</div>
            </div>
          @endif

          @if ($project->notes)
            <div class="row">
              <div class="col-md-3"><strong>{{ trans('general.notes') }}</strong></div>
              <div class="col-md-9">{!! \App\Helpers\Helper::parseEscapedMarkedownInline($project->notes) !!}</div>
            </div>
          @endif

          <div class="row">
            <div class="col-md-3"><strong>{{ trans('general.assets') }}</strong></div>
            <div class="col-md-9">{{ number_format($project->assets()->count()) }}</div>
          </div>

          <div class="row">
            <div class="col-md-3"><strong>{{ trans('general.licenses') }}</strong></div>
            <div class="col-md-9">{{ number_format($project->licenses()->count()) }}</div>
          </div>

        </div>
      </div>

    </div>
  </div>
@stop
