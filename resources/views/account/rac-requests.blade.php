@extends('layouts/default')

@section('title')
    {{ trans('general.rac_requests') }}
    @parent
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h2 class="box-title">{{ trans('general.rac_requests') }}</h2>
                </div>
                <div class="box-body">
                    <ul class="nav nav-pills" style="margin-bottom: 20px;">
                        <li class="{{ $status === 'open' ? 'active' : '' }}">
                            <a href="{{ route('account.rac-requests.index', ['status' => 'open']) }}">
                                Open <span class="badge">{{ $openCount }}</span>
                            </a>
                        </li>
                        <li class="{{ $status === 'completed' ? 'active' : '' }}">
                            <a href="{{ route('account.rac-requests.index', ['status' => 'completed']) }}">
                                Completed <span class="badge">{{ $completedCount }}</span>
                            </a>
                        </li>
                        <li class="{{ $status === 'all' ? 'active' : '' }}">
                            <a href="{{ route('account.rac-requests.index', ['status' => 'all']) }}">
                                All
                            </a>
                        </li>
                    </ul>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Request</th>
                                <th>Model</th>
                                <th>Project</th>
                                <th>Requestor</th>
                                <th>Company</th>
                                <th>Discipline</th>
                                <th>Requested</th>
                                <th>Remaining</th>
                                <th>Needed By</th>
                                <th>Status</th>
                                <th class="text-right">{{ trans('button.actions') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($targets as $target)
                                @php
                                    $checkoutRequest = $target->checkoutRequest;
                                    $resolvedStatus = $target->resolvedStatus();
                                    $statusLabels = [
                                        \App\Models\CheckoutRequestCoordinator::RESOLUTION_PENDING => ['Pending', 'label-warning'],
                                        \App\Models\CheckoutRequestCoordinator::RESOLUTION_IN_PROGRESS => ['In progress', 'label-info'],
                                        \App\Models\CheckoutRequestCoordinator::RESOLUTION_COMPLETED => ['Completed', 'label-success'],
                                        \App\Models\CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK => ['No more stock', 'label-default'],
                                    ];
                                    [$statusLabel, $statusClass] = $statusLabels[$resolvedStatus] ?? [ucwords(str_replace('_', ' ', $resolvedStatus)), 'label-default'];
                                @endphp
                                <tr>
                                    <td>#{{ $checkoutRequest->id }}</td>
                                    <td>{{ optional($checkoutRequest->requestedItem)->name ?: $checkoutRequest->name() }}</td>
                                    <td>{{ optional($checkoutRequest->project)->name ?: '-' }}</td>
                                    <td>{{ optional($checkoutRequest->user)->display_name ?: '-' }}</td>
                                    <td>{{ optional($checkoutRequest->company)->name ?: optional($target->company)->name ?: '-' }}</td>
                                    <td>{{ optional($checkoutRequest->requestedDiscipline)->name ?: optional($target->discipline)->name ?: '-' }}</td>
                                    <td>{{ (int) $checkoutRequest->quantity }}</td>
                                    <td>{{ $checkoutRequest->remainingAllocationQuantity() }}</td>
                                    <td>{{ optional($checkoutRequest->needed_by_date)->format('Y-m-d') ?: '-' }}</td>
                                    <td><span class="label {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                    <td class="text-right">
                                        <a
                                            class="btn btn-sm btn-primary"
                                            href="{{ route('hardware.index', ['request_id' => $checkoutRequest->id, 'request_bucket' => 'reusable_now']) }}"
                                        >
                                            Review
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted">
                                        No {{ $status === 'all' ? '' : $status }} RAC requests found.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $targets->links() }}
                </div>
            </div>
        </div>
    </div>
@stop
