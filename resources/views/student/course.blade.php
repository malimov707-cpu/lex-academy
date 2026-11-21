@extends('layouts.student')

@section('title', $course->title)

@php
    $totalLessons = $lessons->count();
    $completedCount = $lessons->filter(fn ($l) => ($progress->get($l->id)?->status ?? null) === 'completed')->count();
    $percent = $totalLessons > 0 ? (int) round($completedCount / $totalLessons * 100) : 0;
    $nextLesson = $lessons->first(fn ($l) => ($progress->get($l->id)?->status ?? null) !== 'completed');
@endphp

@section('content')
    {{-- ===== HERO ===== --}}
    <section class="edu-card relative overflow-hidden px-5 py-5 sm:px-7"
             style="background:linear-gradient(135deg,#0f1e3d 0%,#14315f 52%,#1d4ed8 140%);border-color:transparent">
        <div class="relative z-10 flex flex-wrap items-end justify-between gap-5">
            <div class="min-w-0 max-w-2xl">
                <a href="{{ route('student.dashboard') }}"
                   class="inline-flex items-center gap-1 text-[0.72rem] font-medium uppercase tracking-[0.12em] text-white/60 hover:text-white">
                    <span aria-hidden="true">←</span> {{ __('Mening kurslarim') }}
                </a>
                <h1 class="mt-1.5 text-xl font-semibold leading-tight tracking-tight text-white sm:text-2xl">
                    {{ $course->title }}
                </h1>
                <p class="mt-1 text-[0.82rem] text-white/65">
                    {{ $course->category->name }} · {{ $course->teacher->name }}
                    @if ($course->duration_weeks) · {{ __(':n hafta', ['n' => $course->duration_weeks]) }} @endif
                </p>

                <div class="mt-4 max-w-sm">
                    <div class="flex items-center justify-between text-[0.7rem] text-white/70">
                        <span>{{ $completedCount }} / {{ $totalLessons }} {{ __('dars') }}</span>
                        <span class="font-semibold text-white">{{ $percent }}%</span>
                    </div>
                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-white/15">
                        <span class="block h-full rounded-full bg-[var(--edu-gold)] transition-[width] duration-500"
                              style="width: {{ $percent }}%"></span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($nextLesson)
                    <a href="{{ route('student.lesson', [$course, $nextLesson]) }}" class="edu-btn edu-btn-hero">
                        {{ $percent === 0 ? __('Boshlash') : __('Davom ettirish') }} <span aria-hidden="true">→</span>
                    </a>
                @elseif ($totalLessons > 0)
                    <span class="edu-badge rounded-full bg-white/15 px-3 py-1.5 text-white">✓ {{ __('Kurs yakunlandi') }}</span>
                @endif
            </div>
        </div>
        <div aria-hidden="true" class="pointer-events-none absolute -right-14 -top-16 h-56 w-56 rounded-full"
             style="background:radial-gradient(circle,rgba(198,161,91,.26),transparent 70%)"></div>
    </section>

    @if ($course->cover_image)
        <img src="{{ route('courses.cover', $course) }}" alt="{{ $course->title }}"
             class="mt-4 max-h-64 w-full rounded-[var(--edu-radius-lg)] object-cover">
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        {{-- ===== MODULLAR ===== --}}
        <div class="space-y-4 lg:col-span-2">
            @forelse ($modules as $module)
                @php $moduleLessons = $module->lessons->where('status', 'published'); @endphp
                <section class="edu-panel !p-5" aria-label="{{ $module->title }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-semibold leading-snug">{{ $module->title }}</h2>
                            @if ($module->description)
                                <p class="mt-0.5 truncate text-xs" style="color:var(--edu-text-muted)">{{ $module->description }}</p>
                            @endif
                        </div>
                        <span class="edu-badge edu-badge-neutral shrink-0">{{ $moduleLessons->count() }} {{ __('dars') }}</span>
                    </div>

                    <ul class="mt-3 space-y-1.5">
                        @forelse ($moduleLessons as $index => $lesson)
                            @php $p = $progress->get($lesson->id); $isDone = $p?->status === 'completed'; @endphp
                            <li>
                                <a href="{{ route('student.lesson', [$course, $lesson]) }}"
                                   class="group flex items-center gap-3 rounded-lg border px-3.5 py-2.5 text-sm transition hover:border-[var(--edu-royal)]/40 hover:bg-[var(--edu-surface-muted)]"
                                   style="border-color:var(--edu-line)">
                                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-[0.66rem] font-semibold tabular-nums
                                        {{ $isDone ? 'bg-[rgb(16_185_129_/_0.14)] text-emerald-700' : '' }}"
                                          style="{{ $isDone ? '' : 'background:var(--edu-surface-muted);color:var(--edu-text-muted)' }}">
                                        {{ $isDone ? '✓' : $index + 1 }}
                                    </span>
                                    <span class="min-w-0 flex-1 truncate">{{ $lesson->title }}</span>
                                    @if ($lesson->materials->isNotEmpty())
                                        <span class="shrink-0 text-[0.66rem]" style="color:var(--edu-text-muted)">📎 {{ $lesson->materials->count() }}</span>
                                    @endif
                                    <span class="shrink-0 text-xs {{ $isDone ? 'text-emerald-600' : '' }}"
                                          style="{{ $isDone ? '' : 'color:var(--edu-text-muted)' }}">
                                        {{ $isDone ? __('Tugallangan') : '' }}
                                    </span>
                                    <span class="shrink-0 opacity-0 transition group-hover:opacity-100" aria-hidden="true"
                                          style="color:var(--edu-royal)">→</span>
                                </a>
                            </li>
                        @empty
                            <li class="py-3 text-center text-sm" style="color:var(--edu-text-muted)">{{ __('Nashr qilingan dars yo‘q') }}</li>
                        @endforelse
                    </ul>
                </section>
            @empty
                <div class="edu-empty">
                    <x-nav-icon name="courses" class="h-7 w-7 opacity-40" />
                    <strong>{{ __('Modul yo‘q') }}</strong>
                    <span class="text-sm">{{ __('O‘qituvchi hali dars qo‘shmagan.') }}</span>
                </div>
            @endforelse
        </div>

        {{-- ===== YON PANEL ===== --}}
        <aside class="space-y-4">
            @if ($quizzes->isNotEmpty())
                <section class="edu-panel !p-5" aria-label="{{ __('Testlar va imtihonlar') }}">
                    <h2 class="text-sm font-semibold">{{ __('Testlar va imtihonlar') }}</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($quizzes as $quiz)
                            <li>
                                <a href="{{ route('student.quiz.show', [$course, $quiz]) }}"
                                   class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 text-sm transition hover:bg-[var(--edu-surface-muted)]"
                                   style="border-color:var(--edu-line)">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-md" style="background:var(--edu-surface-muted);color:var(--edu-royal)">
                                        <x-nav-icon name="quiz" style="width:15px;height:15px" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium">{{ $quiz->title }}</span>
                                        <span class="block text-[0.68rem]" style="color:var(--edu-text-muted)">
                                            {{ $quiz->kind === 'exam' ? __('Imtihon') : __('Test') }}
                                            @if ($quiz->time_limit_minutes) · {{ __(':n daq', ['n' => $quiz->time_limit_minutes]) }} @endif
                                        </span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <nav class="edu-panel !p-4" aria-label="{{ __('Kurs bo‘limlari') }}">
                <h2 class="mb-2 px-1 text-sm font-semibold">{{ __('Kurs bo‘limlari') }}</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ([
                        ['student.analytics', __('O‘quv natijalari'), 'analytics', true],
                        ['discussions.index', __('Kurs muhokamalari'), 'chat', true],
                        ['student.assignments', __('Topshiriqlar'), 'assignments', false],
                        ['student.cases.index', __('Huquqiy kazuslar'), 'scale', false],
                    ] as [$routeName, $label, $icon, $needsCourse])
                        @continue(! \Illuminate\Support\Facades\Route::has($routeName))
                        <li>
                            <a href="{{ $needsCourse ? route($routeName, $course) : route($routeName) }}"
                               class="flex items-center gap-2.5 rounded-lg px-2 py-2 transition hover:bg-[var(--edu-surface-muted)]">
                                <x-nav-icon :name="$icon" style="width:16px;height:16px;color:var(--edu-royal)" />
                                <span class="flex-1">{{ $label }}</span>
                                <span aria-hidden="true" style="color:var(--edu-text-muted)">→</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="edu-panel !p-5">
                <h2 class="text-sm font-semibold">{{ __('Kurs haqida') }}</h2>
                <p class="mt-1.5 text-xs leading-relaxed" style="color:var(--edu-text-muted)">
                    {{ $course->description ?: __('Tavsif kiritilmagan.') }}
                </p>
                @if (! empty($course->learning_objectives))
                    <ul class="mt-3 space-y-1.5 text-xs">
                        @foreach (array_slice($course->learning_objectives, 0, 4) as $objective)
                            <li class="flex gap-2">
                                <span class="mt-1 h-1 w-1 shrink-0 rounded-full" style="background:var(--edu-gold)"></span>
                                <span style="color:var(--edu-text-muted)">{{ $objective }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>
    </div>
@endsection
