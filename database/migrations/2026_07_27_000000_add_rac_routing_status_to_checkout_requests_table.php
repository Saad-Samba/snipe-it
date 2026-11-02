<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->string('rac_routing_status', 32)->nullable()->after('status');
            $table->json('rac_unrouted_scopes')->nullable()->after('rac_routing_status');
            $table->timestamp('rac_routing_alerted_at')->nullable()->after('rac_unrouted_scopes');

            $table->index('rac_routing_status');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->dropIndex(['rac_routing_status']);
            $table->dropColumn(['rac_routing_status', 'rac_unrouted_scopes', 'rac_routing_alerted_at']);
        });
    }
};
