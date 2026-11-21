@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')

@section('title', __('Sertifikat') . ' · ' . $certificate->student_name)

@section('content')
@php
    $isDemo = ($certificate->student?->is_demo ?? false) || str_contains($certificate->verification_code, 'DEMO');
@endphp

<div class="mx-auto max-w-4xl space-y-6">
    @if ($isDemo)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-center text-xs font-semibold text-amber-900 shadow-sm print:hidden dark:border-amber-700/50 dark:bg-amber-950/40 dark:text-amber-300">
            ⚠️ {{ __('DEMO / NAMUNA SERTIFIKATI: Ushbu sertifikat xakaton namoyishi va test tizimi doirasida yaratilgan bo‘lib, rasmiy yuridik litsenziya hisoblanmaydi.') }}
        </div>
    @endif

    <!-- Sertifikat varag'i (Chop etish uchun mos) -->
    <article class="relative overflow-hidden rounded-2xl border-8 border-double border-amber-500/60 bg-gradient-to-b from-[#fdfbf7] via-white to-[#fbf8f0] p-8 sm:p-14 text-center shadow-xl dark:bg-slate-900 dark:from-slate-900 dark:to-slate-950 dark:border-amber-600/40">
        
        @if ($isDemo)
            <!-- Diagonal suv belgisi (Watermark) -->
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center select-none overflow-hidden" aria-hidden="true">
                <span class="rotate-[-35deg] text-6xl sm:text-8xl font-black uppercase tracking-widest text-slate-400/15 dark:text-slate-500/10">
                    DEMO / NAMUNA
                </span>
            </div>
        @endif

        <!-- Sertifikat bosh qismi -->
        <div class="relative z-10 flex flex-col items-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full border-2 border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400">
                <x-nav-icon name="scale" class="h-8 w-8" />
            </div>
            <p class="mt-4 text-xs font-bold uppercase tracking-[0.25em] text-[#ad8548] dark:text-amber-400">
                {{ $certificate->issuer }}
            </p>
            <h1 class="mt-2 text-3xl sm:text-5xl font-serif font-extrabold tracking-tight text-[#0f1e3d] dark:text-white">
                {{ __('SERTIFIKAT') }}
            </h1>
            <p class="mt-1 text-xs uppercase tracking-widest text-slate-500">
                {{ __('Yuridik malaka va ta’lim dasturini muvaffaqiyatli yakunlaganligi to‘g‘risida') }}
            </p>
        </div>

        <!-- Talaba ismi va Kurs -->
        <div class="relative z-10 my-8 border-y border-amber-500/30 py-6">
            <p class="text-xs text-slate-500 uppercase tracking-wider">
                {{ __('Mazkur sertifikat tasdiqlaydiki,') }}
            </p>
            <div class="mt-2 text-2xl sm:text-4xl font-serif font-bold text-[#0f1e3d] dark:text-white">
                {{ $certificate->student_name }}
            </div>
            <p class="mt-3 text-sm text-slate-700 dark:text-slate-300">
                {{ __('HUQUQ AI Academy ta’lim tizimida') }}
            </p>
            <div class="mt-1 text-lg sm:text-xl font-semibold text-blue-900 dark:text-blue-300">
                «{{ $certificate->course_title }}»
            </div>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
                {{ __('amaliy kursi o‘quv rejasini to‘liq bajardi va yakuniy attestatsiyadan o‘tdi.') }}
            </p>
        </div>

        <!-- Ko'rsatkichlar va Sanalar -->
        <div class="relative z-10 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs text-slate-600 dark:text-slate-400">
            <div class="rounded-lg bg-white/60 dark:bg-slate-800/60 p-3 border border-slate-200/60 dark:border-slate-800">
                <span class="block text-slate-400 uppercase text-[10px]">{{ __('Yakuniy natija') }}</span>
                <span class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-400">{{ $certificate->final_result }}%</span>
                <span class="block text-[10px] text-slate-500">{{ $certificate->final_result >= 85 ? __('A’lo daraja') : __('Yaxshi daraja') }}</span>
            </div>
            <div class="rounded-lg bg-white/60 dark:bg-slate-800/60 p-3 border border-slate-200/60 dark:border-slate-800">
                <span class="block text-slate-400 uppercase text-[10px]">{{ __('Tugatilgan sana') }}</span>
                <span class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $certificate->completed_at?->format('d.m.Y') ?? '—' }}</span>
                <span class="block text-[10px] text-slate-500">{{ __('Berilgan:') }} {{ $certificate->issued_at?->format('d.m.Y') }}</span>
            </div>
            <div class="rounded-lg bg-white/60 dark:bg-slate-800/60 p-3 border border-slate-200/60 dark:border-slate-800">
                <span class="block text-slate-400 uppercase text-[10px]">{{ __('Holati') }}</span>
                @if ($certificate->revoked_at)
                    <span class="mt-1 inline-block rounded bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                        {{ __('BEKOR QILINGAN') }}
                    </span>
                @else
                    <span class="mt-1 inline-block rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        ✓ {{ __('Haqiqiy / Tasdiqlangan') }}
                    </span>
                @endif
                <span class="block text-[10px] text-slate-500 font-mono">ID: #{{ $certificate->id }}</span>
            </div>
        </div>

        <!-- Tekshirish kodi va muhr -->
        <div class="relative z-10 mt-8 pt-6 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-left">
            <div>
                <p class="text-[11px] font-mono text-slate-500">
                    {{ __('Onlayn tekshirish kodi:') }}
                </p>
                <p class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-200 break-all">
                    {{ $certificate->verification_code }}
                </p>
                <p class="mt-1 text-[10px] text-slate-400">
                    {{ __('Rasmiy verifikatsiya manzili:') }} {{ route('certificates.verify', $certificate->verification_code) }}
                </p>
            </div>
            <div class="text-center sm:text-right">
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-full border-2 border-dashed border-amber-600/50 text-amber-700 dark:text-amber-400 text-[10px] font-bold uppercase leading-tight p-2">
                    HUQUQ AI<br>SEAL
                </div>
            </div>
        </div>
    </article>

    <!-- Amallar paneli (chop etishda yashirinadi) -->
    <div class="flex flex-wrap items-center justify-center gap-4 print:hidden">
        <button onclick="window.print()" class="edu-btn edu-btn-primary">
            🖨️ {{ __('Chop etish / PDF saqlash') }}
        </button>
        <a href="{{ route('certificates.verify', $certificate->verification_code) }}" target="_blank" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
            🔍 {{ __('Haqiqiyligini tekshirish sahifasi') }}
        </a>
        @if (auth()->user()?->isAdmin() && ! $certificate->revoked_at)
            <form method="POST" action="{{ route('certificates.revoke', $certificate) }}" onsubmit="return confirm('{{ __('Haqiqatan ham ushbu sertifikatni bekor qilmoqchimisiz?') }}');">
                @csrf
                @method('PATCH')
                <button class="rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:bg-slate-800 dark:text-rose-400">
                    {{ __('Bekor qilish') }}
                </button>
            </form>
        @endif
    </div>
</div>
@endsection
