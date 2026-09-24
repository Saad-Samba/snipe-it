<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FmcsReadinessAuditor
{
    private const RESOURCES = [
        'users' => ['type' => 'User', 'label' => 'username', 'severity' => 'blocking'],
        'assets' => ['type' => 'Asset', 'label' => 'asset_tag', 'severity' => 'blocking'],
        'licenses' => ['type' => 'License', 'label' => 'name', 'severity' => 'blocking'],
        'accessories' => ['type' => 'Accessory', 'label' => 'name', 'severity' => 'blocking'],
        'components' => ['type' => 'Component', 'label' => 'name', 'severity' => 'blocking'],
        'consumables' => ['type' => 'Consumable', 'label' => 'name', 'severity' => 'blocking'],
        'departments' => ['type' => 'Department', 'label' => 'name', 'severity' => 'blocking'],
        'locations' => ['type' => 'Location', 'label' => 'name', 'severity' => 'warning'],
    ];

    private const LOCATION_RELATIONSHIPS = [
        ['table' => 'users', 'type' => 'User', 'label' => 'username', 'column' => 'location_id'],
        ['table' => 'assets', 'type' => 'Asset', 'label' => 'asset_tag', 'column' => 'location_id'],
        ['table' => 'assets', 'type' => 'Asset', 'label' => 'asset_tag', 'column' => 'rtd_location_id'],
        ['table' => 'accessories', 'type' => 'Accessory', 'label' => 'name', 'column' => 'location_id'],
        ['table' => 'components', 'type' => 'Component', 'label' => 'name', 'column' => 'location_id'],
        ['table' => 'consumables', 'type' => 'Consumable', 'label' => 'name', 'column' => 'location_id'],
        ['table' => 'departments', 'type' => 'Department', 'label' => 'name', 'column' => 'location_id'],
    ];

    public function audit(bool $includeInactiveUsers = false, bool $includeDeleted = false): array
    {
        $findings = [];
        $coverage = [];

        foreach (self::RESOURCES as $table => $resource) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            $coverage = array_merge(
                $coverage,
                $this->siteCoverage($table, $resource, $includeInactiveUsers, $includeDeleted)
            );
            $this->collectMissingSites($findings, $table, $resource, $includeInactiveUsers, $includeDeleted);
            $this->collectInvalidSites($findings, $table, $resource, $includeInactiveUsers, $includeDeleted);
        }

        $this->collectLocationMismatches($findings, $includeInactiveUsers, $includeDeleted);
        $this->collectAssetAssignmentMismatches($findings, $includeDeleted);

        usort($findings, function (array $left, array $right): int {
            $severityOrder = ['blocking' => 0, 'warning' => 1, 'info' => 2];

            return [
                $severityOrder[$left['severity']],
                $left['code'],
                $left['resource_type'],
                $left['resource_id'],
            ] <=> [
                $severityOrder[$right['severity']],
                $right['code'],
                $right['resource_type'],
                $right['resource_id'],
            ];
        });

        return [
            'generated_at' => now()->toIso8601String(),
            'options' => [
                'include_inactive_users' => $includeInactiveUsers,
                'include_deleted' => $includeDeleted,
            ],
            'coverage' => $coverage,
            'totals' => [
                'blocking' => $this->countSeverity($findings, 'blocking'),
                'warning' => $this->countSeverity($findings, 'warning'),
                'info' => $this->countSeverity($findings, 'info'),
                'all' => count($findings),
            ],
            'summary' => $this->summarize($findings),
            'findings' => $findings,
        ];
    }

    private function siteCoverage(
        string $table,
        array $resource,
        bool $includeInactiveUsers,
        bool $includeDeleted
    ): array {
        return $this->resourceQuery($table, $includeInactiveUsers, $includeDeleted)
            ->leftJoin('companies as fmcs_coverage_companies', $table.'.company_id', '=', 'fmcs_coverage_companies.id')
            ->select([
                $table.'.company_id as site_id',
                'fmcs_coverage_companies.name as site_name',
                DB::raw('COUNT(*) as record_count'),
            ])
            ->groupBy($table.'.company_id', 'fmcs_coverage_companies.name')
            ->orderBy($table.'.company_id')
            ->get()
            ->map(fn ($row): array => [
                'resource_type' => $resource['type'],
                'site_id' => $row->site_id === null ? null : (int) $row->site_id,
                'site_name' => $row->site_name,
                'count' => (int) $row->record_count,
            ])
            ->all();
    }

    private function collectMissingSites(
        array &$findings,
        string $table,
        array $resource,
        bool $includeInactiveUsers,
        bool $includeDeleted
    ): void {
        $rows = $this->resourceQuery($table, $includeInactiveUsers, $includeDeleted)
            ->whereNull($table.'.company_id')
            ->select([$table.'.id', $table.'.'.$resource['label'].' as label'])
            ->orderBy($table.'.id')
            ->get();
        $userContexts = $table === 'users'
            ? $this->missingUserSiteContexts($rows->pluck('id')->map(fn ($id): int => (int) $id)->all())
            : [];

        foreach ($rows as $row) {
            $severity = $resource['severity'];
            $context = null;
            $message = $resource['type'].' is not assigned to a Site.';

            if ($table === 'users') {
                [$severity, $context, $message] = $userContexts[(int) $row->id];
            } elseif ($table === 'locations') {
                $message = 'Location is not assigned to a Site; this blocks optional FMCS location scoping.';
            }

            $findings[] = $this->finding(
                'missing_site',
                $severity,
                $resource['type'],
                (int) $row->id,
                $row->label,
                null,
                null,
                null,
                null,
                $message,
                $context
            );
        }
    }

    private function collectInvalidSites(
        array &$findings,
        string $table,
        array $resource,
        bool $includeInactiveUsers,
        bool $includeDeleted
    ): void {
        $query = $this->resourceQuery($table, $includeInactiveUsers, $includeDeleted)
            ->leftJoin('companies as fmcs_companies', $table.'.company_id', '=', 'fmcs_companies.id')
            ->whereNotNull($table.'.company_id')
            ->where(function (Builder $query): void {
                $query->whereNull('fmcs_companies.id');

                if (Schema::hasColumn('companies', 'deleted_at')) {
                    $query->orWhereNotNull('fmcs_companies.deleted_at');
                }
            })
            ->select([
                $table.'.id',
                $table.'.'.$resource['label'].' as label',
                $table.'.company_id',
            ])
            ->orderBy($table.'.id');

        foreach ($query->get() as $row) {
            $findings[] = $this->finding(
                'invalid_site',
                $resource['severity'],
                $resource['type'],
                (int) $row->id,
                $row->label,
                (int) $row->company_id,
                null,
                null,
                null,
                $resource['type'].' references a missing or deleted Site.'
            );
        }
    }

    private function collectLocationMismatches(
        array &$findings,
        bool $includeInactiveUsers,
        bool $includeDeleted
    ): void {
        if (! Schema::hasTable('locations')) {
            return;
        }

        foreach (self::LOCATION_RELATIONSHIPS as $relationship) {
            $table = $relationship['table'];
            $column = $relationship['column'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $query = $this->resourceQuery($table, $includeInactiveUsers, $includeDeleted)
                ->join('locations as fmcs_locations', $table.'.'.$column, '=', 'fmcs_locations.id')
                ->whereNotNull($table.'.company_id')
                ->where(function (Builder $query) use ($table): void {
                    $query->whereNull('fmcs_locations.company_id')
                        ->orWhereColumn('fmcs_locations.company_id', '<>', $table.'.company_id');
                })
                ->select([
                    $table.'.id',
                    $table.'.'.$relationship['label'].' as label',
                    $table.'.company_id',
                    'fmcs_locations.id as location_id',
                    'fmcs_locations.company_id as location_company_id',
                ])
                ->orderBy($table.'.id');

            foreach ($query->get() as $row) {
                $findings[] = $this->finding(
                    'location_site_mismatch',
                    'warning',
                    $relationship['type'],
                    (int) $row->id,
                    $row->label,
                    (int) $row->company_id,
                    'Location',
                    (int) $row->location_id,
                    $row->location_company_id === null ? null : (int) $row->location_company_id,
                    $relationship['type'].' Site does not match its '.$column.' Site; resolve before enabling location scoping.',
                    $column
                );
            }
        }
    }

    private function collectAssetAssignmentMismatches(array &$findings, bool $includeDeleted): void
    {
        if (! Schema::hasTable('assets')) {
            return;
        }

        $targets = [
            User::class => ['table' => 'users', 'type' => 'User'],
            Location::class => ['table' => 'locations', 'type' => 'Location'],
            Asset::class => ['table' => 'assets', 'type' => 'Asset'],
        ];

        foreach ($targets as $assignedType => $target) {
            if (! Schema::hasTable($target['table'])) {
                continue;
            }

            $targetAlias = 'fmcs_assignment_target';
            $query = $this->resourceQuery('assets', false, $includeDeleted)
                ->join($target['table'].' as '.$targetAlias, 'assets.assigned_to', '=', $targetAlias.'.id')
                ->where('assets.assigned_type', $assignedType)
                ->whereNotNull('assets.company_id')
                ->where(function (Builder $query) use ($targetAlias): void {
                    $query->whereNull($targetAlias.'.company_id')
                        ->orWhereColumn($targetAlias.'.company_id', '<>', 'assets.company_id');
                })
                ->select([
                    'assets.id',
                    'assets.asset_tag as label',
                    'assets.company_id',
                    $targetAlias.'.id as related_id',
                    $targetAlias.'.company_id as related_company_id',
                ])
                ->orderBy('assets.id');

            foreach ($query->get() as $row) {
                $findings[] = $this->finding(
                    'asset_assignment_site_mismatch',
                    'blocking',
                    'Asset',
                    (int) $row->id,
                    $row->label,
                    (int) $row->company_id,
                    $target['type'],
                    (int) $row->related_id,
                    $row->related_company_id === null ? null : (int) $row->related_company_id,
                    'Asset Site does not match the Site of its current assignment target.'
                );
            }
        }
    }

    private function resourceQuery(string $table, bool $includeInactiveUsers, bool $includeDeleted): Builder
    {
        $query = DB::table($table);

        if (! $includeDeleted && Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull($table.'.deleted_at');
        }

        if ($table === 'users' && ! $includeInactiveUsers) {
            $query->where($table.'.activated', 1);
        }

        return $query;
    }

    private function missingUserSiteContexts(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $users = User::withoutGlobalScopes()
            ->with('groups')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');
        $racUserIds = Schema::hasTable('regional_asset_coordinator_assignments')
            ? DB::table('regional_asset_coordinator_assignments')->whereIn('user_id', $userIds)->pluck('user_id')->map(fn ($id): int => (int) $id)->all()
            : [];
        $apiUserIds = Schema::hasTable('oauth_access_tokens')
            ? DB::table('oauth_access_tokens')->whereIn('user_id', $userIds)->where('revoked', false)->pluck('user_id')->map(fn ($id): int => (int) $id)->all()
            : [];
        $contexts = [];

        foreach ($userIds as $userId) {
            $user = $users->get($userId);

            if (! $user) {
                $contexts[$userId] = ['blocking', null, 'Active user is not assigned to a Site.'];

                continue;
            }

            if ($user->isSuperUser()) {
                $contexts[$userId] = ['info', 'superuser', 'Superuser has no Site and will retain global FMCS access.'];

                continue;
            }

            $userContext = [];

            if ($user->isAdmin()) {
                $userContext[] = 'admin';
            }

            if (in_array($userId, $racUserIds, true)) {
                $userContext[] = 'RAC';
            }

            if (in_array($userId, $apiUserIds, true)) {
                $userContext[] = 'API token owner';
            }

            $context = $userContext === [] ? null : implode(', ', $userContext);
            $message = $context
                ? 'Active user is not assigned to a Site ('.$context.').'
                : 'Active user is not assigned to a Site.';
            $contexts[$userId] = ['blocking', $context, $message];
        }

        return $contexts;
    }

    private function finding(
        string $code,
        string $severity,
        string $resourceType,
        int $resourceId,
        ?string $label,
        ?int $siteId,
        ?string $relatedType,
        ?int $relatedId,
        ?int $relatedSiteId,
        string $message,
        ?string $context = null
    ): array {
        return [
            'code' => $code,
            'severity' => $severity,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'label' => $label,
            'site_id' => $siteId,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'related_site_id' => $relatedSiteId,
            'context' => $context,
            'message' => $message,
        ];
    }

    private function countSeverity(array $findings, string $severity): int
    {
        return count(array_filter($findings, fn (array $finding): bool => $finding['severity'] === $severity));
    }

    private function summarize(array $findings): array
    {
        $summary = [];

        foreach ($findings as $finding) {
            $key = $finding['severity'].'|'.$finding['code'].'|'.$finding['resource_type'];

            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'severity' => $finding['severity'],
                    'code' => $finding['code'],
                    'resource_type' => $finding['resource_type'],
                    'count' => 0,
                ];
            }

            $summary[$key]['count']++;
        }

        return array_values($summary);
    }
}
