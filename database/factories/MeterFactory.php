<?php

namespace Database\Factories;

use App\Models\Meter;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meter>
 */
class MeterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'estate_id' => fn (array $attributes) => Property::find($attributes['property_id'])->estate_id,
            'meter_number' => fake()->unique()->numerify('0#########'),
            'label' => 'Home',
            'validated_at' => now(),
            'status' => 'active',
        ];
    }
}
