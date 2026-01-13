<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'discipline_id')) {
                $table->unsignedInteger('discipline_id')->nullable()->after('supplier_id');
                $table->foreign('discipline_id')->references('id')->on('disciplines')->nullOnDelete();
            }
        });

        Schema::table('licenses', function (Blueprint $table) {
            if (!Schema::hasColumn('licenses', 'discipline_id')) {
                $table->unsignedInteger('discipline_id')->nullable()->after('category_id');
                $table->foreign('discipline_id')->references('id')->on('disciplines')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'discipline_id')) {
                $table->dropForeign(['discipline_id']);
                $table->dropColumn('discipline_id');
            }
        });

        Schema::table('licenses', function (Blueprint $table) {
            if (Schema::hasColumn('licenses', 'discipline_id')) {
                $table->dropForeign(['discipline_id']);
                $table->dropColumn('discipline_id');
            }
        });
    }
};
