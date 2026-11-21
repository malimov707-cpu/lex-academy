<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourtroomScenarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Teacher, UserRole::AcademyAdmin, UserRole::SuperAdmin) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requiresReference = $this->route('scenario') === null || $this->anyFilled([
            'legal_reference_title', 'legal_reference_url', 'legal_reference_note', 'legal_reference_confirmed',
        ]);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:4000'],
            'legal_area' => ['required', 'string', 'max:80'],
            'case_type' => ['required', 'string', 'max:80'],
            'difficulty' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'estimated_duration_minutes' => ['required', 'integer', 'min:10', 'max:180'],
            'procedure_type' => ['required', 'string', 'max:80'],
            'learning_objectives' => ['required', 'array', 'min:1', 'max:12'],
            'learning_objectives.*' => ['required', 'string', 'max:500'],
            'case_brief' => ['required', 'string', 'max:12000'],
            'case_facts' => ['required', 'string', 'max:12000'],
            'teacher_notes' => ['nullable', 'string', 'max:8000'],
            'evidence_title' => ['required', 'string', 'max:255'],
            'evidence_summary' => ['required', 'string', 'max:2000'],
            'evidence_body' => ['required', 'string', 'max:12000'],
            'evidence_source_label' => ['required', 'string', 'max:255'],
            'legal_reference_title' => [Rule::requiredIf($requiresReference), 'nullable', 'string', 'max:255'],
            'legal_reference_url' => [Rule::requiredIf($requiresReference), 'nullable', 'url:http,https', 'max:2048'],
            'legal_reference_note' => [Rule::requiredIf($requiresReference), 'nullable', 'string', 'max:2000'],
            'legal_reference_confirmed' => [Rule::requiredIf($requiresReference), 'accepted'],
        ];
    }
}
