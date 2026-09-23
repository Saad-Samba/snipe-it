<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories')) {
            DB::table('categories')->update([
                'eula_text' => null,
                'use_default_eula' => false,
                'require_acceptance' => false,
                'alert_on_response' => false,
            ]);
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->update([
                'default_eula_text' => null,
                'require_accept_signature' => false,
            ]);
        }

        if (Schema::hasTable('checkout_acceptances')) {
            DB::table('checkout_acceptances')
                ->whereNull('accepted_at')
                ->whereNull('declined_at')
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // The previous configuration and the origin of soft-deleted pending
        // requests cannot be reconstructed safely. Historical completed
        // acceptances remain untouched by this migration.
    }
};
