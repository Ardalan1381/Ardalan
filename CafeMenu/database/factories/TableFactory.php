<?php

namespace Database\Factories;

use App\Models\Table;
use App\Models\TableSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    protected $model = Table::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_section_id' => TableSection::factory(),
            'name' => 'Table '.fake()->unique()->numberBetween(1, 999999),
            'capacity' => fake()->numberBetween(2, 8),
            'status' => 'available',
            'is_active' => true,
        ];
    }
}
