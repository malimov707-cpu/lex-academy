@extends('layouts.app')

@section('title', $course->title.__(' — Topshiriqlar'))

@section('content')
<h1 class="text-2xl font-semibold">{{ $course->title }} {{ __('— topshiriqlar') }}</h1>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 edu-panel">
        <table class="edu-table">
            <thead><tr class="text-left text-[#0f1e3d]/60 dark:text-white/60 border-b"><th class="py-2">{{ __('Topshiriq') }}</th><th>{{ __('Turi') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('Yuborilgan') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($course->assignments as $assignment)
                    <tr class="border-b last:border-0">
                        <td class="py-2 font-medium">{{ $assignment->title }}<details class="mt-2 text-sm font-normal"><summary>{{ __('Tahrirlash') }}</summary><form method="POST" action="{{ route('admin.assignments.update', $assignment) }}" class="mt-3 flex flex-col gap-3">@csrf @method('PUT')
<label>{{ __('Nomi') }}<input name="title" value="{{ $assignment->title }}" required class="block w-full rounded border p-2"></label><label>{{ __('Ko‘rsatmalar') }}<textarea name="instructions" rows="4" class="block w-full rounded border p-2">{{ $assignment->instructions }}</textarea></label><label>{{ __('Baholash mezonlari') }}<textarea name="rubric" rows="3" class="block w-full rounded border p-2">{{ is_array($assignment->rubric) ? json_encode($assignment->rubric, JSON_UNESCAPED_UNICODE) : $assignment->rubric }}</textarea></label>
<label>{{ __('Maksimal ball') }}<input name="max_score" type="number" value="{{ $assignment->max_score }}" min="1" max="1000" required class="block w-full rounded border p-2"></label><label>{{ __('Urinishlar') }}<input name="submission_attempts" type="number" value="{{ $assignment->submission_attempts }}" min="1" max="5" required class="block w-full rounded border p-2"></label><label>{{ __('Deadline') }}<input name="deadline" type="datetime-local" value="{{ $assignment->deadline?->format('Y-m-d\TH:i') }}" class="block w-full rounded border p-2"></label>
<label><input name="allow_late_submission" type="checkbox" value="1" @checked($assignment->allow_late_submission)> {{ __('Kech topshirishga ruxsat') }}</label><button class="self-start rounded bg-blue-700 px-4 py-2 text-white">{{ __('Saqlash') }}</button></form></details></td>
                        <td>{{ __($assignment->type) }}</td>
                        <td>{{ $assignment->deadline?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $assignment->submissions->count() }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('admin.courses.assignments.destroy', $assignment) }}">@csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">{{ __('O\'chir') }}</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-[#0f1e3d]/50 dark:text-white/50">{{ __('Topshiriq yo\'q') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="edu-panel">
        <h2 class="font-semibold">{{ __('Yangi topshiriq') }}</h2>
        <form method="POST" action="{{ route('admin.courses.assignments.store', $course) }}" class="mt-4 space-y-3">
            @csrf
            <input name="title" placeholder="{{ __('Nomi') }}" required class="edu-input">
            <textarea name="instructions" placeholder="{{ __('Ko\'rsatmalar') }}" rows="2" class="edu-input"></textarea>
            <select name="type" class="edu-input">
                <option value="written">{{ __('Yozma') }}</option><option value="legal_case">{{ __('Kazus tahlili') }}</option>
                <option value="research">{{ __('Tadqiqot') }}</option><option value="document_analysis">{{ __('Hujjat tahlili') }}</option>
                <option value="practical">{{ __('Amaliy') }}</option><option value="presentation">{{ __('Prezentatsiya') }}</option>
            </select>
            <input type="datetime-local" name="deadline" class="edu-input">
            <input name="max_score" type="number" value="100" min="1" class="edu-input" placeholder="{{ __('Max ball') }}">
            <textarea name="rubric" placeholder="{{ __('Baholash mezoni (rubric)') }}" rows="2" class="edu-input"></textarea>
            <select name="mentor_id" class="edu-input">
                <option value="">{{ __('Mentor (ixtiyoriy)') }}</option>
                @foreach ($mentors as $mentor)
                    <option value="{{ $mentor->id }}">{{ $mentor->name }}</option>
                @endforeach
            </select>
            <label class="block text-sm">{{ __('Faylning maksimal hajmi (KB)') }}<input name="max_file_size_kb" type="number" min="1" max="20480" value="20480" class="block w-full rounded border p-2"></label>
            <label class="block text-sm">{{ __('Ruxsat etilgan fayl formatlari') }}<select name="allowed_file_formats[]" multiple class="block w-full rounded border p-2">@foreach (['pdf', 'doc', 'docx', 'txt', 'jpg', 'png'] as $format)<option value="{{ $format }}" selected>{{ strtoupper($format) }}</option>@endforeach</select></label>
            <input name="submission_attempts" type="number" value="1" min="1" max="5" class="edu-input">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_late_submission" value="1"> {{ __('Deadline\'dan keyin ham qabul') }}</label>
            <button class="edu-btn edu-btn-primary w-full">{{ __('Yaratish') }}</button>
        </form>
    </div>
</div>
@endsection
