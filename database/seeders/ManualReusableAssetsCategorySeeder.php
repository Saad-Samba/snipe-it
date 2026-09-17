<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\Seeder;

class ManualReusableAssetsCategorySeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::withoutGlobalScopes()->find(433)
            ?? User::withoutGlobalScopes()->where('permissions', 'like', '%"superuser":"1"%')->orderBy('id')->first()
            ?? User::withoutGlobalScopes()->first();

        if (! $creator) {
            $creator = User::factory()->superuser()->create([
                'first_name' => 'Manual',
                'last_name' => 'Seeder',
                'username' => 'manual-reuse-seeder',
                'email' => 'manual-reuse-seeder@example.com',
                'company_id' => null,
            ]);
        }

        $company = Company::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'Manual QA Reuse Company'],
            ['created_by' => $creator->id]
        );

        $assetCategory = Category::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'QA Reuse Family Alpha', 'category_type' => 'asset'],
            [
                'created_by' => $creator->id,
                'checkin_email' => 0,
                'require_acceptance' => 0,
                'use_default_eula' => 0,
                'tag_color' => '#1f6f8b',
                'notes' => 'Deterministic category used to validate Reusable Assets rollup behavior.',
            ]
        );

        $otherAssetCategory = Category::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'QA Reuse Family Beta', 'category_type' => 'asset'],
            [
                'created_by' => $creator->id,
                'checkin_email' => 0,
                'require_acceptance' => 0,
                'use_default_eula' => 0,
                'tag_color' => '#6a4c93',
                'notes' => 'Secondary category used to confirm category scoping in rollup links.',
            ]
        );

        $consumableCategory = Category::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'QA Reuse Non-Asset Category', 'category_type' => 'consumable'],
            [
                'created_by' => $creator->id,
                'checkin_email' => 0,
                'require_acceptance' => 0,
                'use_default_eula' => 0,
                'tag_color' => '#708090',
                'notes' => 'Non-asset category used to confirm Reusable Assets stays blank outside asset families.',
            ]
        );

        $deployable = Statuslabel::query()->firstOrCreate(
            ['name' => 'Manual QA Reuse Deployable'],
            [
                'deployable' => 1,
                'pending' => 0,
                'archived' => 0,
                'default_label' => 0,
                'created_by' => $creator->id,
                'notes' => 'Deployable status for reusable asset rollup QA.',
            ]
        );

        $archived = Statuslabel::query()->firstOrCreate(
            ['name' => 'Manual QA Reuse Archived'],
            [
                'deployable' => 0,
                'pending' => 0,
                'archived' => 1,
                'default_label' => 0,
                'created_by' => $creator->id,
                'notes' => 'Archived status for reusable asset rollup QA.',
            ]
        );

        $undeployable = Statuslabel::query()->firstOrCreate(
            ['name' => 'Manual QA Reuse Undeployable'],
            [
                'deployable' => 0,
                'pending' => 0,
                'archived' => 0,
                'default_label' => 0,
                'created_by' => $creator->id,
                'notes' => 'Undeployable status for reusable asset rollup QA.',
            ]
        );

        $modelA = $this->upsertModel('QA Reuse Model Alpha-1', $assetCategory->id, $creator->id);
        $modelB = $this->upsertModel('QA Reuse Model Alpha-2', $assetCategory->id, $creator->id);
        $modelOther = $this->upsertModel('QA Reuse Model Beta-1', $otherAssetCategory->id, $creator->id);

        $this->upsertAsset('QA-REUSE-ALPHA-001', 'QA Reuse Alpha Deployable 1', $modelA->id, $deployable->id, $company->id, $creator->id);
        $this->upsertAsset('QA-REUSE-ALPHA-002', 'QA Reuse Alpha Deployable 2', $modelA->id, $deployable->id, $company->id, $creator->id);
        $this->upsertAsset('QA-REUSE-ALPHA-003', 'QA Reuse Alpha Deployable 3', $modelB->id, $deployable->id, $company->id, $creator->id);
        $this->upsertAsset('QA-REUSE-ALPHA-ARCH-001', 'QA Reuse Alpha Archived', $modelA->id, $archived->id, $company->id, $creator->id);
        $this->upsertAsset('QA-REUSE-ALPHA-UND-001', 'QA Reuse Alpha Undeployable', $modelB->id, $undeployable->id, $company->id, $creator->id);
        $this->upsertAsset('QA-REUSE-BETA-001', 'QA Reuse Beta Deployable', $modelOther->id, $deployable->id, $company->id, $creator->id);

        $this->command?->info('Manual reusable assets category dataset is ready.');
        $this->command?->line('Category to validate: QA Reuse Family Alpha');
        $this->command?->line('Expected Total Assets: 5');
        $this->command?->line('Expected Reusable Assets: 3');
        $this->command?->line('Prefilter asset tags: QA-REUSE-ALPHA-001, QA-REUSE-ALPHA-002, QA-REUSE-ALPHA-003');
        $this->command?->line('Non-reusable controls: QA-REUSE-ALPHA-ARCH-001, QA-REUSE-ALPHA-UND-001');
    }

    private function upsertModel(string $name, int $categoryId, int $createdBy): AssetModel
    {
        return AssetModel::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name],
            [
                'category_id' => $categoryId,
                'created_by' => $createdBy,
                'require_serial' => 0,
                'notes' => 'Deterministic model for reusable asset rollup QA.',
            ]
        );
    }

    private function upsertAsset(
        string $assetTag,
        string $name,
        int $modelId,
        int $statusId,
        ?int $companyId,
        int $createdBy
    ): Asset {
        $asset = Asset::withoutGlobalScopes()->firstOrNew(['asset_tag' => $assetTag]);
        $asset->name = $name;
        $asset->asset_tag = $assetTag;
        $asset->model_id = $modelId;
        $asset->status_id = $statusId;
        $asset->company_id = $companyId;
        $asset->created_by = $createdBy;
        $asset->requestable = 0;
        $asset->notes = 'Deterministic asset for reusable asset rollup QA.';
        $asset->save();

        return $asset;
    }
}
