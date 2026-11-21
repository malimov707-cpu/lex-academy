<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVoiceSessionRequest extends FormRequest
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
            'consent' => ['required', 'accepted'],
            'transcript_consent' => ['required', 'boolean'],
            'audio_recording' => ['prohibited'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.speech_recognition' => ['nullable', 'boolean'],
            'capabilities.speech_synthesis' => ['nullable', 'boolean'],
            'capabilities.locale' => ['nullable', 'string', 'max:20'],
        ];
    }
}
