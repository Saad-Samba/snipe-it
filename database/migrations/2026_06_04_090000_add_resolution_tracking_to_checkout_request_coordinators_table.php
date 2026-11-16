<?php

use App\Models\CheckoutRequestCoordinator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_request_coordinators', function (Blueprint $table) {
            $table->string('resolution_status')->default(CheckoutRequestCoordinator::RESOLUTION_PENDING)->after('discipline_id');
            $table->timestamp('reviewed_at')->nullable()->after('resolution_status');
            $table->timestamp('last_action_at')->nullable()->after('reviewed_at');
            $table->index('resolution_status', 'checkout_request_coordinators_resolution_status_idx');
        });

        DB::table('checkout_request_coordinators')
            ->whereNull('resolution_status')
            ->update(['resolution_status' => CheckoutRequestCoordinator::RESOLUTION_PENDING]);
    }

    public function down(): void
    {
        Schema::table('checkout_request_coordinators', function (Blueprint $table) {
            $table->dropIndex('checkout_request_coordinators_resolution_status_idx');
            $table->dropColumn(['resolution_status', 'reviewed_at', 'last_action_at']);
        });
    }
};
