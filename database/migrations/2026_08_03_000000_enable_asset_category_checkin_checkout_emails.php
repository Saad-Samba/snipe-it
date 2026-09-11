<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->where('category_type', 'asset')
            ->update(['checkin_email' => true]);
    }

    public function down(): void
    {
        // Existing per-category values cannot be reconstructed safely.
    }
};
