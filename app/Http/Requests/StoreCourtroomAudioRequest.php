<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourtroomAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Student;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'mimes:webm,ogg,wav,mp3,mp4,m4a', 'max:8192'],
            'locale' => ['required', 'in:uz,uz-Latn,uz-Cyrl,uz-UZ,ru,ru-RU'],
        ];
    }
}
