<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialChangeDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_change_event_id',
        'user_id',
        'reported_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FinancialChangeEvent::class, 'financial_change_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
