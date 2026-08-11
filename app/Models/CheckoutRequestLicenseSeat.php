<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutRequestLicenseSeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'checkout_request_id',
        'license_seat_id',
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

    public function licenseSeat()
    {
        return $this->belongsTo(LicenseSeat::class);
    }

    public function allocator()
    {
        return $this->belongsTo(User::class, 'allocated_by')->withTrashed();
    }
}
