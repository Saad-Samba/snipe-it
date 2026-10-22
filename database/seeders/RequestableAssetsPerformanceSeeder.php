<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Manufacturer;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\Seeder;

class RequestableAssetsPerformanceSeeder extends Seeder
{
    private const MODEL_COUNT = 600;

    public function run(): void
    {
        $admin = User::withoutGlobalScopes()->where('username', 'demo-GSA')->firstOrFail();
        $readyStatus = Statuslabel::withoutGlobalScopes()->where('name', 'Ready to Deploy')->firstOrFail();
        $pendingStatus = Statuslabel::withoutGlobalScopes()->where('name', 'In Transfer')->firstOrFail();
        $categories = Category::withoutGlobalScopes()
            ->where('category_type', 'asset')
            ->orderBy('id')
            ->get();
        $manufacturer = Manufacturer::withoutGlobalScopes()->orderBy('id')->firstOrFail();

        $companies = $this->seedCompanies($admin);
        $disciplines = $this->seedDisciplines($admin);

        for ($number = 1; $number <= self::MODEL_COUNT; $number++) {
            $model = AssetModel::withoutGlobalScopes()->updateOrCreate(
                ['name' => sprintf('PERF Catalogue Model %03d', $number)],
                [
                    'model_number' => sprintf('PERF-%04d', $number),
                    'category_id' => $categories[($number - 1) % $categories->count()]->id,
                    'manufacturer_id' => $manufacturer->id,
                    'reference_price' => 100 + $number,
                    'require_serial' => 1,
                    'obsolete' => 0,
                    'created_by' => $admin->id,
                    'notes' => 'Deterministic catalogue record for requestable-assets performance profiling.',
                ]
            );

            $company = $companies[($number - 1) % count($companies)];
            $discipline = $disciplines[($number - 1) % count($disciplines)];

            $this->upsertAsset($number, 'AVAILABLE-A', $model, $readyStatus, $company, $discipline, $admin);
            $this->upsertAsset($number, 'AVAILABLE-B', $model, $readyStatus, $company, $discipline, $admin);
            $this->upsertAsset($number, 'AVAILABLE-C', $model, $readyStatus, $company, $discipline, $admin);
            $this->upsertAsset($number, 'PENDING', $model, $pendingStatus, $company, $discipline, $admin);
            $this->upsertAsset($number, 'ASSIGNED', $model, $readyStatus, $company, $discipline, $admin, true);
        }

        $this->command?->info(sprintf(
            'Requestable-assets benchmark dataset ready: %d PERF models, %d companies, %d disciplines, %d PERF assets.',
            self::MODEL_COUNT,
            count($companies),
            count($disciplines),
            self::MODEL_COUNT * 5
        ));
    }

    private function seedCompanies(User $admin): array
    {
        $companies = Company::withoutGlobalScopes()->orderBy('id')->get()->all();

        for ($number = count($companies) + 1; $number <= 25; $number++) {
            $companies[] = Company::withoutGlobalScopes()->updateOrCreate(
                ['name' => sprintf('PERF Destination %02d', $number)],
                ['created_by' => $admin->id, 'notes' => 'Performance benchmark destination site.']
            );
        }

        return $companies;
    }

    private function seedDisciplines(User $admin): array
    {
        $disciplines = Discipline::withoutGlobalScopes()->orderBy('id')->get()->all();

        for ($number = count($disciplines) + 1; $number <= 20; $number++) {
            $disciplines[] = Discipline::withoutGlobalScopes()->updateOrCreate(
                ['name' => sprintf('PERF Discipline %02d', $number)],
                ['created_by' => $admin->id, 'notes' => 'Performance benchmark discipline.']
            );
        }

        return $disciplines;
    }

    private function upsertAsset(
        int $modelNumber,
        string $suffix,
        AssetModel $model,
        Statuslabel $status,
        Company $company,
        Discipline $discipline,
        User $admin,
        bool $assigned = false
    ): void {
        $tag = sprintf('PERF-%04d-%s', $modelNumber, $suffix);

        Asset::withoutGlobalScopes()->updateOrCreate(
            ['asset_tag' => $tag],
            [
                'name' => $tag,
                'serial' => 'SN-'.$tag,
                'model_id' => $model->id,
                'status_id' => $status->id,
                'company_id' => $company->id,
                'discipline_id' => $discipline->id,
                'created_by' => $admin->id,
                'assigned_to' => $assigned ? $admin->id : null,
                'assigned_type' => $assigned ? User::class : null,
                'requestable' => 1,
                'notes' => 'Deterministic asset for requestable-assets performance profiling.',
            ]
        );
    }
}
