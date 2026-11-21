@extends('layouts.app')

@section('title', __('Darslar'))

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="edu-page-title">{{ __('Darslar') }}</h1><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('Kurs modullari, dars materiallari va mashg‘ulot jadvali.') }}</p></div>
    <a class="edu-btn edu-btn-primary" href="{{ route('schedule.index') }}">{{ __('Dars jadvalini ochish') }} →</a>
</div>
<div class="grid gap-4 xl:grid-cols-2">
    @forelse ($courses as $course)
        <section class="edu-panel">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><h2 class="text-lg font-semibold">{{ $course->title }}</h2><p class="text-sm text-slate-500">{{ __(':modules ta modul', ['modules' => $course->modules_count]) }}</p></div>
                <a class="edu-btn edu-btn-secondary edu-btn-sm" href="{{ route('admin.courses.modules.index', $course) }}">{{ __('Kursni boshqarish') }} →</a>
            </div>
            <ul class="mt-4 divide-y divide-slate-200 dark:divide-slate-700">
                @forelse ($course->modules as $module)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div><h3 class="font-medium">{{ $module->title }}</h3><p class="text-sm text-slate-500">{{ __(':lessons ta dars', ['lessons' => $module->lessons_count]) }}</p></div>
                        <a class="edu-btn edu-btn-secondary edu-btn-sm" href="{{ route('admin.modules.lessons.index', $module) }}">{{ __('Darslarni ochish') }}</a>
                    </li>
                @empty
                    <li class="py-4 text-sm text-slate-500">{{ __('Bu kursda hali modul yo‘q.') }}</li>
                @endforelse
            </ul>
        </section>
    @empty
        <section class="edu-panel text-slate-500">{{ __('Sizga biriktirilgan kurslar yo‘q.') }}</section>
    @endforelse
</div>
@endsection
