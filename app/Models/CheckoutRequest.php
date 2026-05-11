<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class CheckoutRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FULLY_ALLOCATED = 'fully_allocated';
    public const STATUS_PARTIALLY_ALLOCATED = 'partially_allocated';
    public const STATUS_NOT_ALLOCATED = 'not_allocated';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'requested_discipline_id',
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
        'note',
    ];

    protected $casts = [
        'needed_by_date' => 'date',
        'estimated_savings' => 'float',
        'reference_price_snapshot' => 'float',
    ];

    protected $table = 'checkout_requests';

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

        if (in_array($this->status, [self::STATUS_UNDER_REVIEW, self::STATUS_APPROVED], true)) {
            return self::STATUS_IN_PROGRESS;
        }

        if ($this->status === self::STATUS_REJECTED) {
            return self::STATUS_NOT_ALLOCATED;
        }

        return $this->status ?: self::STATUS_PENDING;
    }

    public function canBeProcessedBy(User $user): bool
    {
        return $this->canBeViewedBy($user)
            && !in_array($this->resolvedStatus(), [self::STATUS_CANCELED, self::STATUS_FULLY_ALLOCATED, self::STATUS_PARTIALLY_ALLOCATED, self::STATUS_NOT_ALLOCATED], true);
    }

    public function canBeViewedBy(User $user): bool
    {
        return $this->candidateCoordinators()->where('users.id', $user->id)->exists()
            && $this->resolvedStatus() !== self::STATUS_CANCELED;
    }

    public function allocatedQuantity(): int
    {
        return $this->allocatedAssets()->count();
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
        $reservedStatusId = Setting::getSettings()?->rfq_reserved_statuslabel_id;

        if (! $reservedStatusId || ! $this->project_id || $this->requestable_type !== AssetModel::class) {
            return Asset::query()->whereRaw('1 = 0');
        }

        return Asset::withoutGlobalScopes()
            ->where('model_id', $this->requestable_id)
            ->where('project_id', $this->project_id)
            ->where('status_id', $reservedStatusId)
            ->whereNotNull('expected_checkin');
    }

    public function reservedAssetsCount(): int
    {
        return $this->reservedAssetsQuery()->count();
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
        $reservedStatusId = Setting::getSettings()?->rfq_reserved_statuslabel_id;
        $projectId = $requests->first()?->project_id;

        $reservedAssets = 0;
        if ($reservedStatusId && $projectId) {
            $reservedAssets = Asset::withoutGlobalScopes()
                ->where('project_id', $projectId)
                ->where('status_id', $reservedStatusId)
                ->whereNotNull('expected_checkin')
                ->count();
        }

        return [
            'requests_count' => $requests->count(),
            'total_needed' => (int) $requests->sum('quantity'),
            'reusable_now' => (int) $requests->sum(fn ($request) => (int) ($request->reusable_quantity ?? 0)),
            'due_back_before_needed_by' => (int) $requests->sum(fn ($request) => (int) ($request->due_back_before_needed_by_quantity ?? 0)),
            'potentially_coverable' => (int) $requests->sum(fn ($request) => (int) ($request->potentially_coverable_quantity ?? 0)),
            'shortfall' => (int) $requests->sum(fn ($request) => (int) ($request->procurement_shortfall ?? 0)),
            'estimated_savings' => round((float) $requests->sum(fn ($request) => (float) ($request->estimated_savings ?? 0)), 2),
            'booked_count' => (int) $requests->sum(fn ($request) => $request->bookedAssetsCount()),
            'reserved_count' => $reservedAssets,
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

        $this->status = $this->derivedAllocationStatus();

        if ($forceDerived && ! $this->fulfilled_at) {
            $this->fulfilled_at = now();
        }

        $this->save();
    }
}
