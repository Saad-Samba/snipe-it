<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->unsignedInteger('due_back_before_needed_by_quantity')
                ->nullable()
                ->after('reusable_quantity');
            $table->unsignedInteger('potentially_coverable_quantity')
                ->nullable()
                ->after('due_back_before_needed_by_quantity');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedBigInteger('rfq_reserved_statuslabel_id')
                ->nullable()
                ->after('manager_view_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->dropColumn([
                'due_back_before_needed_by_quantity',
                'potentially_coverable_quantity',
            ]);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('rfq_reserved_statuslabel_id');
        });
    }
};
