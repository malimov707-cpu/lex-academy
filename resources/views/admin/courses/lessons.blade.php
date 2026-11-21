@extends('layouts.app')

@section('title', $module->title.__(' — Darslar'))

@section('content')
<h1 class="text-2xl font-semibold">{{ $module->course->title }} → {{ $module->title }}</h1>

<div class="mt-6 grid gap-6 lg:grid-cols-3" data-sort-container>
    <div class="lg:col-span-2 space-y-3" data-resource-sortable data-reorder-url="{{ route('admin.lessons.reorder', $module) }}">
        @forelse ($lessons as $lesson)
            <div draggable="true" data-resource-id="{{ $lesson->id }}" class="flex items-center justify-between rounded-xl border border-[#0f1e3d]/10 bg-white px-5 py-4 shadow-sm">
                <div>
                    <a href="{{ route('admin.lessons.edit', $lesson) }}" class="font-medium hover:text-[#1d4ed8] dark:text-[#7aa2ff]">
                        {{ $lesson->sort_order }}. {{ $lesson->title }}
                    </a>
                    <span class="ml-2 text-xs text-[#0f1e3d]/50 dark:text-white/50">{{ $lesson->materials_count }} {{ __('material') }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <span class="edu-badge {{ $lesson->status === 'published' ? 'edu-badge-success' : 'edu-badge-neutral' }}">{{ __($lesson->status) }}</span>
                    <form method="POST" action="{{ route('admin.lessons.up', $lesson) }}">@csrf @method('PATCH')<button aria-label="{{ __('Yuqoriga') }}" class="hover:underline">↑</button></form>
                    <form method="POST" action="{{ route('admin.lessons.down', $lesson) }}">@csrf @method('PATCH')<button aria-label="{{ __('Pastga') }}" class="hover:underline">↓</button></form>
                    @if ($lesson->status !== 'published')
                        <form method="POST" action="{{ route('admin.lessons.publish', $lesson) }}">@csrf @method('PATCH')<button class="text-emerald-600 hover:underline">{{ __('Nashr') }}</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.lessons.destroy', $lesson) }}">@csrf @method('DELETE')<button class="text-red-600 hover:underline">{{ __('O\'chir') }}</button></form>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-[#0f1e3d]/20 p-8 text-center text-[#0f1e3d]/50 dark:text-white/50">{{ __('Dars yo\'q') }}</div>
        @endforelse
        <p data-sort-status role="status" class="mt-3 text-sm" data-saved="{{ __('Tartib saqlandi.') }}" data-error="{{ __('Tartib saqlanmadi. Qayta urinib ko‘ring.') }}">{{ __('Qatorlarni sudrab tartiblang yoki yuqori/past tugmalaridan foydalaning.') }}</p>
    </div>

    <div class="edu-panel">
        <h2 class="font-semibold">{{ __('Yangi dars') }}</h2>
        <form method="POST" action="{{ route('admin.modules.lessons.store', $module) }}" class="mt-4 space-y-3">
            @csrf
            <input name="title" placeholder="{{ __('Dars nomi') }}" required class="edu-input">
            <textarea name="description" placeholder="{{ __('Tavsif') }}" rows="2" class="edu-input"></textarea>
            <button class="edu-btn edu-btn-primary w-full">{{ __('Yaratish') }}</button>
        </form>
    </div>
</div>
@endsection
