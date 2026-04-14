<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status_labels', function (Blueprint $table) {
            $table->boolean('finance_relevant')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('status_labels', function (Blueprint $table) {
            $table->dropColumn('finance_relevant');
        });
    }
};
