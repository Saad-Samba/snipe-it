<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('disciplines')) {
            Schema::create('disciplines', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('assets', 'discipline_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->foreignId('discipline_id')->nullable()->after('location_id')->constrained('disciplines')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('licenses', 'discipline_id')) {
            Schema::table('licenses', function (Blueprint $table) {
                $table->foreignId('discipline_id')->nullable()->after('license_name')->constrained('disciplines')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'discipline_id')) {
                $table->dropConstrainedForeignId('discipline_id');
            }
        });

        Schema::table('licenses', function (Blueprint $table) {
            if (Schema::hasColumn('licenses', 'discipline_id')) {
                $table->dropConstrainedForeignId('discipline_id');
            }
        });

        Schema::dropIfExists('disciplines');
    }
};
