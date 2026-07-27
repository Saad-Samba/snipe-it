<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->string('afm_review_status')->nullable()->after('status');
            $table->unsignedInteger('afm_reviewer_id')->nullable()->after('afm_review_status');
            $table->foreign('afm_reviewer_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('afm_reviewed_by')->nullable()->after('afm_reviewer_id');
            $table->foreign('afm_reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('afm_review_requested_at')->nullable()->after('afm_reviewed_by');
            $table->timestamp('afm_reviewed_at')->nullable()->after('afm_review_requested_at');
            $table->unsignedInteger('afm_confirmed_shortfall')->nullable()->after('afm_reviewed_at');
            $table->text('afm_review_note')->nullable()->after('afm_confirmed_shortfall');
            $table->index(
                ['afm_review_status', 'afm_reviewer_id'],
                'checkout_requests_afm_review_queue_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->dropIndex('checkout_requests_afm_review_queue_idx');
            $table->dropForeign(['afm_reviewer_id']);
            $table->dropForeign(['afm_reviewed_by']);
            $table->dropColumn([
                'afm_review_status',
                'afm_reviewer_id',
                'afm_reviewed_by',
                'afm_review_requested_at',
                'afm_reviewed_at',
                'afm_confirmed_shortfall',
                'afm_review_note',
            ]);
        });
    }
};
