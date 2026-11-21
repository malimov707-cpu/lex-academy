@extends('layouts.app')

@section('title', __('Baholash'))

@section('content')
<div class="mb-6">
    <h1 class="edu-page-title">{{ __('Baholash') }}</h1>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('Topshiriqlar va test natijalarini kurslar bo‘yicha ko‘rib chiqing.') }}</p>
</div>

<section class="mb-6 edu-panel">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-lg font-semibold">{{ __('Yuborilgan ishlar') }}</h2><p class="text-sm text-slate-500">{{ __('O‘qituvchi tekshiruvini kutayotgan topshiriqlar') }}</p></div>
        <strong class="edu-badge edu-badge-neutral">{{ $pendingSubmissions->count() }}</strong>
    </div>
    <div class="mt-4 overflow-x-auto">
        <table class="edu-table">
            <thead><tr><th>{{ __('Talaba') }}</th><th>{{ __('Topshiriq') }}</th><th>{{ __('Kurs') }}</th><th>{{ __('Yuborilgan') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($pendingSubmissions as $submission)
                    <tr><td>{{ $submission->student->name }}</td><td>{{ $submission->assignment->title }}</td><td>{{ $courses->firstWhere('id', $submission->assignment->course_id)?->title }}</td><td>{{ $submission->submitted_at?->diffForHumans() }}</td><td><a class="edu-btn edu-btn-secondary edu-btn-sm" href="{{ route('admin.courses.assignments.index', $submission->assignment->course_id) }}">{{ __('Ko‘rib chiqish') }} →</a></td></tr>
                @empty
                    <tr><td colspan="5" class="py-5 text-center text-slate-500">{{ __('Tekshiriladigan topshiriq yo‘q.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="mb-6 edu-panel">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-lg font-semibold">{{ __('Yakuniy bahosi kutilayotgan testlar') }}</h2><p class="text-sm text-slate-500">{{ __('Qo‘lda tekshirilishi kerak bo‘lgan test javoblari') }}</p></div>
        <strong class="edu-badge edu-badge-neutral">{{ $pendingQuizzes->count() }}</strong>
    </div>
    <div class="mt-4 overflow-x-auto">
        <table class="edu-table">
            <thead><tr><th>{{ __('Talaba') }}</th><th>{{ __('Test') }}</th><th>{{ __('Kurs') }}</th><th>{{ __('Yuborilgan') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($pendingQuizzes as $attempt)
                    <tr><td>{{ $attempt->student->name }}</td><td>{{ $attempt->quiz->title }}</td><td>{{ $courses->firstWhere('id', $attempt->quiz->course_id)?->title }}</td><td>{{ $attempt->submitted_at?->diffForHumans() }}</td><td><a class="edu-btn edu-btn-secondary edu-btn-sm" href="{{ route('admin.quizzes.results', $attempt->quiz_id) }}">{{ __('Natijalarni ko‘rish') }} →</a></td></tr>
                @empty
                    <tr><td colspan="5" class="py-5 text-center text-slate-500">{{ __('Qo‘lda tekshiriladigan test javobi yo‘q.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="edu-panel">
    <h2 class="text-lg font-semibold">{{ __('Kurs bo‘yicha baholash') }}</h2>
    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($courses as $course)
            <article class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <h3 class="font-semibold">{{ $course->title }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ __(':assignments ta topshiriq · :quizzes ta test', ['assignments' => $course->assignments_count, 'quizzes' => $course->quizzes_count]) }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a class="edu-btn edu-btn-secondary edu-btn-sm" href="{{ route('admin.courses.assignments.index', $course) }}">{{ __('Topshiriqlarni boshqarish') }}</a>
                    <a class="edu-btn edu-btn-secondary edu-btn-sm" href="{{ route('admin.courses.quizzes.index', $course) }}">{{ __('Testlar va natijalar') }}</a>
                </div>
            </article>
        @empty
            <p class="text-sm text-slate-500">{{ __('Sizga biriktirilgan kurslar yo‘q.') }}</p>
        @endforelse
    </div>
</section>
@endsection
