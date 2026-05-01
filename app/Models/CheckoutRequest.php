<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CheckoutRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'user_id',
        'requested_discipline_id',
        'quantity',
        'status',
        'note',
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

    public function coordinatorTargets()
    {
        return $this->hasMany(CheckoutRequestCoordinator::class);
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

        if ($this->fulfilled_at) {
            return self::STATUS_FULFILLED;
        }

        return $this->status ?: self::STATUS_PENDING;
    }

    public function canBeProcessedBy(User $user): bool
    {
        return $this->candidateCoordinators()->where('users.id', $user->id)->exists()
            && !in_array($this->resolvedStatus(), [self::STATUS_CANCELED, self::STATUS_FULFILLED, self::STATUS_REJECTED], true);
    }
}
