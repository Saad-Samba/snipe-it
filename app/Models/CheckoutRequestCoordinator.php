<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutRequestCoordinator extends Model
{
    use HasFactory;

    protected $fillable = [
        'checkout_request_id',
        'user_id',
        'company_id',
        'discipline_id',
    ];

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
