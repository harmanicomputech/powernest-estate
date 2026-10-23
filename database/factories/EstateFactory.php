<?php

namespace Database\Factories;

use App\Models\Estate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Estate>
 */
class EstateFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' Gardens';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'code' => strtoupper(Str::random(6)),
            'address' => fake()->streetAddress(),
            'city' => 'Lagos',
            'state' => 'Lagos',
            'timezone' => 'Africa/Lagos',
            'status' => 'active',
            'settings' => [],
        ];
    }
}
