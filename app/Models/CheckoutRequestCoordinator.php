<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutRequestCoordinator extends Model
{
    use HasFactory;

    public const RESOLUTION_PENDING = 'pending';
    public const RESOLUTION_IN_PROGRESS = 'in_progress';
    public const RESOLUTION_COMPLETED = 'completed';
    public const RESOLUTION_COMPLETED_NO_STOCK = 'completed_no_stock';

    protected $fillable = [
        'checkout_request_id',
        'user_id',
        'company_id',
        'discipline_id',
        'resolution_status',
        'reviewed_at',
        'last_action_at',
        'initial_notified_at',
        'last_reminded_at',
        'reminder_count',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'last_action_at' => 'datetime',
        'initial_notified_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'reminder_count' => 'integer',
    ];

    public static function terminalResolutionStatuses(): array
    {
        return [
            self::RESOLUTION_COMPLETED,
            self::RESOLUTION_COMPLETED_NO_STOCK,
        ];
    }

    public function resolvedStatus(): string
    {
        return $this->resolution_status ?: self::RESOLUTION_PENDING;
    }

    public function hasTerminalResolution(): bool
    {
        return in_array($this->resolvedStatus(), self::terminalResolutionStatuses(), true);
    }

    public function markInProgress(): void
    {
        $this->markResolution(self::RESOLUTION_IN_PROGRESS);
    }

    public function markCompleted(): void
    {
        $this->markResolution(self::RESOLUTION_COMPLETED);
    }

    public function markCompletedNoStock(): void
    {
        $this->markResolution(self::RESOLUTION_COMPLETED_NO_STOCK);
    }

    private function markResolution(string $resolutionStatus): void
    {
        $this->forceFill([
            'resolution_status' => $resolutionStatus,
            'reviewed_at' => $this->reviewed_at ?: now(),
            'last_action_at' => now(),
        ])->save();
    }

    public function checkoutRequest()
    {
        return $this->belongsTo(CheckoutRequest::class);
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function discipline()
    {
        return $this->belongsTo(Discipline::class, 'discipline_id');
    }
}
