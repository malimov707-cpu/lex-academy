<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LegalCase> */
class LegalCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assignment_id' => function (): int {
                $course = Course::factory()->published()->create();

                return Assignment::query()->create([
                    'course_id' => $course->id, 'teacher_id' => $course->teacher_id,
                    'title' => fake()->sentence(3), 'type' => 'legal_case',
                ])->id;
            },
            'category' => 'civil', 'situation' => fake()->paragraph(),
            'facts' => fake()->paragraph(), 'legal_issue' => fake()->sentence(),
            'legislation' => 'O‘qituvchi tekshirgan manba: Fuqarolik kodeksi.',
            'analysis_questions' => 'Qaysi faktlar huquqiy ahamiyatga ega?',
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'published']);
    }
}
