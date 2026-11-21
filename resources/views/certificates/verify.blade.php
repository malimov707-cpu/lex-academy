@extends('layouts.app')

@section('title', __('Sertifikat tekshiruvi') . ' · ' . $certificate->verification_code)

@section('content')
@php
    $isDemo = ($certificate->student?->is_demo ?? false) || str_contains($certificate->verification_code, 'DEMO');
@endphp

<div class="mx-auto max-w-2xl px-4 py-8">
    <div class="edu-card overflow-hidden p-6 sm:p-8">
        <div class="text-center">
            @if ($certificate->revoked_at)
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </div>
                <h1 class="mt-4 text-2xl font-bold text-rose-700 dark:text-rose-400">
                    {{ __('Sertifikat bekor qilingan') }}
                </h1>
                <p class="mt-1 text-xs text-rose-600">
                    {{ __('Ushbu sertifikat ma’muriyat tomonidan bekor qilingan va yuridik kuchga ega emas.') }}
                </p>
            @else
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                </div>
                <h1 class="mt-4 text-2xl font-bold text-emerald-700 dark:text-emerald-400">
                    {{ __('Sertifikat haqiqiy va tasdiqlangan') }}
                </h1>
                <p class="mt-1 text-xs text-slate-500">
                    {{ __('HUQUQ AI Academy yagona reyestri orqali muvaffaqiyatli verifikatsiya qilindi.') }}
                </p>
            @endif

            @if ($isDemo)
                <div class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300">
                    <span>⚠️</span>
                    {{ __('DEMO / NAMUNA VERIFIKATSIYASI (Xakaton va test muhiti)') }}
                </div>
            @endif
        </div>

        <div class="mt-8 space-y-3 divide-y divide-slate-100 dark:divide-slate-800 text-sm">
            <div class="flex justify-between py-2">
                <span class="text-slate-500">{{ __('Talaba:') }}</span>
                <span class="font-bold text-[#0f1e3d] dark:text-white">{{ $certificate->student_name }}</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-slate-500">{{ __('Ta’lim kursi:') }}</span>
                <span class="font-semibold text-blue-900 dark:text-blue-300">{{ $certificate->course_title }}</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-slate-500">{{ __('Yakuniy natija:') }}</span>
                <span class="font-bold text-emerald-600">{{ $certificate->final_result }}%</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-slate-500">{{ __('Berilgan sana:') }}</span>
                <span>{{ $certificate->issued_at?->format('d.m.Y H:i') }}</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-slate-500">{{ __('Beruvchi tashkilot:') }}</span>
                <span>{{ $certificate->issuer }}</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-slate-500">{{ __('Verifikatsiya kodi:') }}</span>
                <span class="font-mono text-xs text-slate-700 dark:text-slate-300 break-all">{{ $certificate->verification_code }}</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-slate-500">{{ __('Tekshirilgan vaqt:') }}</span>
                <span class="text-xs text-slate-400">{{ now()->format('d.m.Y H:i:s') }}</span>
            </div>
        </div>

        <div class="mt-8 pt-4 text-center border-t border-slate-100 dark:border-slate-800">
            <a href="{{ route('public.home') }}" class="text-xs text-blue-700 hover:underline dark:text-blue-400">
                &larr; {{ __('HUQUQ AI Academy bosh sahifasiga qaytish') }}
            </a>
        </div>
    </div>
</div>
@endsection
