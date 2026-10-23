<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_request_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('checkout_request_id');
            $table->unsignedInteger('asset_id');
            $table->unsignedInteger('allocated_by')->nullable();
            $table->dateTime('allocated_at')->nullable();
            $table->timestamps();

            $table->unique('asset_id');
            $table->unique(['checkout_request_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_request_assets');
    }
};
