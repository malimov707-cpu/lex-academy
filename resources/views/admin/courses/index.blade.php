@extends('layouts.app')

@section('title', __('Kurslar'))

@php use Illuminate\Support\Facades\Route; @endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ __('Kurslar') }}</h1>
            <p class="mt-0.5 text-sm" style="color:var(--edu-text-muted)">{{ __('Kurs yarating, tuzilmasi va nashrini boshqaring.') }}</p>
        </div>
        <span class="edu-badge edu-badge-neutral">{{ __('Jami') }}: {{ $courses->total() }}</span>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        {{-- ===== KURSLAR RO'YXATI ===== --}}
        <div class="min-w-0 xl:col-span-2">
            <div class="edu-panel !p-0">
                <ul class="divide-y" style="border-color:var(--edu-line)">
                    @forelse ($courses as $course)
                        <li class="p-4 transition hover:bg-[var(--edu-surface-muted)]" style="border-color:var(--edu-line)">
                            <div class="flex items-start gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl"
                                      style="background:rgb(29 78 216 / .1);color:var(--edu-royal)">
                                    <x-nav-icon name="courses" style="width:18px;height:18px" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <a href="{{ route('admin.course-settings.edit', $course) }}"
                                           class="min-w-0 flex-1 truncate font-semibold leading-snug hover:text-[var(--edu-royal)]">{{ $course->title }}</a>
                                        <span class="edu-badge shrink-0 {{ $course->status === 'published' ? 'edu-badge-success' : ($course->status === 'draft' ? 'edu-badge-neutral' : 'edu-badge-warning') }}">
                                            {{ __($course->status) }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs" style="color:var(--edu-text-muted)">
                                        {{ $course->category->name }} · {{ $course->teacher->name }}
                                        @if ($course->duration_weeks) · {{ __(':n hafta', ['n' => $course->duration_weeks]) }} @endif
                                    </p>

                                    {{-- Amallar — ikonka + label, ixcham --}}
                                    <div class="mt-2 flex flex-wrap items-center gap-1">
                                        <a href="{{ route('admin.courses.modules.index', $course) }}"
                                           class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[0.7rem] transition hover:bg-[var(--edu-surface-muted)]"
                                           style="color:var(--edu-royal)">
                                            <x-nav-icon name="module" style="width:14px;height:14px" /> {{ __('Tuzilma') }}
                                        </a>
                                        <a href="{{ route('admin.courses.assignments.index', $course) }}"
                                           class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[0.7rem] transition hover:bg-[var(--edu-surface-muted)]"
                                           style="color:var(--edu-royal)">
                                            <x-nav-icon name="assignments" style="width:14px;height:14px" /> {{ __('Topshiriqlar') }}
                                        </a>
                                        <a href="{{ route('admin.courses.quizzes.index', $course) }}"
                                           class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[0.7rem] transition hover:bg-[var(--edu-surface-muted)]"
                                           style="color:var(--edu-royal)">
                                            <x-nav-icon name="quiz" style="width:14px;height:14px" /> {{ __('Testlar') }}
                                        </a>
                                        <a href="{{ route('admin.courses.enrollments.index', $course) }}"
                                           class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[0.7rem] transition hover:bg-[var(--edu-surface-muted)]"
                                           style="color:var(--edu-royal)">
                                            <x-nav-icon name="users" style="width:14px;height:14px" /> {{ __('Talabalar') }}
                                        </a>
                                        @if (Route::has('admin.teacher-ai.index'))
                                            <a href="{{ route('admin.teacher-ai.index', $course) }}"
                                               class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[0.7rem] transition hover:bg-[var(--edu-surface-muted)]"
                                               style="color:var(--edu-royal)">
                                                <x-nav-icon name="tutor" style="width:14px;height:14px" /> {{ __("AI") }}
                                            </a>
                                        @endif
                                        @if (Route::has('admin.courses.cases.index'))
                                            <a href="{{ route('admin.courses.cases.index', $course) }}"
                                               class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[0.7rem] transition hover:bg-[var(--edu-surface-muted)]"
                                               style="color:var(--edu-royal)">
                                                <x-nav-icon name="scale" style="width:14px;height:14px" /> {{ __('Kazuslar') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                {{-- Nashr / arxiv / o'chirish --}}
                                <div class="flex shrink-0 flex-col items-end gap-1.5">
                                    @if ($course->status !== 'published')
                                        <form method="POST" action="{{ route('admin.courses.publish', $course) }}">@csrf @method('PATCH')
                                            <button class="edu-btn edu-btn-sm" style="background:rgb(16 185 129 / .12);color:#047857">
                                                {{ __('Nashr') }}
                                            </button>
                                        </form>
                                    @endif
                                    <details class="relative shrink-0">
                                        <summary class="edu-icon-button !h-7 !w-7 cursor-pointer text-xs leading-none"
                                                 aria-label="{{ __('Boshqa amallar') }}">
                                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="width:14px;height:14px">
                                                <circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/>
                                            </svg>
                                        </summary>
                                        <div class="edu-card absolute right-0 z-30 mt-1 w-40 p-1.5 text-sm shadow-[var(--edu-shadow-lg)]">
                                            <form method="POST" action="{{ route('admin.courses.archive', $course) }}">@csrf @method('PATCH')
                                                <button class="w-full rounded px-2 py-1.5 text-left hover:bg-[var(--edu-surface-muted)]">{{ __('Arxivlash') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.courses.destroy', $course) }}"
                                                  onsubmit="return confirm('{{ __('Kursni o‘chirishni tasdiqlaysizmi?') }}')">@csrf @method('DELETE')
                                                <button class="w-full rounded px-2 py-1.5 text-left text-red-600 hover:bg-[rgb(239_68_68_/_0.08)]">{{ __('O‘chirish') }}</button>
                                            </form>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="edu-empty m-4">
                            <x-nav-icon name="courses" class="h-7 w-7 opacity-40" />
                            <strong>{{ __('Hali kurs yaratilmagan') }}</strong>
                            <span class="text-sm">{{ __('O‘ng tomondagi formadan birinchi kursni yarating.') }}</span>
                        </li>
                    @endforelse
                </ul>
            </div>
            <div class="mt-3">{{ $courses->links() }}</div>
        </div>

        {{-- ===== YANGI KURS ===== --}}
        <div class="edu-panel h-fit">
            <h2 class="text-base font-semibold">{{ __('Yangi kurs') }}</h2>
            <p class="mt-0.5 text-xs" style="color:var(--edu-text-muted)">{{ __('Kurs qoralama sifatida yaratiladi.') }}</p>

            <form method="POST" action="{{ route('admin.courses.store') }}" class="mt-4 space-y-2.5">
                @csrf
                <div>
                    <label for="course-title" class="mb-1 block text-xs font-medium">{{ __('Kurs nomi') }}</label>
                    <input id="course-title" name="title" placeholder="{{ __('Masalan: Mehnat huquqi asoslari') }}" required class="edu-input">
                </div>
                <div>
                    <label for="course-desc" class="mb-1 block text-xs font-medium">{{ __('Tavsif') }}</label>
                    <textarea id="course-desc" name="description" rows="2" class="edu-input"></textarea>
                </div>
                <div>
                    <label for="course-cat" class="mb-1 block text-xs font-medium">{{ __('Kategoriya') }}</label>
                    <select id="course-cat" name="category_id" required class="edu-input">
                        <option value="">{{ __('Tanlang') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="course-teacher" class="mb-1 block text-xs font-medium">{{ __('O‘qituvchi') }}</label>
                    <select id="course-teacher" name="teacher_id" required class="edu-input">
                        <option value="">{{ __('Tanlang') }}</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="course-difficulty" class="mb-1 block text-[0.68rem] font-medium">{{ __('Qiyinlik') }}</label>
                        <select id="course-difficulty" name="difficulty" class="edu-input">
                            <option value="beginner">{{ __('Boshlang‘ich') }}</option>
                            <option value="intermediate">{{ __('O‘rta') }}</option>
                            <option value="advanced">{{ __('Yuqori') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="course-language" class="mb-1 block text-[0.68rem] font-medium">{{ __('Til') }}</label>
                        <select id="course-language" name="language" class="edu-input">
                            <option value="uz-latin">{{ __('O‘zbek') }}</option>
                            <option value="uz-cyr">{{ __('Кирилл') }}</option>
                            <option value="ru">{{ __('Русский') }}</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="status" value="draft">
                <button class="edu-btn edu-btn-primary w-full">{{ __('Kurs yaratish') }}</button>
            </form>
        </div>
    </div>
@endsection
