<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialChangeEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'event_type',
        'company_id',
        'previous_status_id',
        'new_status_id',
        'previous_company_id',
        'new_company_id',
        'effective_at',
        'changed_by',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function previousCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'previous_company_id');
    }

    public function newCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'new_company_id');
    }

    public function previousStatus(): BelongsTo
    {
        return $this->belongsTo(Statuslabel::class, 'previous_status_id');
    }

    public function newStatus(): BelongsTo
    {
        return $this->belongsTo(Statuslabel::class, 'new_status_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(FinancialChangeDelivery::class);
    }
}
