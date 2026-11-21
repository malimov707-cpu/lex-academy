<?php

namespace Database\Factories;

use App\Models\ClassSchedule;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClassSchedule> */
class ClassScheduleFactory extends Factory
{
    public function definition(): array
    {
        return ['course_id' => Course::factory()->published(),
            'teacher_id' => fn (array $attributes): int => Course::findOrFail($attributes['course_id'])->teacher_id,
            'title' => fake()->sentence(3), 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()];
    }
}
