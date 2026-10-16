<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offboarding_report_runs', function (Blueprint $table) {
            $table->id();
            $table->char('source_hash', 64)->unique();
            $table->string('source_filename');
            $table->string('status', 32)->default('reviewed');
            $table->json('summary')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('offboarding_report_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offboarding_report_run_id')
                ->constrained('offboarding_report_runs')
                ->cascadeOnDelete();
            $table->string('mode', 16);
            $table->string('intended_recipient');
            $table->string('delivery_recipient');
            $table->string('status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(
                ['offboarding_report_run_id', 'mode', 'intended_recipient'],
                'offboarding_delivery_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_report_deliveries');
        Schema::dropIfExists('offboarding_report_runs');
    }
};
