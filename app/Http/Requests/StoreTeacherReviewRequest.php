<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTeacherReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ($this->user()?->hasRole(UserRole::Teacher, UserRole::AcademyAdmin, UserRole::SuperAdmin) ?? false)
            && $this->user()->can('update', $this->route('evaluation'));
    }

    protected function prepareForValidation(): void
    {
        $scores = $this->input('criterion_overrides');
        if (is_array($scores)) {
            $this->merge(['criterion_overrides' => array_filter($scores, static fn (mixed $score): bool => $score !== null && $score !== '')]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'changes_requested'])],
            'adjusted_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comment' => ['required', 'string', 'min:10', 'max:5000'],
            'criterion_overrides' => ['nullable', 'array'],
            'criterion_overrides.*' => ['numeric', 'min:0', 'max:100'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $results = $this->route('evaluation')->criterionResults()->with('criterion')->get()->keyBy('evaluation_criterion_id');
            foreach ($this->input('criterion_overrides', []) ?? [] as $id => $score) {
                if (! $results->has($id) || (float) $score > $results[$id]->criterion->max_score) {
                    $validator->errors()->add('criterion_overrides', 'Mezon shu hisobotga tegishli va ball uning chegarasida bo‘lishi kerak.');
                }
            }
        }];
    }
}
