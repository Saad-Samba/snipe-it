<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\Manufacturer;
use App\Models\Statuslabel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates an isolated, repeatable catalogue workload for profiling the model API.
 *
 * Run only in local/testing/develop after explicitly setting MODEL_PERF_SEED_ENABLED=true.
 * MODEL_PERF_SEED_COUNT controls the number of models (default 1,400).
 */
class ModelApiPerformanceSeeder extends Seeder
{
    private const CATEGORY_NAME = 'QA Model API Performance';
    private const FIELDSET_NAME = 'QA Model API Performance Specifications';
    private const MODEL_PREFIX = 'QA-MODEL-PERF-';
    private const ASSET_PREFIX = 'QA-ASSET-PERF-';

    public function run(): void
    {
        if (
            ! app()->environment(['local', 'testing', 'develop'])
            || ! filter_var(env('MODEL_PERF_SEED_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
        ) {
            throw new RuntimeException(
                'Refusing to seed the model API performance workload. Use a local/testing/develop app and set MODEL_PERF_SEED_ENABLED=true.'
            );
        }

        $count = max(1, (int) env('MODEL_PERF_SEED_COUNT', 1400));
        $admin = User::where('permissions->superuser', '1')->first();

        if (! $admin) {
            throw new RuntimeException('Seed the local application and create a superuser before running this seeder.');
        }

        $readyStatus = Statuslabel::where('deployable', 1)->where('pending', 0)->where('archived', 0)->first();
        $archivedStatus = Statuslabel::where('deployable', 0)->where('pending', 0)->where('archived', 1)->first();

        if (! $readyStatus || ! $archivedStatus) {
            throw new RuntimeException('Ready-to-deploy and archived status labels are required. Run the normal local seeders first.');
        }

        $category = Category::where('name', self::CATEGORY_NAME)->first();
        if (! $category) {
            $category = Category::factory()->create([
                'name' => self::CATEGORY_NAME,
                'category_type' => 'asset',
                'created_by' => $admin->id,
                'notes' => 'Isolated synthetic data for model API performance testing.',
            ]);
        }

        $fieldset = CustomFieldset::firstOrCreate(['name' => self::FIELDSET_NAME]);
        $specificationFields = CustomField::query()
            ->whereIn('name', ['IMEI', 'Phone Number', 'RAM', 'CPU', 'MAC Address'])
            ->where('is_unique', 0)
            ->where('field_encrypted', 0)
            ->orderBy('id')
            ->get();

        if ($specificationFields->count() !== 5) {
            throw new RuntimeException('The local base custom-field seeder must provide five non-sensitive model specification fields.');
        }

        for ($index = 1; $index <= 4; $index++) {
            $specificationFields->push(CustomField::firstOrCreate(
                ['name' => 'QA Performance Attribute '.$index],
                [
                    'format' => 'ANY',
                    'element' => 'text',
                    'show_in_requestable_list' => 1,
                    'field_encrypted' => 0,
                    'is_unique' => 0,
                ]
            ));
        }

        foreach ($specificationFields as $index => $field) {
            $fieldset->fields()->syncWithoutDetaching([
                $field->id => ['order' => $index + 1, 'required' => false],
            ]);
        }

        $fieldset->load('fields');

        $category->fieldset_id = $fieldset->id;
        $category->save();

        $specificationFields = $fieldset->fields
            ->filter(fn (CustomField $field) => ! $field->is_unique && ! $field->field_encrypted)
            ->values();

        $manufacturerIds = Manufacturer::query()->orderBy('id')->limit(12)->pluck('id')->all();
        if (count($manufacturerIds) === 0) {
            throw new RuntimeException('At least one manufacturer is required. Run the normal local seeders first.');
        }

        DB::transaction(function () use ($count, $admin, $category, $readyStatus, $archivedStatus, $manufacturerIds, $specificationFields): void {
            $existingModelIds = AssetModel::query()
                ->where('category_id', $category->id)
                ->where('model_number', 'like', self::MODEL_PREFIX.'%')
                ->pluck('id');

            // Keep reruns idempotent and make the complete request workload visible
            // to the local superuser used for profiling the request calculation page.
            CheckoutRequest::query()
                ->where('requestable_type', AssetModel::class)
                ->whereIn('requestable_id', $existingModelIds)
                ->update(['user_id' => $admin->id]);
            DB::table('models_custom_fields')->whereIn('asset_model_id', $existingModelIds)->delete();

            $specificationValues = [];
            for ($number = 1; $number <= $count; $number++) {
                $modelNumber = self::MODEL_PREFIX.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
                $model = AssetModel::firstOrCreate(
                    ['model_number' => $modelNumber],
                    [
                        'name' => 'QA Performance Model '.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                        'category_id' => $category->id,
                        'manufacturer_id' => $manufacturerIds[($number - 1) % count($manufacturerIds)],
                        'created_by' => $admin->id,
                        'reference_price' => 300 + (($number * 37) % 4700),
                        'min_amt' => 1 + ($number % 4),
                        'eol' => 24 + (($number % 4) * 12),
                        'require_serial' => 1,
                        'notes' => 'Synthetic model for the local model API performance workload.',
                    ]
                );

                foreach ($specificationFields as $field) {
                    $specificationValues[] = [
                        'asset_model_id' => $model->id,
                        'custom_field_id' => $field->id,
                        'default_value' => $this->specificationValue($field, $number),
                    ];
                }

                // Each model has a changing inventory shape: 0-3 ready, one assigned
                // item due back, and every fourth model also has an archived item.
                $readyCount = $number % 4;
                for ($assetNumber = 1; $assetNumber <= $readyCount; $assetNumber++) {
                    $this->firstOrCreateAsset($model, $admin, $readyStatus, $assetNumber, null, null, null);
                }

                $this->firstOrCreateAsset(
                    $model,
                    $admin,
                    $readyStatus,
                    4,
                    $admin->id,
                    User::class,
                    Carbon::now()->addDays(($number % 45) + 1)->toDateString()
                );

                if ($number % 4 === 0) {
                    $this->firstOrCreateAsset($model, $admin, $archivedStatus, 5, null, null, null);
                }

                $requestIdentity = [
                    'requestable_id' => $model->id,
                    'requestable_type' => AssetModel::class,
                    'user_id' => $admin->id,
                ];
                if (! CheckoutRequest::query()->where($requestIdentity)->exists()) {
                    CheckoutRequest::query()->forceCreate($requestIdentity + [
                        'quantity' => 1 + ($number % 6),
                        'needed_by_date' => Carbon::now()->addDays(($number % 60) + 1)->toDateString(),
                        'status' => CheckoutRequest::STATUS_PENDING,
                        'reusable_quantity' => 0,
                        'due_back_before_needed_by_quantity' => 0,
                        'potentially_coverable_quantity' => 0,
                        'procurement_shortfall' => 1 + ($number % 6),
                        'estimated_savings' => 0,
                        'reference_price_snapshot' => $model->reference_price,
                    ]);
                }
            }

            foreach (array_chunk($specificationValues, 500) as $valueChunk) {
                DB::table('models_custom_fields')->insert($valueChunk);
            }
        });

        $this->command?->info(sprintf(
            'Model API workload ready: category_id=%d, models=%d, generated assets/requests are prefixed %s / %s.',
            $category->id,
            $count,
            self::ASSET_PREFIX,
            self::MODEL_PREFIX
        ));
    }

    private function firstOrCreateAsset(
        AssetModel $model,
        User $admin,
        Statuslabel $status,
        int $slot,
        ?int $assignedTo,
        ?string $assignedType,
        ?string $expectedCheckin
    ): void {
        $assetTag = self::ASSET_PREFIX.$model->id.'-'.$slot;

        Asset::query()->firstOrCreate(
            ['asset_tag' => $assetTag],
            [
                'name' => $model->name.' inventory '.$slot,
                'model_id' => $model->id,
                'status_id' => $status->id,
                'created_by' => $admin->id,
                'assigned_to' => $assignedTo,
                'assigned_type' => $assignedType,
                'expected_checkin' => $expectedCheckin,
                'requestable' => 1,
                'serial' => 'QA-SERIAL-'.$model->id.'-'.$slot,
                'purchase_cost' => $model->reference_price,
                'purchase_date' => Carbon::now()->subMonths(6)->toDateString(),
            ]
        );
    }

    private function specificationValue(CustomField $field, int $number): string
    {
        return match (strtolower($field->name)) {
            'ram' => [8, 16, 32, 64][$number % 4].' GB',
            'cpu' => ['Intel Core i5', 'Intel Core i7', 'AMD Ryzen 5', 'Apple M3'][$number % 4],
            'mac address' => sprintf('02:00:%02X:%02X:%02X:%02X', ($number >> 24) & 0xff, ($number >> 16) & 0xff, ($number >> 8) & 0xff, $number & 0xff),
            default => 'QA value '.($number % 5 + 1),
        };
    }
}
