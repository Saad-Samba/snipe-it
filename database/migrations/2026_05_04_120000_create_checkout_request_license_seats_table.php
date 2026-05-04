<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_request_license_seats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('checkout_request_id');
            $table->unsignedInteger('license_seat_id');
            $table->unsignedInteger('allocated_by')->nullable();
            $table->dateTime('allocated_at')->nullable();
            $table->timestamps();

            $table->unique('license_seat_id');
            $table->unique(['checkout_request_id', 'license_seat_id'], 'checkout_request_license_seat_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_request_license_seats');
    }
};
