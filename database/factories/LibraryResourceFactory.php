<?php

namespace Database\Factories;

use App\Models\LibraryResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LibraryResource> */
class LibraryResourceFactory extends Factory
{
    public function definition(): array
    {
        return ['catalog_key' => fake()->uuid(), 'title' => fake()->sentence(3), 'type' => 'code', 'topic' => 'Fuqarolik huquqi',
            'description' => fake()->paragraph(), 'url' => 'https://lex.uz/docs/-111189', 'publisher' => 'LexUZ', 'language' => 'uz-latin', 'status' => 'published', 'access' => 'open'];
    }
}
