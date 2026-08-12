<?php

namespace App\Console\Commands;

use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\License;
use App\Models\Setting;
use App\Notifications\UnroutedRacRequestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class ReconcileRacRouting extends Command
{
    protected $signature = 'snipeit:reconcile-rac-routing';

    protected $description = 'Reconcile RAC routing for active reusable inventory requests and alert administrators about uncovered scopes.';

    public function handle(): int
    {
        $reconciledCount = 0;
        $alertedCount = 0;

        CheckoutRequest::query()
            ->whereIn('requestable_type', [AssetModel::class, License::class])
            ->where('status', CheckoutRequest::STATUS_PENDING)
            ->whereNull('canceled_at')
            ->whereNull('fulfilled_at')
            ->with(['requestedItem', 'project'])
            ->chunkById(200, function (Collection $checkoutRequests) use (&$reconciledCount, &$alertedCount) {
                $lines = [];

                foreach ($checkoutRequests as $checkoutRequest) {
                    $routingResult = ResolveCheckoutRequestCoordinatorsAction::run($checkoutRequest, false);
                    $reconciledCount++;

                    if (! $routingResult->shouldAlert || empty($routingResult->unroutedScopes)) {
                        continue;
                    }

                    $lines[] = [
                        'request_id' => (int) $checkoutRequest->id,
                        'model_name' => $checkoutRequest->requestedItem()?->name ?? $checkoutRequest->name(),
                        'project_name' => optional($checkoutRequest->project)->name,
                        'unrouted_scopes' => $routingResult->unroutedScopes,
                        'review_url' => route('assets.requested'),
                    ];
                }

                $alertedCount += $this->sendAlerts($lines);
            });

        $this->info(sprintf(
            '%d active requests reconciled; %d unrouted requests included in administrator alerts.',
            $reconciledCount,
            $alertedCount
        ));

        return self::SUCCESS;
    }

    private function sendAlerts(array $lines): int
    {
        if (empty($lines)) {
            return 0;
        }

        $settings = Setting::getSettings();
        if (! $settings?->alerts_enabled || empty($settings->alert_email) || config('app.lock_passwords')) {
            return 0;
        }

        $recipients = collect(explode(',', $settings->alert_email))
            ->map(fn (string $email) => trim($email))
            ->filter()
            ->unique();

        if ($recipients->isEmpty()) {
            return 0;
        }

        foreach ($recipients as $recipient) {
            Notification::route('mail', $recipient)
                ->notify(new UnroutedRacRequestNotification($lines));
        }

        $requestIds = collect($lines)
            ->pluck('request_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        CheckoutRequest::query()
            ->whereIn('id', $requestIds)
            ->update(['rac_routing_alerted_at' => now()]);

        return count($requestIds);
    }
}
