<?php

namespace App\Http\Controllers;

use App\Actions\CheckoutRequests\AllocateCoordinatorScopedRequestAssetsAction;
use App\Models\CheckoutRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CheckoutRequestCoordinatorAllocationController extends Controller
{
    public function allocateAllForRequest(CheckoutRequest $checkoutRequest): RedirectResponse
    {
        return $this->redirectWithAllocationResults(
            collect([$checkoutRequest]),
            auth()->user(),
            route('hardware.index', ['request_id' => $checkoutRequest->id])
        );
    }

    public function allocateAllFromEmail(Request $request): RedirectResponse
    {
        $coordinatorId = (int) $request->query('coordinator');
        $requestIds = collect(explode(',', (string) $request->query('requests')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values();

        abort_if($requestIds->isEmpty(), 404);

        $ignoredQueryKeys = collect(array_keys($request->query()))
            ->reject(fn (string $key) => in_array($key, ['coordinator', 'requests', 'expires', 'signature'], true))
            ->values()
            ->all();

        if (! $request->hasValidSignatureWhileIgnoring($ignoredQueryKeys)) {
            return redirect()->route('requests.index')
                ->with('error', 'This allocation link is invalid or has expired.');
        }

        if ((int) auth()->id() !== $coordinatorId) {
            return redirect()->route('requests.index')
                ->with('error', 'This allocation link belongs to another coordinator account.');
        }

        $checkoutRequests = CheckoutRequest::query()
            ->whereIn('id', $requestIds)
            ->get()
            ->sortBy(fn (CheckoutRequest $checkoutRequest) => array_search($checkoutRequest->id, $requestIds->all(), true))
            ->values();

        abort_if($checkoutRequests->isEmpty(), 404);

        return $this->redirectWithAllocationResults(
            $checkoutRequests,
            auth()->user(),
            route('hardware.index', ['request_id' => $checkoutRequests->first()->id])
        );
    }

    private function redirectWithAllocationResults($checkoutRequests, User $coordinator, string $redirectTo): RedirectResponse
    {
        try {
            $requestCount = $checkoutRequests->count();
            $processedCount = 0;
            $allocatedCount = 0;
            $remainingQuantity = 0;

            foreach ($checkoutRequests as $checkoutRequest) {
                $result = AllocateCoordinatorScopedRequestAssetsAction::run($checkoutRequest, $coordinator);
                $processedCount++;
                $allocatedCount += (int) $result['allocated_count'];
                $remainingQuantity += (int) $result['remaining_quantity'];
            }

            if ($allocatedCount === 0) {
                return redirect()->to($redirectTo)->with(
                    'warning',
                    $this->buildSummaryMessage($requestCount, $processedCount, $allocatedCount, $remainingQuantity)
                );
            }

            return redirect()->to($redirectTo)->with(
                'success',
                $this->buildSummaryMessage($requestCount, $processedCount, $allocatedCount, $remainingQuantity)
            );
        } catch (AuthorizationException $exception) {
            return redirect()->to($redirectTo)->with('error', $exception->getMessage());
        } catch (RuntimeException $exception) {
            return redirect()->to($redirectTo)->with('error', $exception->getMessage());
        }
    }

    private function buildSummaryMessage(int $requestCount, int $processedCount, int $allocatedCount, int $remainingQuantity): string
    {
        $requestLabel = $requestCount === 1 ? 'request' : 'requests';
        $processedLabel = $processedCount === 1 ? 'request' : 'requests';
        $assetLabel = $allocatedCount === 1 ? 'asset' : 'assets';
        $remainingLabel = $remainingQuantity === 1 ? 'unit' : 'units';

        return "Allocated {$allocatedCount} {$assetLabel} across {$processedCount} {$processedLabel}. "
            ."{$remainingQuantity} {$remainingLabel} still remain open across {$requestCount} {$requestLabel}.";
    }
}
