<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_seats', function (Blueprint $table) {
            $table->date('expected_release_date')->nullable()->after('asset_id');
        });

        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->string('requested_for_type')->nullable()->after('company_id');
            $table->string('requested_for_display')->nullable()->after('requested_for_type');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->dropColumn([
                'requested_for_type',
                'requested_for_display',
            ]);
        });

        Schema::table('license_seats', function (Blueprint $table) {
            $table->dropColumn('expected_release_date');
        });
    }
};
