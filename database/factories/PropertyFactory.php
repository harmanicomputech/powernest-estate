<?php

namespace Database\Factories;

use App\Enums\OccupancyStatus;
use App\Enums\PropertyType;
use App\Models\Estate;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'estate_id' => Estate::factory(),
            'identifier' => strtoupper(fake()->unique()->bothify('?-##')),
            'type' => PropertyType::House,
            'occupancy_status' => OccupancyStatus::Vacant,
        ];
    }
}
