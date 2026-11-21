@extends('layouts.app')

@section('title', $course->title.__(' — Tuzilma'))

@section('content')
<h1 class="text-2xl font-semibold">{{ $course->title }} {{ __('— modullar') }}</h1>

<div class="mt-6 grid gap-6 lg:grid-cols-3" data-sort-container>
    <div class="lg:col-span-2 space-y-4" data-resource-sortable data-reorder-url="{{ route('admin.modules.reorder', $course) }}">
        @forelse ($course->modules as $module)
            <div draggable="true" data-resource-id="{{ $module->id }}" class="edu-card p-5">
                <div class="flex items-center justify-between">
                    <div class="font-semibold">{{ $module->sort_order }}. {{ $module->title }}</div>
                    <div class="space-x-1 text-sm">
                        <a href="{{ route('admin.modules.lessons.index', $module) }}" class="text-[#1d4ed8] dark:text-[#7aa2ff] hover:underline">{{ __('Darslar') }}</a>
                        <form class="inline" method="POST" action="{{ route('admin.modules.up', $module) }}">@csrf @method('PATCH')<button aria-label="{{ __('Yuqoriga') }}" class="hover:underline">↑</button></form>
                        <form class="inline" method="POST" action="{{ route('admin.modules.down', $module) }}">@csrf @method('PATCH')<button aria-label="{{ __('Pastga') }}" class="hover:underline">↓</button></form>
                        <form class="inline" method="POST" action="{{ route('admin.modules.destroy', $module) }}">@csrf @method('DELETE')<button class="text-red-600 hover:underline">{{ __('O\'chir') }}</button></form>
                    </div>
                </div>
                <div class="mt-2 text-sm text-[#0f1e3d]/60 dark:text-white/60">{{ $module->lessons->count() }} {{ __('dars') }}</div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-[#0f1e3d]/20 p-8 text-center text-[#0f1e3d]/50 dark:text-white/50">{{ __('Modul yo\'q') }}</div>
        @endforelse
        <p data-sort-status role="status" class="mt-3 text-sm" data-saved="{{ __('Tartib saqlandi.') }}" data-error="{{ __('Tartib saqlanmadi. Qayta urinib ko‘ring.') }}">{{ __('Qatorlarni sudrab tartiblang yoki yuqori/past tugmalaridan foydalaning.') }}</p>
    </div>

    <div class="edu-panel">
        <h2 class="font-semibold">{{ __('Yangi modul') }}</h2>
        <form method="POST" action="{{ route('admin.courses.modules.store', $course) }}" class="mt-4 space-y-3">
            @csrf
            <input name="title" placeholder="{{ __('Modul nomi') }}" required class="edu-input">
            <textarea name="description" placeholder="{{ __('Tavsif') }}" rows="2" class="edu-input"></textarea>
            <button class="edu-btn edu-btn-primary w-full">{{ __('Qo\'shish') }}</button>
        </form>
    </div>
</div>
@endsection
