<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionActionRequest extends FormRequest
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
            'action_type' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'target_role_key' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]{0,59}$/'],
            'content' => ['nullable', 'string', 'max:4000'],
            'evidence_id' => ['nullable', 'integer'],
            'idempotency_key' => ['required', 'uuid'],
            'input_type' => ['nullable', Rule::in(['text', 'voice'])],
        ];
    }
}
