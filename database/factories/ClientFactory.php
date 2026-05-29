<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            // Default to the owner's Self employer when nothing else is
            // specified. The closure runs after `owner_id` is resolved by
            // Laravel's factory ordering, so we can look the user up here.
            'employer_id' => function (array $attrs) {
                $ownerId = $attrs['owner_id'] ?? null;
                return $ownerId
                    ? User::find($ownerId)?->selfEmployer()->id
                    : null;
            },
            'legal_name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'notes' => null,
        ];
    }
}
