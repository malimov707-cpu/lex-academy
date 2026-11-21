<?php

namespace Database\Factories;

use App\Models\LibraryBookmark;
use App\Models\LibraryResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LibraryBookmark> */
class LibraryBookmarkFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->student(), 'library_resource_id' => LibraryResource::factory(), 'state' => 'saved'];
    }
}
