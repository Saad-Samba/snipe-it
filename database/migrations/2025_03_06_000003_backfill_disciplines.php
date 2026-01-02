<?php

use App\Models\CustomField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $disciplineColumn = CustomField::name_to_db_name('Discipline');

        $names = collect();

        if (Schema::hasColumn('assets', $disciplineColumn)) {
            $assetNames = DB::table('assets')
                ->whereNotNull($disciplineColumn)
                ->where($disciplineColumn, '!=', '')
                ->pluck($disciplineColumn);
            $names = $names->merge($assetNames);
        }

        if (Schema::hasColumn('licenses', 'discipline')) {
            $licenseNames = DB::table('licenses')
                ->whereNotNull('discipline')
                ->where('discipline', '!=', '')
                ->pluck('discipline');
            $names = $names->merge($licenseNames);
        }

        $names = $names->filter()->map(fn ($name) => trim($name))->filter()->unique();

        $nameToId = [];
        foreach ($names as $name) {
            $id = DB::table('disciplines')->where('name', $name)->value('id');
            if (! $id) {
                $id = DB::table('disciplines')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $nameToId[$name] = $id;
        }

        if (Schema::hasColumn('assets', $disciplineColumn)) {
            foreach ($nameToId as $name => $id) {
                DB::table('assets')
                    ->where($disciplineColumn, $name)
                    ->update(['discipline_id' => $id]);
            }
        }

        if (Schema::hasColumn('licenses', 'discipline')) {
            foreach ($nameToId as $name => $id) {
                DB::table('licenses')
                    ->where('discipline', $name)
                    ->update(['discipline_id' => $id]);
            }
        }

        if (Schema::hasColumn('licenses', 'discipline')) {
            Schema::table('licenses', function ($table) {
                $table->dropColumn('discipline');
            });
        }
    }

    public function down(): void
    {
        // No down-migration for data backfill; discipline_id columns are removed in previous migration.
    }
};
