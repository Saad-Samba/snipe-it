<?php

namespace App\Http\Controllers;

use App\Models\CheckoutRequestCoordinator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RacRequestsController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        if (! in_array($status, ['open', 'completed', 'all'], true)) {
            $status = 'open';
        }

        $baseQuery = CheckoutRequestCoordinator::query()
            ->where('user_id', auth()->id())
            ->whereHas('checkoutRequest', fn ($query) => $query->whereNull('canceled_at'));

        $openQuery = fn ($query) => $query->where(function ($query) {
            $query->whereNull('resolution_status')
                ->orWhereNotIn(
                    'resolution_status',
                    CheckoutRequestCoordinator::terminalResolutionStatuses()
                );
        });

        $completedQuery = fn ($query) => $query->whereIn(
            'resolution_status',
            CheckoutRequestCoordinator::terminalResolutionStatuses()
        );

        $targetsQuery = (clone $baseQuery)
            ->with([
                'checkoutRequest.requestedItem',
                'checkoutRequest.user',
                'checkoutRequest.project',
                'checkoutRequest.company',
                'checkoutRequest.requestedDiscipline',
                'checkoutRequest.allocatedAssets',
                'company',
                'discipline',
            ]);

        if ($status === 'open') {
            $openQuery($targetsQuery);
        } elseif ($status === 'completed') {
            $completedQuery($targetsQuery);
        }

        $targets = $targetsQuery
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('account/rac-requests', [
            'targets' => $targets,
            'status' => $status,
            'openCount' => $openQuery(clone $baseQuery)->count(),
            'completedCount' => $completedQuery(clone $baseQuery)->count(),
        ]);
    }
}
