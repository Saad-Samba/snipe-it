<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_request_assets', function (Blueprint $table) {
            $table->unsignedInteger('transfer_source_company_id')->nullable()->after('allocated_at');
            $table->unsignedInteger('transfer_destination_company_id')->nullable()->after('transfer_source_company_id');
            $table->dateTime('transfer_started_at')->nullable()->after('transfer_destination_company_id');
            $table->dateTime('transfer_completed_at')->nullable()->after('transfer_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_request_assets', function (Blueprint $table) {
            $table->dropColumn([
                'transfer_source_company_id',
                'transfer_destination_company_id',
                'transfer_started_at',
                'transfer_completed_at',
            ]);
        });
    }
};
