<?php

namespace App\Actions\CheckoutRequests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\RegionalAssetCoordinatorAssignment;
use Illuminate\Support\Collection;

class ResolveCheckoutRequestCoordinatorsAction
{
    public static function run(
        CheckoutRequest $checkoutRequest,
        bool $sendAlternativeFollowUp = true
    ): RacRoutingResult {
        if ($checkoutRequest->requestable_type !== AssetModel::class) {
            $checkoutRequest->coordinatorTargets()->delete();

            $result = self::persistRoutingSnapshot(
                $checkoutRequest,
                CheckoutRequest::RAC_ROUTING_NOT_REQUIRED,
                collect()
            );

            return self::finish($checkoutRequest, $result, $sendAlternativeFollowUp);
        }

        $eligibleAssetPairs = Asset::query()
            ->RTD()
            ->where('model_id', $checkoutRequest->requestable_id)
            ->whereNotNull('company_id')
            ->whereNotNull('discipline_id')
            ->get(['company_id', 'discipline_id']);

        if ($eligibleAssetPairs->isEmpty()) {
            $checkoutRequest->coordinatorTargets()->delete();

            $result = self::persistRoutingSnapshot(
                $checkoutRequest,
                CheckoutRequest::RAC_ROUTING_NOT_REQUIRED,
                collect()
            );

            return self::finish($checkoutRequest, $result, $sendAlternativeFollowUp);
        }

        $reusableCountsByScope = $eligibleAssetPairs
            ->groupBy(fn (Asset $asset) => self::makeScopeKey((int) $asset->company_id, (int) $asset->discipline_id))
            ->map(fn (Collection $assets) => $assets->count());

        $assignments = RegionalAssetCoordinatorAssignment::query()
            ->with(['coordinator', 'company', 'discipline'])
            ->get()
            ->filter(fn (RegionalAssetCoordinatorAssignment $assignment) => self::hasActiveCoordinator($assignment));

        $matchedAssignments = $assignments->filter(function (RegionalAssetCoordinatorAssignment $assignment) use ($reusableCountsByScope) {
            return $reusableCountsByScope->has(self::makeScopeKey((int) $assignment->company_id, (int) $assignment->discipline_id));
        });

        $matchedAssignmentsByScope = $matchedAssignments->keyBy(
            fn (RegionalAssetCoordinatorAssignment $assignment) => self::makeScopeKey(
                (int) $assignment->company_id,
                (int) $assignment->discipline_id
            )
        );

        $existingTargets = $checkoutRequest->coordinatorTargets()->get();
        foreach ($existingTargets as $target) {
            $scopeKey = self::makeScopeKey((int) $target->company_id, (int) $target->discipline_id);
            $assignment = $matchedAssignmentsByScope->get($scopeKey);

            if (! $assignment || (int) $assignment->user_id !== (int) $target->user_id) {
                $target->delete();
            }
        }

        foreach ($matchedAssignments as $assignment) {
            $checkoutRequest->coordinatorTargets()->firstOrCreate(
                [
                    'user_id' => $assignment->user_id,
                    'company_id' => $assignment->company_id,
                    'discipline_id' => $assignment->discipline_id,
                ]
            );
        }

        $unroutedScopeKeys = $reusableCountsByScope->keys()->diff($matchedAssignmentsByScope->keys());
        $unroutedScopes = self::describeUnroutedScopes($unroutedScopeKeys, $reusableCountsByScope);
        $routingStatus = $unroutedScopes->isEmpty()
            ? CheckoutRequest::RAC_ROUTING_ROUTED
            : ($matchedAssignments->isEmpty()
                ? CheckoutRequest::RAC_ROUTING_UNROUTED
                : CheckoutRequest::RAC_ROUTING_PARTIALLY_ROUTED);

        $coordinatorMatches = $matchedAssignments
            ->groupBy('user_id')
            ->map(function (Collection $userAssignments, int|string $userId) use ($reusableCountsByScope) {
                /** @var RegionalAssetCoordinatorAssignment $firstAssignment */
                $firstAssignment = $userAssignments->first();

                return [
                    'user_id' => (int) $userId,
                    'coordinator' => $firstAssignment->coordinator,
                    'inventory_discipline_names' => $userAssignments
                        ->pluck('discipline.name')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values()
                        ->all(),
                    'reusable_quantity' => $userAssignments->sum(
                        fn (RegionalAssetCoordinatorAssignment $assignment) => (int) ($reusableCountsByScope[self::makeScopeKey((int) $assignment->company_id, (int) $assignment->discipline_id)] ?? 0)
                    ),
                ];
            })
            ->values();

        $result = self::persistRoutingSnapshot(
            $checkoutRequest,
            $routingStatus,
            $unroutedScopes,
            $coordinatorMatches
        );

        return self::finish($checkoutRequest, $result, $sendAlternativeFollowUp);
    }

