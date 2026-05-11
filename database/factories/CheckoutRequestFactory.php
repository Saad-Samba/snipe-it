<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CheckoutRequestFactory extends Factory
{
    protected $model = CheckoutRequest::class;

    public function definition(): array
    {
        return [
            'requestable_id' => Asset::factory(),
            'requestable_type' => Asset::class,
            'quantity' => 1,
            'reusable_quantity' => 0,
            'procurement_shortfall' => 1,
            'estimated_savings' => 0,
            'reference_price_snapshot' => 0,
            'status' => CheckoutRequest::STATUS_PENDING,
            'user_id' => User::factory(),
        ];
    }

    public function forAsset()
    {
        return $this->state(function (array $attributes) {
            return [
                'requestable_id' => Asset::factory(),
                'requestable_type' => Asset::class,
            ];
        });
    }

    public function forAssetModel()
    {
        return $this->state(function (array $attributes) {
            return [
                'requestable_id' => AssetModel::factory(),
                'requestable_type' => AssetModel::class,
            ];
        });
    }
}
