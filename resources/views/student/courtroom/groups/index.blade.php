@extends($staff ? 'layouts.app' : 'layouts.student')
@section('title', __('Guruhli Virtual Sud (3D)'))
@section('content')
<div class="space-y-6">
    <nav class="flex gap-2" aria-label="{{ __('Virtual sud rejimlari') }}">
        <a class="edu-btn edu-btn-secondary" href="{{ route('student.courtroom.index') }}">
            <x-nav-icon name="courtroom" class="h-4 w-4 mr-1.5" />
            {{ __('Yakka AI sud') }}
        </a>
        <a class="edu-btn edu-btn-primary" href="{{ route('courtroom.groups.index') }}">
            <x-nav-icon name="groups" class="h-4 w-4 mr-1.5" />
            {{ __('Guruhli 3D sud') }}
        </a>
    </nav>

    <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#0f1e3d] dark:text-white sm:text-3xl">
                {{ __('Guruhli sud mashg‘ulotlari') }}
            </h1>
            <p class="mt-1 text-sm text-[#0f1e3d]/70 dark:text-white/70">
                {{ __('9 ta real talaba (Sudya, Prokuror, Advokatlar, Guvohlar va Ayblanuvchi) hamda erkin kuzatuvchilar uchun yagona interaktiv 3D sud zali.') }}
            </p>
        </div>
        @if ($staff)
            <a class="edu-btn edu-btn-primary shrink-0" href="{{ route('courtroom.groups.create') }}">
                + {{ __('Yangi guruh mashg‘ulotini yaratish') }}
            </a>
        @endif
    </header>

    <!-- 1. Faol va Rejalashtirilgan Guruh Sessiyalari -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-[#0f1e3d] dark:text-white">
                {{ __('Mening guruhli sud mashg‘ulotlarim') }}
            </h2>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                {{ $sessions->total() }} {{ __('ta sessiya') }}
            </span>
        </div>

        <div class="grid gap-4">
            @forelse ($sessions as $session)
                @php
                    $myParticipant = $session->participants->firstWhere('user_id', auth()->id());
                    $statusLabel = [
                        'created' => 'Kutish xonasi (Lobby)',
                        'active' => '3D Sud davom etmoqda (Jonli)',
                        'paused' => 'Pauza holatida',
                        'completed' => 'Yakunlangan',
                    ][$session->status] ?? $session->status;
                    $statusColor = [
                        'created' => 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
                        'active' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 animate-pulse',
                        'paused' => 'bg-purple-50 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300',
                        'completed' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                    ][$session->status] ?? 'bg-slate-100 text-slate-700';
                @endphp
                <article class="edu-card flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center transition hover:shadow-md border border-slate-200/80 dark:border-slate-800">
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-bold text-[#0f1e3d] dark:text-white">
                                {{ $session->scenario->title }}
                            </h3>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusColor }}">
                                ● {{ __($statusLabel) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            <strong>{{ $session->educationGroup->name }}</strong> · {{ __('Boshlovchi:') }} {{ $session->facilitator->name }} · {{ $session->created_at->format('d.m.Y H:i') }}
                        </p>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                            <span>👥 {{ $session->participants_count }} {{ __('ishtirokchi (9 rol + kuzatuvchilar)') }}</span>
                            @if ($myParticipant)
                                <span>•</span>
                                <span class="font-semibold text-blue-700 dark:text-blue-400">
                                    {{ __('Sizning rolingiz:') }} {{ $myParticipant->actor_type === 'observer' ? __('Kuzatuvchi') : Str::headline($myParticipant->role_key) }}
                                </span>
                            @elseif ($staff)
                                <span>•</span>
                                <span class="font-semibold text-purple-700 dark:text-purple-400">
                                    {{ __('O‘qituvchi / Moderator') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a class="edu-btn edu-btn-primary" href="{{ route('courtroom.groups.show', $session) }}">
                            <span class="mr-1">🏛️</span>
                            {{ $session->status === 'created' ? __('Lobby / Kutish xonasiga kirish') : __('3D Sud Zaliga Kirish') }} &rarr;
                        </a>
                        @if ($session->status === 'completed')
                            <a class="edu-btn edu-btn-secondary" href="{{ route('courtroom.results.show', $session) }}">
                                📊 {{ __('Natijalar & Baholar') }}
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="edu-card p-8 text-center text-slate-500">
                    <p>{{ __('Sizga biriktirilgan faol guruhli sud mashg‘uloti hozircha yo‘q.') }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ __('O‘qituvchi guruh mashg‘ulotini tayinlagach, u ushbu ro‘yxatda paydo bo‘ladi.') }}</p>
                </div>
            @endforelse
        </div>
        {{ $sessions->links() }}
    </div>

    <!-- 2. Nashr qilingan Guruhli Ssenariylar Katalogi -->
    <section class="edu-card p-6">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Guruhli sud ssenariylari') }}</p>
                <h2 class="mt-1 text-xl font-bold text-[#0f1e3d] dark:text-white">{{ __('Barcha 9 rollik amaliy ishlar') }}</h2>
            </div>
            <p class="text-xs text-slate-500 max-w-md">
                {{ __('Har bir ssenariy 9 ta alohida rol (Sudya, Prokuror, Advokatlar, Guvohlar, Ayblanuvchi) va erkin kuzatuvchilar uchun to‘liq yozilgan faktlar va yo‘riqnomalarni o‘z ichiga oladi.') }}
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($scenarios as $scenario)
                @php
                    $version = $scenario->versions->first();
                    $activeGroupSession = $sessions->firstWhere('scenario_id', $scenario->id);
                @endphp
                <article class="flex flex-col justify-between rounded-xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900/60">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-200">
                                {{ __(Str::headline($scenario->legal_area)) }}
                            </span>
                            <span class="text-xs font-medium text-slate-500">
                                ⏱ {{ $scenario->estimated_duration_minutes ?? 25 }} {{ __('daqiqa') }}
                            </span>
                        </div>

                        <h3 class="mt-3 text-base font-bold text-[#0f1e3d] dark:text-white">
                            {{ $scenario->title }}
                        </h3>
                        <p class="mt-2 text-xs text-slate-600 dark:text-slate-400 line-clamp-3">
                            {{ $scenario->description }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2 text-[11px] text-slate-500">
                            <span class="rounded bg-slate-100 px-2 py-0.5 dark:bg-slate-800">
                                🎭 9 ta faol rol
                            </span>
                            <span class="rounded bg-slate-100 px-2 py-0.5 dark:bg-slate-800">
                                👥 Cheksiz kuzatuvchi
                            </span>
                            <span class="rounded bg-slate-100 px-2 py-0.5 dark:bg-slate-800">
                                📜 {{ $version?->stages->count() ?? 5 }} ta bosqich
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800">
                        @if ($staff)
                            <a href="{{ route('courtroom.groups.create', ['scenario_id' => $scenario->id]) }}"
                               class="edu-btn edu-btn-secondary edu-btn-sm w-full text-center justify-center">
                                + {{ __('Guruhni biriktirish & Boshlash') }}
                            </a>
                        @elseif ($activeGroupSession)
                            <a href="{{ route('courtroom.groups.show', $activeGroupSession) }}"
                               class="edu-btn edu-btn-primary edu-btn-sm w-full text-center justify-center">
                                {{ __('Mashg‘ulotga qo‘shilish (3D Zal)') }} &rarr;
                            </a>
                        @else
                            <span class="block text-center text-xs text-slate-400 py-1">
                                {{ __('O‘qituvchi tayinlashi kutilmoqda') }}
                            </span>
                        @endif
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-500">{{ __('Hozircha nashr qilingan guruhli ssenariy yo‘q.') }}</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
