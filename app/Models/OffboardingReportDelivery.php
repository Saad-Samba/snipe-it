<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OffboardingReportDelivery extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'offboarding_report_run_id',
        'mode',
        'intended_recipient',
        'delivery_recipient',
        'status',
        'error',
        'attempted_at',
        'sent_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function reportRun()
    {
        return $this->belongsTo(OffboardingReportRun::class, 'offboarding_report_run_id');
    }
}
