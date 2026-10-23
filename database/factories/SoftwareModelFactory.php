<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\SoftwareModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SoftwareModelFactory extends Factory
{
    protected $model = SoftwareModel::class;

    public function definition(): array
    {
        return [
            'created_by' => User::factory()->superuser(),
            'name' => $this->faker->unique()->words(3, true),
            'category_id' => Category::factory()->forLicenses(),
            'manufacturer_id' => Manufacturer::factory(),
            'active' => true,
        ];
    }
}
