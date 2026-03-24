<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('finance_report_enabled')->default(0);
            $table->string('finance_report_email')->nullable();
            $table->dateTime('finance_report_last_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'finance_report_enabled',
                'finance_report_email',
                'finance_report_last_sent_at',
            ]);
        });
    }
};
