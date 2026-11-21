<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSimulationSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Student;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scenario_id' => ['required', 'integer', 'exists:simulation_scenarios,id'],
            'role_key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'captions' => ['nullable', 'boolean'],
            'auto_camera' => ['nullable', 'boolean'],
        ];
    }
}
