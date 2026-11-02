<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->unsignedInteger('reusable_quantity')->nullable()->after('quantity');
            $table->unsignedInteger('procurement_shortfall')->nullable()->after('reusable_quantity');
            $table->decimal('estimated_savings', 20, 2)->nullable()->after('procurement_shortfall');
            $table->decimal('reference_price_snapshot', 20, 2)->nullable()->after('estimated_savings');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->dropColumn([
                'reusable_quantity',
                'procurement_shortfall',
                'estimated_savings',
                'reference_price_snapshot',
            ]);
        });
    }
};
