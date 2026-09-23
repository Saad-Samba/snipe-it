<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OffboardingReportRun extends Model
{
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_TEST_SENT = 'test_sent';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIAL_FAILURE = 'partial_failure';

    protected $fillable = [
        'source_hash',
        'source_filename',
        'status',
        'summary',
        'processed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'processed_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(OffboardingReportDelivery::class);
    }
}