    private static function finish(
        CheckoutRequest $checkoutRequest,
        RacRoutingResult $result,
        bool $sendAlternativeFollowUp
    ): RacRoutingResult {
        if ($sendAlternativeFollowUp) {
            SendAlternativeFollowUpNotificationAction::run($checkoutRequest);
        }

        return $result;
    }

    private static function makeScopeKey(int $companyId, int $disciplineId): string
    {
        return $companyId.'-'.$disciplineId;
    }

    private static function hasActiveCoordinator(RegionalAssetCoordinatorAssignment $assignment): bool
    {
        return $assignment->coordinator
            && ! $assignment->coordinator->trashed()
            && (bool) $assignment->coordinator->activated;
    }

    private static function describeUnroutedScopes(Collection $scopeKeys, Collection $reusableCountsByScope): Collection
    {
        $scopeParts = $scopeKeys
            ->map(function (string $scopeKey) {
                [$companyId, $disciplineId] = array_map('intval', explode('-', $scopeKey, 2));

                return compact('companyId', 'disciplineId');
            })
            ->values();

        $companies = Company::query()
            ->whereIn('id', $scopeParts->pluck('companyId'))
            ->pluck('name', 'id');
        $disciplines = Discipline::query()
            ->whereIn('id', $scopeParts->pluck('disciplineId'))
            ->pluck('name', 'id');

        return $scopeParts
            ->map(function (array $scope) use ($companies, $disciplines, $reusableCountsByScope) {
                $scopeKey = self::makeScopeKey($scope['companyId'], $scope['disciplineId']);

                return [
                    'company_id' => $scope['companyId'],
                    'company_name' => $companies[$scope['companyId']] ?? null,
                    'discipline_id' => $scope['disciplineId'],
                    'discipline_name' => $disciplines[$scope['disciplineId']] ?? null,
                    'reusable_quantity' => (int) ($reusableCountsByScope[$scopeKey] ?? 0),
                ];
            })
            ->sortBy([
                ['company_id', 'asc'],
                ['discipline_id', 'asc'],
            ])
            ->values();
    }

    private static function persistRoutingSnapshot(
        CheckoutRequest $checkoutRequest,
        string $status,
        Collection $unroutedScopes,
        ?Collection $coordinatorMatches = null
    ): RacRoutingResult {
        $unroutedScopesArray = $unroutedScopes->values()->all();
        $previousScopes = $checkoutRequest->rac_unrouted_scopes ?? [];
        $gapsChanged = self::scopeIdentity($previousScopes) !== self::scopeIdentity($unroutedScopesArray);
        $shouldAlert = ! empty($unroutedScopesArray)
            && ($gapsChanged || ! $checkoutRequest->rac_routing_alerted_at);

        $checkoutRequest->forceFill([
            'rac_routing_status' => $status,
            'rac_unrouted_scopes' => empty($unroutedScopesArray) ? null : $unroutedScopesArray,
            'rac_routing_alerted_at' => ($gapsChanged || empty($unroutedScopesArray))
                ? null
                : $checkoutRequest->rac_routing_alerted_at,
        ])->save();

        return new RacRoutingResult(
            $coordinatorMatches ?? collect(),
            $status,
            $unroutedScopesArray,
            $shouldAlert
        );
    }

    private static function scopeIdentity(array $scopes): array
    {
        return collect($scopes)
            ->map(fn (array $scope) => [
                'company_id' => (int) ($scope['company_id'] ?? 0),
                'discipline_id' => (int) ($scope['discipline_id'] ?? 0),
            ])
            ->sortBy([
                ['company_id', 'asc'],
                ['discipline_id', 'asc'],
            ])
            ->values()
            ->all();
    }
}
