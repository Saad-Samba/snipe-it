<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutRequestAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'checkout_request_id',
        'asset_id',
        'allocated_by',
        'allocated_at',
    ];

    protected $casts = [
        'allocated_at' => 'datetime',
    ];

    public function checkoutRequest()
    {
        return $this->belongsTo(CheckoutRequest::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function allocator()
    {
        return $this->belongsTo(User::class, 'allocated_by')->withTrashed();
    }
}
