<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->catchPhrase(),
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::factory()->superuser(),
        ];
    }
}
