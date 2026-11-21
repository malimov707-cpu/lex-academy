<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCourtroomEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('scenario')) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:2000'],
            'body' => ['nullable', 'required_without:file', 'string', 'max:20000'],
            'source_label' => ['required', 'string', 'max:255'],
            'visibility' => ['required', 'in:all,roles'],
            'allowed_role_keys' => ['required_if:visibility,roles', 'array', 'max:12'],
            'allowed_role_keys.*' => ['required', 'string', 'distinct', 'max:80'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,mp3,wav,ogg,mp4,webm', 'extensions:pdf,jpg,jpeg,png,webp,mp3,wav,ogg,mp4,webm', 'max:12288'],
            'educational_use_confirmed' => ['required', 'accepted'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $roles = $this->route('scenario')->versions()->latest('version')->firstOrFail()->roles()->pluck('key')->all();
            if (array_diff($this->input('allowed_role_keys', []), $roles)) {
                $validator->errors()->add('allowed_role_keys', 'Faqat shu ssenariydagi rollarni tanlang.');
            }
        }];
    }
}
