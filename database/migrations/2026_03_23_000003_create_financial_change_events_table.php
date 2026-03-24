<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_change_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('asset_id');
            $table->string('event_type');
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('previous_status_id')->nullable();
            $table->unsignedInteger('new_status_id')->nullable();
            $table->unsignedInteger('previous_company_id')->nullable();
            $table->unsignedInteger('new_company_id')->nullable();
            $table->dateTime('effective_at');
            $table->unsignedInteger('changed_by')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'company_id']);
            $table->index(['event_type', 'previous_company_id']);
            $table->index(['event_type', 'new_company_id']);
            $table->index('effective_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_change_events');
    }
};
