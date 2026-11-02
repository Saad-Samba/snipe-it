<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Manufacturer;
use App\Models\Import;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;

class AssetDisciplineQaSeeder extends Seeder
{
    public function run(): void
    {
        if (!Setting::first()) {
            $this->call(SettingsSeeder::class);
        }

        $company = Company::firstOrCreate(['name' => 'Discipline QA Site']);
        $user = User::firstOrNew(['username' => 'discipline.qa']);
        $user->fill([
            'first_name' => 'Discipline', 'last_name' => 'QA',
            'email' => 'discipline.qa@example.test', 'activated' => 1,
            'company_id' => $company->id, 'locale' => 'en-US',
            'permissions' => json_encode(['superuser' => '1']),
        ]);
        $user->password = Hash::make('Discipline-QA-2026!');
        $user->save();
        $category = Category::firstOrCreate(['name' => 'Discipline QA Family'], ['category_type' => 'asset']);
        $manufacturer = Manufacturer::firstOrCreate(['name' => 'Discipline QA Manufacturer']);
        $model = AssetModel::firstOrCreate(['name' => 'Discipline QA Model'], [
            'category_id' => $category->id, 'manufacturer_id' => $manufacturer->id,
            'model_number' => 'DISC-QA', 'require_serial' => 0,
        ]);
        $status = Statuslabel::firstOrCreate(['name' => 'Discipline QA Ready'], [
            'deployable' => 1, 'pending' => 0, 'archived' => 0,
        ]);
        $discipline = Discipline::firstOrCreate(['name' => 'Discipline QA Hardware']);
        foreach (['QA-DISC-LEGACY' => null, 'QA-DISC-SET' => $discipline->id] as $tag => $disciplineId) {
            Asset::firstOrCreate(['asset_tag' => $tag], [
                'name' => $tag, 'model_id' => $model->id, 'company_id' => $company->id,
                'status_id' => $status->id, 'discipline_id' => $disciplineId,
                'created_by' => $user->id,
            ]);
        }
        File::ensureDirectoryExists(config('app.private_uploads').'/imports');
        foreach (['new-assets', 'legacy-update'] as $sample) {
            $contents = File::get(base_path('docs/qa/asset-discipline/'.$sample.'.csv'));
            $filename = 'qa-discipline-'.$sample.'.csv';
            File::put(config('app.private_uploads').'/imports/'.$filename, $contents);
            $rows = array_map('str_getcsv', explode("\n", trim($contents)));
            Import::firstOrCreate(['file_path' => $filename], [
                'name' => 'Discipline QA '.$sample, 'filesize' => strlen($contents),
                'import_type' => 'asset', 'header_row' => $rows[0],
                'first_row' => $rows[1], 'created_by' => $user->id,
            ]);
        }
    }
}
