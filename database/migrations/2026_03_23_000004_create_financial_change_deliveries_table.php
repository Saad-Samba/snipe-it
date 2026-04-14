<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_change_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('financial_change_event_id');
            $table->unsignedInteger('user_id');
            $table->dateTime('reported_at');
            $table->timestamps();

            $table->unique(['financial_change_event_id', 'user_id'], 'financial_change_deliveries_unique');
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_change_deliveries');
    }
};
