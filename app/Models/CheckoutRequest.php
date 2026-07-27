<?php

namespace App\Models;

use App\Actions\CheckoutRequests\EstimateAssetModelReuseAction;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class CheckoutRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected ?array $liveRequestMetricsCache = null;

    public const STATUS_PENDING = 'pending';
    public const STATUS_FULLY_ALLOCATED = 'fully_allocated';
    public const STATUS_PARTIALLY_ALLOCATED = 'partially_allocated';
    public const STATUS_NOT_ALLOCATED = 'not_allocated';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_REJECTED = 'rejected';

    public const RAC_ROUTING_ROUTED = 'routed';
    public const RAC_ROUTING_PARTIALLY_ROUTED = 'partially_routed';
    public const RAC_ROUTING_UNROUTED = 'unrouted';
    public const RAC_ROUTING_NOT_REQUIRED = 'not_required';

    protected $fillable = [
        'user_id',
        'requested_discipline_id',
        'company_id',
        'project_id',
        'needed_by_date',
        'quantity',
        'reusable_quantity',
        'due_back_before_needed_by_quantity',
        'potentially_coverable_quantity',
        'procurement_shortfall',
        'estimated_savings',
        'reference_price_snapshot',
        'status',
        'rac_routing_status',
        'rac_unrouted_scopes',
        'rac_routing_alerted_at',
        'note',
    ];

    protected $casts = [
        'needed_by_date' => 'date',
        'estimated_savings' => 'float',
        'reference_price_snapshot' => 'float',
        'rac_unrouted_scopes' => 'array',
        'rac_routing_alerted_at' => 'datetime',
    ];

    protected $table = 'checkout_requests';

    public static function requesterScopedQuery(User $user): Builder
    {
        return self::query()
            ->where('user_id', $user->id)
            ->whereNull('canceled_at');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function requestingUser()
    {
        return $this->user()->withTrashed()->first();
    }

    public function requestedItem()
    {
        return $this->morphTo('requestable');
    }

    public function requestedDiscipline()
    {
        return $this->belongsTo(Discipline::class, 'requested_discipline_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function coordinatorTargets()
    {
        return $this->hasMany(CheckoutRequestCoordinator::class);
    }

    public function allocatedAssets()
    {
        return $this->belongsToMany(Asset::class, 'checkout_request_assets')
            ->withoutGlobalScope(CompanyableScope::class)
            ->withPivot(['allocated_by', 'allocated_at'])
            ->withTimestamps();
    }

    public function candidateCoordinators()
    {
        return $this->belongsToMany(
            User::class,
            'checkout_request_coordinators',
            'checkout_request_id',
            'user_id'
        )->withPivot(['company_id', 'discipline_id'])->withTimestamps();
    }

    public function itemRequested() // Workaround for laravel polymorphic issue that's not being solved :(
    {
        return $this->requestedItem()->first();
    }

    public function itemType()
    {
        return snake_case(class_basename($this->requestable_type));
    }

    public function location()
    {
        return $this->itemRequested()?->location;
    }

    public function name()
    {
        if ($this->itemType() == 'asset') {
            return $this->itemRequested()->display_name;
        }

        return $this->itemRequested()->name;
    }

    public function resolvedStatus(): string
    {
        if ($this->canceled_at) {
            return self::STATUS_CANCELED;
        }

        if ($this->fulfilled_at || $this->status === self::STATUS_FULFILLED) {
            return $this->status ?: self::STATUS_FULLY_ALLOCATED;
        }

        if ($this->status === self::STATUS_REJECTED) {
            return self::STATUS_NOT_ALLOCATED;
        }

        return $this->status ?: self::STATUS_PENDING;
    }

    public function canBeProcessedBy(User $user): bool
    {
        return $this->canBeViewedBy($user)
            && !in_array($this->resolvedStatus(), [self::STATUS_CANCELED, self::STATUS_FULLY_ALLOCATED, self::STATUS_NOT_ALLOCATED, self::STATUS_REJECTED, self::STATUS_FULFILLED], true)
            && $this->remainingAllocationQuantity() > 0;
    }

    public function canBeViewedBy(User $user): bool
    {
        return $this->candidateCoordinators()->where('users.id', $user->id)->exists()
            && $this->resolvedStatus() !== self::STATUS_CANCELED;
    }

    public function canBeBulkAllocatedBy(User $user): bool
    {
        return $this->requestable_type === AssetModel::class
            && $this->canBeViewedBy($user)
            && $this->remainingAllocationQuantity() > 0;
    }

    public function allocatedQuantity(): int
    {
        return $this->allocatedAssets()->count();
    }

    public function remainingAllocationQuantity(): int
    {
        return max((int) $this->quantity - $this->allocatedQuantity(), 0);
    }

    public function suggestedReusableAssetIds(): array
    {
        if ($this->requestable_type !== AssetModel::class || ! $this->requestable_id) {
            return [];
        }

        return Asset::query()
            ->RTD()
            ->where('model_id', $this->requestable_id)
            ->orderBy('assets.id')
            ->limit(max($this->remainingAllocationQuantity(), 0))
            ->pluck('assets.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function bookedAssetsQuery()
    {
        if ($this->requestable_type !== AssetModel::class || ! $this->project_id) {
            return Asset::query()->whereRaw('1 = 0');
        }

        return Asset::withoutGlobalScopes()
            ->where('model_id', $this->requestable_id)
            ->where('project_id', $this->project_id)
            ->whereNotNull('assigned_to');
    }

    public function bookedAssetsCount(): int
    {
        return $this->bookedAssetsQuery()->count();
    }

    public function reservedAssetsQuery()
    {
        $reservedStatusId = Setting::rfqReservedStatusId();

        if (! $reservedStatusId || ! $this->project_id || $this->requestable_type !== AssetModel::class) {
            return Asset::query()->whereRaw('1 = 0');
        }

        return Asset::withoutGlobalScopes()
            ->where('model_id', $this->requestable_id)
            ->where('project_id', $this->project_id)
            ->where('status_id', $reservedStatusId);
    }

    public function reservedAssetsCount(): int
    {
        return $this->reservedAssetsQuery()->count();
    }

    public function reservedByOtherRfqsQuery()
    {
        $reservedStatusId = Setting::rfqReservedStatusId();

        if (! $reservedStatusId || ! $this->project_id || $this->requestable_type !== AssetModel::class) {
            return Asset::query()->whereRaw('1 = 0');
        }

        return Asset::withoutGlobalScopes()
            ->where('model_id', $this->requestable_id)
            ->whereNotNull('project_id')
            ->where('project_id', '!=', $this->project_id)
            ->where('status_id', $reservedStatusId);
    }

    public function reservedByOtherRfqsCount(): int
    {
        return $this->reservedByOtherRfqsQuery()->count();
    }

    public function amountToBuy(): float
    {
        return round(
            ((float) ($this->procurement_shortfall ?? 0)) * ((float) ($this->reference_price_snapshot ?? 0)),
            2
        );
    }

    public function liveRequestMetrics(): array
    {
        if ($this->liveRequestMetricsCache !== null) {
            return $this->liveRequestMetricsCache;
        }

        if ($this->requestable_type !== AssetModel::class) {
            $referencePrice = $this->reference_price_snapshot !== null ? (float) $this->reference_price_snapshot : 0.0;
            $reusableQuantity = (int) ($this->reusable_quantity ?? 0);
            $dueBackQuantity = (int) ($this->due_back_before_needed_by_quantity ?? 0);
            $coverableQuantity = min((int) $this->quantity, $reusableQuantity + $dueBackQuantity);
            $shortfall = max((int) $this->quantity - ($reusableQuantity + $dueBackQuantity), 0);

            return $this->liveRequestMetricsCache = [
                'reusable_quantity' => $reusableQuantity,
                'due_back_before_needed_by_quantity' => $dueBackQuantity,
                'procurement_shortfall' => $shortfall,
                'estimated_savings' => round($coverableQuantity * $referencePrice, 2),
                'reference_price' => $referencePrice,
                'amount_to_buy' => round($shortfall * $referencePrice, 2),
            ];
        }

        /** @var AssetModel|null $model */
        $model = $this->requestedItem()->first();

        if (! $model) {
            return $this->liveRequestMetricsCache = [
                'reusable_quantity' => 0,
                'due_back_before_needed_by_quantity' => 0,
                'procurement_shortfall' => (int) $this->quantity,
                'estimated_savings' => 0.0,
                'reference_price' => $this->reference_price_snapshot !== null ? (float) $this->reference_price_snapshot : 0.0,
                'amount_to_buy' => round(((float) ($this->reference_price_snapshot ?? 0)) * ((int) $this->quantity), 2),
            ];
        }

        $estimate = EstimateAssetModelReuseAction::run(
            $model,
            (int) $this->quantity,
            optional($this->needed_by_date)?->format('Y-m-d')
        );

        $referencePrice = $this->reference_price_snapshot !== null
            ? (float) $this->reference_price_snapshot
            : (float) ($estimate['reference_price_snapshot'] ?? 0);
        $reusableQuantity = (int) ($estimate['reusable_quantity'] ?? 0);
        $dueBackQuantity = (int) ($estimate['due_back_before_needed_by_quantity'] ?? 0);
        $coverableQuantity = min((int) $this->quantity, $reusableQuantity + $dueBackQuantity);
        $shortfall = max((int) $this->quantity - ($reusableQuantity + $dueBackQuantity), 0);

        return $this->liveRequestMetricsCache = [
            'reusable_quantity' => $reusableQuantity,
            'due_back_before_needed_by_quantity' => $dueBackQuantity,
            'procurement_shortfall' => $shortfall,
            'estimated_savings' => round($coverableQuantity * $referencePrice, 2),
            'reference_price' => $referencePrice,
            'amount_to_buy' => round($shortfall * $referencePrice, 2),
        ];
    }

    public function liveReusableQuantity(): int
    {
        return (int) ($this->liveRequestMetrics()['reusable_quantity'] ?? 0);
    }

    public function liveDueBackBeforeNeededByQuantity(): int
    {
        return (int) ($this->liveRequestMetrics()['due_back_before_needed_by_quantity'] ?? 0);
    }

    public function liveProcurementShortfall(): int
    {
        return (int) ($this->liveRequestMetrics()['procurement_shortfall'] ?? 0);
    }

    public function liveEstimatedSavings(): float
    {
        return round((float) ($this->liveRequestMetrics()['estimated_savings'] ?? 0), 2);
    }

    public function liveAmountToBuy(): float
    {
        return round((float) ($this->liveRequestMetrics()['amount_to_buy'] ?? 0), 2);
    }

    public function requesterAllocationStatus(): string
    {
        $resolvedStatus = $this->resolvedStatus();

        if (in_array($resolvedStatus, [
            self::STATUS_CANCELED,
            self::STATUS_FULFILLED,
            self::STATUS_FULLY_ALLOCATED,
            self::STATUS_PARTIALLY_ALLOCATED,
            self::STATUS_NOT_ALLOCATED,
        ], true)) {
            return $resolvedStatus;
        }

        $reservedCount = $this->reservedAssetsCount();

        if ($reservedCount >= $this->quantity) {
            return self::STATUS_FULLY_ALLOCATED;
        }

        if ($reservedCount > 0) {
            return self::STATUS_PARTIALLY_ALLOCATED;
        }

        return self::STATUS_PENDING;
    }

    public static function projectSummaryForUser(int $userId, int $projectId): array
    {
        $requests = self::query()
            ->where('user_id', $userId)
            ->where('project_id', $projectId)
            ->whereNull('canceled_at')
            ->get();

        return self::summarizeRequests($requests);
    }

    public static function summarizeRequests(Collection $requests): array
    {
        $reservedStatusId = Setting::rfqReservedStatusId();
        $projectId = $requests->first()?->project_id;

        $reservedAssets = 0;
        $reservedByOtherRfqs = 0;
        if ($reservedStatusId && $projectId) {
            $reservedAssets = $requests->sum(fn ($request) => $request->reservedAssetsCount());

            $modelIds = $requests
                ->filter(fn ($request) => $request->requestable_type === AssetModel::class)
                ->pluck('requestable_id')
                ->filter()
                ->unique()
                ->values();

            if ($modelIds->isNotEmpty()) {
                $reservedByOtherRfqs = Asset::withoutGlobalScopes()
                    ->whereIn('model_id', $modelIds)
                    ->whereNotNull('project_id')
                    ->where('project_id', '!=', $projectId)
                    ->where('status_id', $reservedStatusId)
                    ->count();
            }
        }

        return [
            'requests_count' => $requests->count(),
            'total_needed' => (int) $requests->sum('quantity'),
            'reusable_now' => (int) $requests->sum(fn ($request) => $request->liveReusableQuantity()),
            'due_back_before_needed_by' => (int) $requests->sum(fn ($request) => $request->liveDueBackBeforeNeededByQuantity()),
            'shortfall' => (int) $requests->sum(fn ($request) => $request->liveProcurementShortfall()),
            'estimated_savings' => round((float) $requests->sum(fn ($request) => $request->liveEstimatedSavings()), 2),
            'reserved_count' => $reservedAssets,
            'reserved_by_other_rfqs_count' => $reservedByOtherRfqs,
            'amount_to_buy' => round((float) $requests->sum(fn ($request) => $request->liveAmountToBuy()), 2),
        ];
    }

    public function derivedAllocationStatus(): string
    {
        $allocatedCount = $this->allocatedQuantity();

        if ($allocatedCount >= $this->quantity) {
            return self::STATUS_FULLY_ALLOCATED;
        }

        if ($allocatedCount > 0) {
            return self::STATUS_PARTIALLY_ALLOCATED;
        }

        return self::STATUS_NOT_ALLOCATED;
    }

    public function syncAllocationStatus(bool $forceDerived = false): void
    {
        if ($this->canceled_at) {
            return;
        }

        $hasFinalizedOutcome = $this->fulfilled_at
            || in_array($this->status, [
                self::STATUS_FULLY_ALLOCATED,
                self::STATUS_PARTIALLY_ALLOCATED,
                self::STATUS_NOT_ALLOCATED,
                self::STATUS_FULFILLED,
                self::STATUS_REJECTED,
            ], true);

        if (! $forceDerived && ! $hasFinalizedOutcome) {
            return;
        }

        $derivedStatus = $this->derivedAllocationStatus();
        $this->status = $derivedStatus;

        if ($forceDerived && $derivedStatus === self::STATUS_FULLY_ALLOCATED && ! $this->fulfilled_at) {
            $this->fulfilled_at = now();
        }

        if ($derivedStatus !== self::STATUS_FULLY_ALLOCATED) {
            $this->fulfilled_at = null;
        }

        $this->save();
    }
}
