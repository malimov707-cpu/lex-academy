<?php

namespace Database\Factories;

use App\Models\LegalSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LegalSource> */
class LegalSourceFactory extends Factory
{
    public function definition(): array
    {
        return ['created_by' => User::factory()->academyAdmin(), 'title' => fake()->sentence(), 'url' => 'https://lex.uz/', 'excerpt' => fake()->paragraph(), 'status' => 'draft'];
    }
}
