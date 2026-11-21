<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\EducationGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EducationGroup> */
class EducationGroupFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(2, true), 'course_id' => Course::factory()];
    }
}
