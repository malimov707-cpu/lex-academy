@extends('layouts.student')

@section('title', __('Yuridik hujjatlar trenajyori'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#0f1e3d] dark:text-white sm:text-3xl">
                {{ __('Yuridik hujjatlar trenajyori') }}
            </h1>
            <p class="mt-1 text-sm text-[#0f1e3d]/70 dark:text-white/70">
                {{ __('Hujjat loyihasini tayyorlang va matndagi tuzilma, havola ko‘rinishi hamda uslub belgilarini mashq checklisti bilan ko‘rib chiqing.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-400">
                <span class="mr-1.5 h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                {{ __('Matn checklisti') }}
            </span>
        </div>
    </div>

    <!-- Hujjat turlari kartochkalari -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        @php
            $typeMeta = [
                'claim' => [
                    'title' => 'Da’vo arizasi',
                    'desc' => 'Da’vo loyihasi uchun o‘quv tuzilma va ish holatlarini bayon qilish mashqi.',
                    'badge' => 'O‘quv namunasi',
                    'icon' => 'scale',
                ],
                'application' => [
                    'title' => 'Ariza',
                    'desc' => 'Davlat organlari va tashkilotlarga yuboriladigan huquqiy talab yoki ma’lumot so‘rovi.',
                    'badge' => 'O‘quv namunasi',
                    'icon' => 'paragraph',
                ],
                'complaint' => [
                    'title' => 'Shikoyat',
                    'desc' => 'Shikoyat loyihasi uchun faktlar va so‘rovni tartiblash mashqi.',
                    'badge' => 'O‘quv namunasi',
                    'icon' => 'review',
                ],
                'contract' => [
                    'title' => 'Shartnoma',
                    'desc' => 'Fuqarolik-huquqiy shartnomalar (oldi-sotdi, xizmat ko‘rsatish, ijara) va taraflar javobgarligi.',
                    'badge' => 'O‘quv namunasi',
                    'icon' => 'law',
                ],
                'demand' => [
                    'title' => 'Talabnoma',
                    'desc' => 'Talabnoma loyihasining faktlar va so‘rov qismini mashq qiling.',
                    'badge' => 'O‘quv namunasi',
                    'icon' => 'assignments',
                ],
            ];
        @endphp

        @foreach ($types as $key => $label)
            @php
                $meta = $typeMeta[$key] ?? ['title' => $label, 'desc' => '', 'badge' => '', 'icon' => 'law'];
            @endphp
            <div class="edu-card flex flex-col justify-between p-5 transition hover:shadow-md">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-400">
                            <x-nav-icon :name="$meta['icon']" class="h-5 w-5" />
                        </span>
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            {{ $meta['badge'] }}
                        </span>
                    </div>
                    <h3 class="mt-3 text-base font-semibold text-[#0f1e3d] dark:text-white">
                        {{ $meta['title'] }}
                    </h3>
                    <p class="mt-1 text-xs text-[#0f1e3d]/70 dark:text-white/70 line-clamp-3">
                        {{ $meta['desc'] }}
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('student.document-trainer.create', ['type' => $key]) }}"
                       class="edu-btn edu-btn-primary edu-btn-sm w-full text-center justify-center">
                        {{ __('Loyiha tuzish') }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Avvalgi hujjat qoralamalari -->
    <div class="edu-card p-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h2 class="text-lg font-semibold text-[#0f1e3d] dark:text-white">
                    {{ __('Mening hujjat loyihalarim') }}
                </h2>
                <p class="text-xs text-[#0f1e3d]/60 dark:text-white/60">
                    {{ __('Sizning matn checklisti natijalari va tahrirlangan loyihalaringiz') }}
                </p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                {{ $drafts->total() }} {{ __('ta loyiha') }}
            </span>
        </div>

        @if ($drafts->isEmpty())
            <div class="py-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-600">
                    <x-nav-icon name="law" class="h-6 w-6" />
                </div>
                <h3 class="mt-3 text-sm font-semibold text-[#0f1e3d] dark:text-white">{{ __('Hali loyihalar tuzilmagan') }}</h3>
                <p class="mt-1 text-xs text-[#0f1e3d]/60 dark:text-white/60">
                    {{ __('Yuqoridagi hujjat turlaridan birini tanlab, birinchi qoralamangizni tuzishni boshlang.') }}
                </p>
                <div class="mt-4">
                    <a href="{{ route('student.document-trainer.create', ['type' => 'claim']) }}" class="edu-btn edu-btn-primary edu-btn-sm">
                        {{ __('Da’vo arizasi tuzish') }}
                    </a>
                </div>
            </div>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-medium text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <th class="py-3 px-3">{{ __('Hujjat nomi va turi') }}</th>
                            <th class="py-3 px-3">{{ __('Versiya') }}</th>
                            <th class="py-3 px-3">{{ __('Mashq ko‘rsatkichi') }}</th>
                            <th class="py-3 px-3">{{ __('Holati') }}</th>
                            <th class="py-3 px-3">{{ __('Sana') }}</th>
                            <th class="py-3 px-3 text-right">{{ __('Amallar') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($drafts as $draft)
                            @php
                                $score = $draft->analysis['score'] ?? null;
                                $scoreColor = $score >= 80 ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40' : ($score >= 60 ? 'text-amber-600 bg-amber-50 dark:bg-amber-950/40' : 'text-rose-600 bg-rose-50 dark:bg-rose-950/40');
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                                <td class="py-3 px-3">
                                    <div class="font-medium text-[#0f1e3d] dark:text-white">
                                        <a href="{{ route('student.document-trainer.show', $draft) }}" class="hover:underline">
                                            {{ $draft->title }}
                                        </a>
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ $types[$draft->document_type] ?? $draft->document_type }}
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-xs text-slate-600 dark:text-slate-400">
                                    v{{ $draft->version }}
                                </td>
                                <td class="py-3 px-3">
                                    @if ($score !== null)
                                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold {{ $scoreColor }}">
                                            {{ $score }} / 100
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">{{ __('Tahlil qilinmoqda') }}</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3">
                                    <span class="edu-badge edu-badge-info text-xs">
                                        {{ __($draft->status) }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-xs text-slate-500">
                                    {{ $draft->updated_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <a href="{{ route('student.document-trainer.show', $draft) }}" class="text-xs font-semibold text-blue-700 hover:underline dark:text-blue-400">
                                        {{ __('Ko‘rish & Tahrirlash') }} &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $drafts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
