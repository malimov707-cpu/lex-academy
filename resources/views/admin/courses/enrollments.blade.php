@extends('layouts.app')

@section('title', $course->title.__(' — Talabalar'))

@section('content')
<h1 class="text-2xl font-semibold">{{ $course->title }} {{ __('— talabalar') }}</h1>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 edu-panel">
        <table class="edu-table">
            <thead><tr class="text-left text-[#0f1e3d]/60 dark:text-white/60 border-b"><th class="py-2">{{ __('Talaba') }}</th><th>{{ __('Holat') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($course->enrollments as $enrollment)
                    <tr class="border-b last:border-0">
                        <td class="py-2">{{ $enrollment->student->name }} ({{ $enrollment->student->email }})</td>
                        <td>{{ __($enrollment->status) }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('admin.courses.enrollments.destroy', $enrollment) }}">@csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">{{ __('Olib tashla') }}</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-[#0f1e3d]/50 dark:text-white/50">{{ __('Talaba yo\'q') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="edu-panel">
        <h2 class="font-semibold">{{ __('Talaba biriktirish') }}</h2>
        <form method="POST" action="{{ route('admin.courses.enrollments.store', $course) }}" class="mt-4 space-y-3">
            @csrf
            <select name="student_id" required class="edu-input">
                <option value="">{{ __('Talaba') }}</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}">{{ $student->name }}</option>
                @endforeach
            </select>
            <label class="block text-sm">{{ __('Biriktirish holati') }}<select name="status" class="block w-full rounded border p-2"><option value="active">{{ __('Aktiv') }}</option><option value="suspended">{{ __('To‘xtatilgan') }}</option><option value="completed">{{ __('Tugallangan') }}</option></select></label>
            <button class="edu-btn edu-btn-primary w-full">{{ __('Biriktirish') }}</button>
        </form>
    </div>
</div>
@if (auth()->user()->isAdmin())
<form method="POST" action="{{ route('admin.certificates.store', $course) }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-xl bg-white p-5">@csrf
    <label>{{ __('Sertifikat uchun talaba') }}<select name="student_id" class="block rounded border p-2">@foreach ($course->enrollments as $enrollment)<option value="{{ $enrollment->student_id }}">{{ $enrollment->student->name }}</option>@endforeach</select></label>
    <button class="rounded bg-blue-700 px-4 py-2 text-white">{{ __('Talablarni tekshirish va sertifikat berish') }}</button>
</form>
@if ($errors->any())<p class="mt-3 text-red-700" role="alert">{{ $errors->first() }}</p>@endif
@endif
@endsection
