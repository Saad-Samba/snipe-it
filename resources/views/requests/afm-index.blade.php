@extends('layouts/default')

@section('title')
    AFM Reviews
    @parent
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h2 class="box-title">Requests requiring asset-family review</h2>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Request</th>
                        <th>Status</th>
                        <th>Requestor</th>
                        <th>Project</th>
                        <th>Asset Model</th>
                        <th>Requested</th>
                        <th>Allocated</th>
                        <th>Remaining</th>
                        <th>RAC outcomes</th>
                        <th>Requested for review</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($requests as $request)
                        <tr>
                            <td>#{{ $request->id }}</td>
                            <td>
                                @if ($request->awaitsAfmReview())
                                    <span class="label label-warning">Pending AFM review</span>
                                @else
                                    <span class="label label-success">Procurement confirmed</span>
                                @endif
                            </td>
                            <td>{{ optional($request->user)->display_name ?: '-' }}</td>
                            <td>{{ optional($request->project)->name ?: '-' }}</td>
                            <td>{{ optional($request->requestedItem)->name ?: '-' }}</td>
                            <td>{{ $request->quantity }}</td>
                            <td>{{ $request->allocatedQuantity() }}</td>
                            <td>{{ $request->afm_confirmed_shortfall ?? $request->remainingAllocationQuantity() }}</td>
                            <td>
                                @foreach ($request->coordinatorTargets as $target)
                                    <div>
                                        {{ optional($target->coordinator)->display_name ?: 'Unassigned RAC' }}:
                                        {{ str_replace('_', ' ', $target->resolvedStatus()) }}
                                    </div>
                                @endforeach
                                @if ($request->coordinatorTargets->isEmpty())
                                    No RAC matched
                                @endif
                            </td>
                            <td>{{ optional($request->afm_review_requested_at)?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td>
                                <a class="btn btn-sm btn-primary" href="{{ route('hardware.index', [
                                    'request_id' => $request->id,
                                    'request_bucket' => 'reusable_now',
                                ]) }}">
                                    {{ $request->awaitsAfmReview() ? 'Review' : 'View' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center">No AFM reviews are currently assigned to you.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop
