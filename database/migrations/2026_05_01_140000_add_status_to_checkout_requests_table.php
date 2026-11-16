<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('quantity');
        });

        DB::table('checkout_requests')
            ->whereNotNull('canceled_at')
            ->update(['status' => 'canceled']);

        DB::table('checkout_requests')
            ->whereNull('canceled_at')
            ->whereNotNull('fulfilled_at')
            ->update(['status' => 'fulfilled']);
    }

    public function down(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
