<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('models')->where('require_serial', 1)->update(['require_serial' => 0]);
    }

    public function down(): void
    {
        // Former per-model choices cannot be reconstructed after retirement.
    }
};
