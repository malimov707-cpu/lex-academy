<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\EducationCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => EducationCategory::factory(),
            'teacher_id' => User::factory()->teacher(),
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->paragraph(),
            'difficulty' => 'beginner',
            'language' => 'uz-latin',
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
