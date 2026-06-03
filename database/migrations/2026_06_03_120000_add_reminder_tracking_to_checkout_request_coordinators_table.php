<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_request_coordinators', function (Blueprint $table) {
            $table->timestamp('initial_notified_at')->nullable()->after('discipline_id');
            $table->timestamp('last_reminded_at')->nullable()->after('initial_notified_at');
            $table->unsignedInteger('reminder_count')->default(0)->after('last_reminded_at');

            $table->index('initial_notified_at', 'checkout_request_coordinators_initial_notified_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_request_coordinators', function (Blueprint $table) {
            $table->dropIndex('checkout_request_coordinators_initial_notified_at_idx');
            $table->dropColumn(['initial_notified_at', 'last_reminded_at', 'reminder_count']);
        });
    }
};
