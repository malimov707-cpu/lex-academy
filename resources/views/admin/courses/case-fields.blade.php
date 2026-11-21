@if ($errors->any())
    <ul class="text-red-700" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
@endif
<label class="flex flex-col gap-1">{{ __('Nomi') }}
    <input name="title" required maxlength="255" value="{{ old('title', $legalCase?->assignment->title) }}" class="rounded-lg border p-2">
</label>
<label class="flex flex-col gap-1">{{ __('Yo‘nalish') }}
    <select name="category" class="rounded-lg border p-2">
        @foreach ($categories as $value => $label)
            <option value="{{ $value }}" @selected(old('category', $legalCase?->category) === $value)>{{ __($label) }}</option>
        @endforeach
    </select>
</label>
@foreach (['situation' => 'Vaziyat', 'facts' => 'Faktlar', 'legal_issue' => 'Huquqiy muammo', 'legislation' => 'Tegishli qonunchilik va tekshirilgan manbalar', 'analysis_questions' => 'Tahlil savollari'] as $field => $label)
    <label class="flex flex-col gap-1">{{ __($label) }}
        <textarea name="{{ $field }}" rows="3" required class="rounded-lg border p-2">{{ old($field, $legalCase?->$field) }}</textarea>
    </label>
@endforeach
<label class="flex flex-col gap-1">{{ __('Baholash mezonlari') }}
    <textarea name="rubric" rows="3" class="rounded-lg border p-2">{{ old('rubric', $legalCase?->assignment->rubric) }}</textarea>
</label>
<div class="grid gap-4 sm:grid-cols-2">
    <label class="flex flex-col gap-1">{{ __('Maksimal ball') }}<input name="max_score" type="number" min="1" max="1000" required value="{{ old('max_score', $legalCase?->assignment->max_score ?? 100) }}" class="rounded-lg border p-2"></label>
    <label class="flex flex-col gap-1">{{ __('Urinishlar') }}<input name="submission_attempts" type="number" min="1" max="5" required value="{{ old('submission_attempts', $legalCase?->assignment->submission_attempts ?? 3) }}" class="rounded-lg border p-2"></label>
</div>
<label class="flex flex-col gap-1">{{ __('Topshirish muddati') }}<input name="deadline" type="datetime-local" value="{{ old('deadline', $legalCase?->assignment->deadline?->format('Y-m-d\TH:i')) }}" class="rounded-lg border p-2"></label>
<label><input name="allow_late_submission" type="checkbox" value="1" @checked(old('allow_late_submission', $legalCase?->assignment->allow_late_submission))> {{ __('Kech topshirishga ruxsat') }}</label>
<label class="flex flex-col gap-1">{{ __('Holat') }}
    <select name="status" class="rounded-lg border p-2">
        @foreach (['draft' => 'Qoralama', 'published' => 'Nashr qilingan', 'archived' => 'Arxiv'] as $value => $label)
            <option value="{{ $value }}" @selected(old('status', $legalCase?->status ?? 'draft') === $value)>{{ __($label) }}</option>
        @endforeach
    </select>
</label>
