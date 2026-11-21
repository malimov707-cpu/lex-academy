<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Services\VirtualCourtroom\GroupCourtroomSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupCourtroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Teacher, UserRole::AcademyAdmin, UserRole::SuperAdmin) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('gradebook_configured') && ! $this->has('rubric.gradebook') && is_array($this->input('rubric'))) {
            $this->merge(['rubric' => [...$this->input('rubric'), 'gradebook' => []]]);
        }
        $quiz = $this->input('quiz');
        if (is_array($quiz)) {
            $this->merge(['quiz' => array_values(array_filter($quiz, fn ($row): bool => ! is_array($row) || filled($row['question'] ?? null)
                || (is_array($row['options'] ?? null) && count(array_filter($row['options'], fn ($value): bool => filled($value))) > 0)))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $roles = implode(',', array_keys(GroupCourtroomSession::ROLES));

        return [
            'group_id' => ['required', 'integer'], 'scenario_id' => ['required', 'integer'], 'creation_key' => ['required', 'uuid'],
            'class_schedule_id' => ['nullable', 'integer'],
            'roles' => ['required', 'array:'.$roles], 'roles.*' => ['nullable', 'integer', 'min:1'],
            'briefs' => ['sometimes', 'array:'.$roles], 'briefs.*' => ['nullable', 'string', 'max:4000'],
            'quiz' => ['sometimes', 'array', 'max:10'], 'quiz.*' => ['array:question,options,correct'],
            'quiz.*.question' => ['required', 'string', 'max:500'], 'quiz.*.options' => ['required', 'array:A,B', 'size:2'],
            'quiz.*.options.*' => ['required', 'string', 'max:250'], 'quiz.*.correct' => ['required', Rule::in(['A', 'B'])],
            'rubric' => ['sometimes', 'array:academic,team_weights,tie_threshold,gradebook'],
            'rubric.academic' => ['sometimes', 'array:active,observer'],
            'rubric.academic.*' => ['required', 'array', 'min:1', 'max:10'],
            'rubric.academic.*.*' => ['required', 'array:label,max'],
            'rubric.academic.*.*.label' => ['required', 'string', 'max:100'],
            'rubric.academic.*.*.max' => ['required', 'integer', 'min:1', 'max:10'],
            'rubric.team_weights' => ['sometimes', 'array:legal,evidence,questions,rebuttal,procedure,closing', 'size:6'],
            'rubric.team_weights.*' => ['required', 'integer', 'min:1', 'max:100'],
            'rubric.tie_threshold' => ['sometimes', 'numeric', 'between:0,100'],
            'rubric.gradebook' => ['sometimes', 'array', 'max:5'],
            'rubric.gradebook.*' => ['required', 'distinct', Rule::in(['attendance', 'classroom_activity', 'simulation_performance', 'individual_role_score', 'teacher_final_grade'])],
        ];
    }
}
