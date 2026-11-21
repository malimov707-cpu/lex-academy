@extends('layouts.student')

@section('title', $lesson->title)

@php
    $currentIndex = $lessons->search(fn ($l) => $l->id === $lesson->id);
    $total = $lessons->count();
    $position = $currentIndex === false ? 1 : $currentIndex + 1;
    $done = $progress?->status === 'completed';
@endphp

@section('content')
    @if ($errors->any())
        <div role="alert" class="edu-feedback edu-feedback-error mb-4">{{ $errors->first() }}</div>
    @endif

    {{-- Dars sarlavhasi --}}
    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        <a href="{{ route('student.course', $course) }}" class="inline-flex items-center gap-1 hover:text-[var(--edu-royal)]"
           style="color:var(--edu-text-muted)">
            <span aria-hidden="true">←</span> {{ $course->title }}
        </a>
        <span aria-hidden="true" style="color:var(--edu-text-muted)">/</span>
        <span style="color:var(--edu-text-muted)">{{ $lesson->module->title }}</span>
        <span class="ml-auto edu-badge edu-badge-neutral">
            {{ __(':p / :t dars', ['p' => $position, 't' => $total]) }}
        </span>
    </div>

    <div class="grid gap-5 lg:grid-cols-4">
        {{-- Kurs navigatsiyasi --}}
        <aside class="lg:col-span-1" aria-label="{{ __('Kurs darslari') }}">
            <div class="edu-panel !p-3">
                <p class="mb-2 px-2 text-[0.66rem] font-semibold uppercase tracking-[0.12em]" style="color:var(--edu-gold)">
                    {{ __('Kurs tarkibi') }}
                </p>
                <nav class="space-y-1">
                    @foreach ($lessons as $i => $l)
                        @php $isCurrent = $l->id === $lesson->id; @endphp
                        <a href="{{ route('student.lesson', [$course, $l]) }}"
                           @if ($isCurrent) aria-current="page" @endif
                           class="flex items-start gap-2 rounded-lg px-2.5 py-2 text-sm transition
                                  {{ $isCurrent
                                      ? 'bg-[var(--edu-royal)] font-semibold text-white'
                                      : 'hover:bg-[var(--edu-surface-muted)]' }}">
                            <span class="mt-0.5 shrink-0 text-[0.68rem] tabular-nums {{ $isCurrent ? 'text-white/70' : '' }}"
                                  style="{{ $isCurrent ? '' : 'color:var(--edu-text-muted)' }}">{{ $i + 1 }}</span>
                            <span class="min-w-0 flex-1 leading-snug">{{ $l->title }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        </aside>

        {{-- Dars kontenti --}}
        <article class="lg:col-span-2" aria-label="{{ __('Dars mazmuni') }}">
            <div class="edu-panel !p-6 sm:!p-8">
                <div class="flex items-center gap-2">
                    <span class="text-[0.66rem] font-semibold uppercase tracking-[0.12em]" style="color:var(--edu-gold)">
                        {{ __(':p-dars', ['p' => $position]) }}
                    </span>
                    @if ($done)
                        <span class="edu-badge edu-badge-success">{{ __('Tugallangan') }}</span>
                    @endif
                </div>

                <h1 class="mt-1.5 text-xl font-semibold leading-tight tracking-tight sm:text-2xl">{{ $lesson->title }}</h1>

                @if ($lesson->description)
                    <p class="mt-2 text-sm leading-relaxed" style="color:var(--edu-text-muted)">{{ $lesson->description }}</p>
                @endif

                {{-- O'quv maqsadlari --}}
                @if (! empty($lesson->objectives))
                    <section class="mt-5 rounded-xl border p-4" style="border-color:var(--edu-line);background:var(--edu-surface-muted)"
                             aria-label="{{ __('O‘quv maqsadlari') }}">
                        <p class="text-xs font-semibold uppercase tracking-[0.1em]" style="color:var(--edu-royal)">
                            {{ __('O‘quv maqsadlari') }}
                        </p>
                        <ul class="mt-2 space-y-1.5 text-sm">
                            @foreach ($lesson->objectives as $objective)
                                <li class="flex gap-2">
                                    <span class="mt-[0.35rem] h-1.5 w-1.5 shrink-0 rounded-full" style="background:var(--edu-gold)"></span>
                                    <span class="leading-relaxed">{{ $objective }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Asosiy matn --}}
                <div class="prose-edu mt-6 text-[0.94rem] leading-[1.75]">
                    {!! nl2br(e($lesson->body)) !!}
                </div>

                @include('student.lesson-blocks')

                {{-- Materiallar --}}
                @if ($lesson->materials->isNotEmpty())
                    <section class="mt-7" aria-label="{{ __('Materiallar') }}">
                        <h2 class="text-sm font-semibold">{{ __('Materiallar') }}</h2>
                        <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($lesson->materials as $material)
                                <li>
                                    <a href="{{ $material->file_path ? route('materials.download', $material) : $material->external_url }}"
                                       @unless ($material->file_path) target="_blank" rel="noopener noreferrer" @endunless
                                       class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 text-sm transition hover:bg-[var(--edu-surface-muted)]"
                                       style="border-color:var(--edu-line)">
                                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-md" style="background:var(--edu-surface-muted);color:var(--edu-royal)">
                                            <x-nav-icon name="lesson" style="width:14px;height:14px" />
                                        </span>
                                        <span class="min-w-0 flex-1 truncate">{{ $material->title }}</span>
                                        <span class="shrink-0 text-[0.66rem] uppercase" style="color:var(--edu-text-muted)">{{ $material->type }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Navigatsiya / tugatish --}}
                <div class="mt-8 border-t pt-5" style="border-color:var(--edu-line)">
                    {{-- Bitta qator: oldingi · tugatish · keyingi --}}
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($previousLesson)
                            <a href="{{ route('student.lesson', [$course, $previousLesson]) }}"
                               class="edu-btn edu-btn-ghost edu-btn-sm">← {{ __('Oldingi') }}</a>
                        @endif

                        @if ($done)
                            <span class="edu-badge edu-badge-success">{{ __('✓ Tugallangan') }}</span>
                        @else
                            <form method="POST" action="{{ route('student.lesson.complete', [$course, $lesson]) }}"
                                  class="ml-auto">
                                @csrf
                                @if (! empty($lesson->completion_criteria))
                                    <input type="hidden" name="confirm_criteria" value="1">
                                @endif
                                <button class="edu-btn edu-btn-primary">{{ __('Darsni tugatish') }}</button>
                            </form>
                        @endif

                        @if ($nextLesson)
                            <a href="{{ route('student.lesson', [$course, $nextLesson]) }}"
                               class="edu-btn {{ $done ? 'edu-btn-primary' : 'edu-btn-ghost' }} edu-btn-sm {{ $done ? 'ml-auto' : '' }}">
                                {{ __('Keyingi dars') }} →
                            </a>
                        @endif
                    </div>

                    {{-- Tugatish mezoni (agar mavjud bo'lsa) --}}
                    @if (! $done && ! empty($lesson->completion_criteria))
                        <p class="mt-3 text-[0.7rem]" style="color:var(--edu-text-muted)">
                            {{ __('Tugatish mezoni:') }} {{ implode(' · ', $lesson->completion_criteria) }}
                        </p>
                    @endif
                </div>
            </div>
        </article>

        {{-- Yon panel: AI Ustoz --}}
        <aside class="space-y-4" aria-label="{{ __('Yordam') }}">
            <div class="edu-card p-5"
                 style="background:linear-gradient(150deg,rgb(29 78 216 / .09),rgb(198 161 91 / .12));border-color:rgb(29 78 216 / .18)">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-white/70 text-[var(--edu-royal)]">
                    <x-nav-icon name="tutor" style="width:18px;height:18px" />
                </span>
                <h2 class="mt-3 text-sm font-semibold">{{ __('AI Ustoz') }}</h2>
                <p class="mt-1 text-xs leading-relaxed" style="color:var(--edu-text-muted)">
                    {{ __('Shu dars kontekstida tushuntirish, mashq va kazus tahlili.') }}
                </p>
                <a href="{{ route('student.tutor', [$course, $lesson]) }}"
                   class="edu-btn edu-btn-primary edu-btn-sm mt-3 w-full">{{ __('Suhbatni boshlash') }}</a>
            </div>

            <div class="edu-panel !p-4">
                <h2 class="text-sm font-semibold">{{ __('Dars holati') }}</h2>
                <dl class="mt-2 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <dt style="color:var(--edu-text-muted)">{{ __('Holat') }}</dt>
                        <dd class="font-medium">{{ $done ? __('Tugallangan') : __('O‘rganilmoqda') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt style="color:var(--edu-text-muted)">{{ __('Tartib') }}</dt>
                        <dd class="font-medium tabular-nums">{{ $position }} / {{ $total }}</dd>
                    </div>
                </dl>
                <div class="edu-progress mt-3"><span style="width: {{ $total ? (int) round($position / $total * 100) : 0 }}%"></span></div>
            </div>

            <a href="{{ route('student.assignments') }}" class="edu-card edu-card-hover flex items-center gap-2.5 p-4 text-sm">
                <span class="grid h-8 w-8 place-items-center rounded-lg" style="background:var(--edu-surface-muted);color:var(--edu-royal)">
                    <x-nav-icon name="assignments" style="width:16px;height:16px" />
                </span>
                <span class="flex-1">{{ __('Topshiriqlar') }}</span>
                <span aria-hidden="true" style="color:var(--edu-text-muted)">→</span>
            </a>
        </aside>
    </div>
@endsection
