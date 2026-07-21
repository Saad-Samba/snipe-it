<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->boolean('perpetual')->default(false)->after('expiration_date');
        });

        DB::table('licenses')
            ->whereNull('expiration_date')
            ->update(['perpetual' => true]);
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('perpetual');
        });
    }
};
