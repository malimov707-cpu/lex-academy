@extends('layouts.student')

@section('title', $draft->title . ' — ' . __('Matn checklisti'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <a href="{{ route('student.document-trainer.index') }}" class="hover:underline">
                    &larr; {{ __('Barcha hujjat loyihalariga qaytish') }}
                </a>
            </div>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0f1e3d] dark:text-white">
                {{ $draft->title }}
            </h1>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span class="font-medium text-slate-700 dark:text-slate-300">{{ $typeLabel }}</span>
                <span>•</span>
                <span>{{ __('Versiya:') }} v{{ $draft->version }}</span>
                <span>•</span>
                <span>{{ __('Oxirgi tahrir:') }} {{ $draft->updated_at->format('d.m.Y H:i') }}</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="edu-badge edu-badge-info">
                {{ __($draft->status) }}
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @php
        $analysis = $draft->analysis ?? [];
        $score = $analysis['score'] ?? 0;
        $scoreBreakdown = $analysis['breakdown'] ?? [];
        $recommendations = $analysis['recommendations'] ?? [];
        $strengths = $analysis['strengths'] ?? [];
        $missingElements = $analysis['missing_elements'] ?? [];
        $detectedArticles = $analysis['detected_articles'] ?? [];

        $gradeLabel = $score >= 85 ? 'Checklist belgilari ko‘p topildi' : ($score >= 70 ? 'Checklist belgilari yetarli' : ($score >= 50 ? 'Ayrim checklist bandlari topildi' : 'Ko‘proq ma’lumot kiriting'));
        $gradeColor = $score >= 85 ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-900/50' : ($score >= 70 ? 'text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-900/50' : ($score >= 50 ? 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-900/50' : 'text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-900/50'));
    @endphp

    <!-- Matn checklisti natijasi -->
    <div class="edu-card p-6 border {{ $gradeColor }}">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-4 items-center">
            <div class="text-center md:border-r md:border-slate-200 dark:md:border-slate-800 pr-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    {{ __('Mashq ko‘rsatkichi') }}
                </p>
                <div class="mt-2 text-5xl font-extrabold tracking-tight">
                    {{ $score }}<span class="text-2xl font-normal text-slate-400">/100</span>
                </div>
                <p class="mt-2 text-xs font-bold">
                    {{ $gradeLabel }}
                </p>
            </div>

            <div class="md:col-span-3 space-y-3">
                <h3 class="text-sm font-semibold text-[#0f1e3d] dark:text-white">
                    {{ __('Matn belgilari bo‘yicha natijalar') }}
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="rounded-lg bg-white/70 dark:bg-slate-900/50 p-3 border border-slate-200/50 dark:border-slate-800">
                        <div class="flex justify-between font-medium">
                            <span>{{ __('Tuzilma va rekvizitlar:') }}</span>
                            <span class="font-bold">{{ $scoreBreakdown['structure'] ?? 0 }} / 25</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-1.5 rounded-full bg-blue-600" style="width: {{ min(100, (($scoreBreakdown['structure'] ?? 0) / 25) * 100) }}%"></div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white/70 dark:bg-slate-900/50 p-3 border border-slate-200/50 dark:border-slate-800">
                        <div class="flex justify-between font-medium">
                            <span>{{ __('Modda havolasiga o‘xshash matn:') }}</span>
                            <span class="font-bold">{{ $scoreBreakdown['legal_basis'] ?? 0 }} / 35</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-1.5 rounded-full bg-emerald-600" style="width: {{ min(100, (($scoreBreakdown['legal_basis'] ?? 0) / 35) * 100) }}%"></div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white/70 dark:bg-slate-900/50 p-3 border border-slate-200/50 dark:border-slate-800">
                        <div class="flex justify-between font-medium">
                            <span>{{ __('Protsessual uslub va til:') }}</span>
                            <span class="font-bold">{{ $scoreBreakdown['style'] ?? 0 }} / 20</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-1.5 rounded-full bg-purple-600" style="width: {{ min(100, (($scoreBreakdown['style'] ?? 0) / 20) * 100) }}%"></div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white/70 dark:bg-slate-900/50 p-3 border border-slate-200/50 dark:border-slate-800">
                        <div class="flex justify-between font-medium">
                            <span>{{ __('To‘liqlik va talablar:') }}</span>
                            <span class="font-bold">{{ $scoreBreakdown['completeness'] ?? 0 }} / 20</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-1.5 rounded-full bg-amber-600" style="width: {{ min(100, (($scoreBreakdown['completeness'] ?? 0) / 20) * 100) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <p class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs leading-relaxed text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
            {{ $analysis['disclaimer'] ?? __('Bu avtomatik matn checklisti hujjatning qonuniy kuchini, modda havolalarining to‘g‘riligini yoki amaldagi rasmiy talablarni tekshirmaydi.') }}
        </p>
    </div>

    <!-- Tahlil tafsilotlari -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- 1-ustun: Kuchli tomonlar va aniqlangan moddalar -->
        <div class="space-y-4">
            <div class="edu-card p-5">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-emerald-800 dark:text-emerald-400">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 text-xs">✓</span>
                    {{ __('Ijobiy jihatlar') }}
                </h3>
                <ul class="mt-3 space-y-2 text-xs text-slate-700 dark:text-slate-300">
                    @forelse ($strengths as $item)
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-600">•</span>
                            <span>{{ $item }}</span>
                        </li>
                    @empty
                        <li class="text-slate-400">{{ __('Yetarli ijobiy jihatlar topilmadi.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="edu-card p-5">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-[#0f1e3d] dark:text-white">
                    <x-nav-icon name="law" class="h-4 w-4 text-blue-600" />
                    {{ __('Topilgan modda havolalari (tekshirilmagan)') }}
                </h3>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @forelse ($detectedArticles as $article)
                        <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-950/40 dark:text-blue-300">
                            § {{ $article }}
                        </span>
                    @empty
                        <p class="text-xs text-slate-400">{{ __('Hujjat matnida qonun moddalariga to‘g‘ridan-to‘g‘ri havola topilmadi.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- 2-ustun: Yetishmayotgan qismlar va Tavsiyalar -->
        <div class="space-y-4 lg:col-span-2">
            <div class="edu-card p-5">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-rose-800 dark:text-rose-400">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 text-xs">!</span>
                    {{ __('Yetishmayotgan rekvizitlar va kamchiliklar') }}
                </h3>
                <ul class="mt-3 space-y-2 text-xs text-slate-700 dark:text-slate-300">
                    @forelse ($missingElements as $item)
                        <li class="flex items-start gap-2 rounded-lg bg-rose-50/50 dark:bg-rose-950/20 p-2.5 border border-rose-100 dark:border-rose-900/30">
                            <span class="text-rose-600 font-bold">✕</span>
                            <span>{{ $item }}</span>
                        </li>
                    @empty
                        <li class="text-emerald-700 text-xs font-medium">{{ __('Bu checklistda yetishmayotgan belgi topilmadi; rasmiy talablar tekshirilmagan.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="edu-card p-5">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-400">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 text-xs">💡</span>
                    {{ __('Mashq tavsiyalari') }}
                </h3>
                <ul class="mt-3 space-y-2.5 text-xs text-slate-700 dark:text-slate-300">
                    @forelse ($recommendations as $rec)
                        <li class="flex items-start gap-2 rounded-lg bg-amber-50/50 dark:bg-amber-950/20 p-2.5 border border-amber-100 dark:border-amber-900/30">
                            <span class="text-amber-600 font-bold">&rarr;</span>
                            <span>{{ $rec }}</span>
                        </li>
                    @empty
                        <li class="text-slate-400">{{ __('Tavsiyalar yo‘q.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <!-- Hujjatni qayta tahrirlash va takroriy tahlil qilish -->
    <div class="edu-card p-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h2 class="text-lg font-semibold text-[#0f1e3d] dark:text-white">
                    {{ __('Hujjatni tahrirlash va qayta tahlil qilish') }}
                </h2>
                <p class="text-xs text-[#0f1e3d]/60 dark:text-white/60">
                    {{ __('Mashq checklistidagi qaydlarni ko‘rib, loyihangizni tahrirlang va qayta tekshiring.') }}
                </p>
            </div>
            <span class="rounded bg-slate-100 px-2 py-1 text-xs font-mono text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                v{{ $draft->version }} &rarr; v{{ $draft->version + 1 }}
            </span>
        </div>

        <form method="POST" action="{{ route('student.document-trainer.analyze', $draft) }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label for="edit-title" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                    {{ __('Hujjat sarlavhasi') }}
                </label>
                <input type="text"
                       id="edit-title"
                       name="title"
                       value="{{ old('title', $draft->title) }}"
                       class="edu-input mt-1 w-full text-sm">
            </div>

            <div>
                <label for="edit-content" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                    {{ __('Hujjat matni') }}
                </label>
                <textarea id="edit-content"
                          name="content"
                          rows="16"
                          required
                          class="edu-input mt-1 font-mono text-xs sm:text-sm leading-relaxed w-full whitespace-pre-wrap">{{ old('content', $draft->content) }}</textarea>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
                <a href="{{ route('student.document-trainer.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">
                    {{ __('Trenajyorga qaytish') }}
                </a>
                <button type="submit" class="edu-btn edu-btn-primary">
                    ⚡ {{ __('Tuzatilgan hujjatni qayta tahlildan o‘tkazish') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
