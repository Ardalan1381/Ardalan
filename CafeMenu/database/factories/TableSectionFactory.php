<?php

namespace Database\Factories;

use App\Models\TableSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSection>
 */
class TableSectionFactory extends Factory
{
    protected $model = TableSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
