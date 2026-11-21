@extends('layouts.student')

@section('title', __('Yangi hujjat loyihasi') . ' — ' . $typeLabel)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <a href="{{ route('student.document-trainer.index') }}" class="hover:underline">
                    &larr; {{ __('Yuridik hujjatlar trenajyoriga qaytish') }}
                </a>
            </div>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0f1e3d] dark:text-white">
                {{ $typeLabel }}: {{ __('Yangi loyiha tayyorlash') }}
            </h1>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300">
            <strong>{{ __('Xatolik yuz berdi:') }}</strong>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Tahrirlash formasi -->
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('student.document-trainer.store') }}" class="edu-card p-6 space-y-4">
                @csrf
                <input type="hidden" name="document_type" value="{{ $type }}">

                <div>
                    <label for="title" class="block text-sm font-semibold text-[#0f1e3d] dark:text-white">
                        {{ __('Hujjat sarlavhasi / Ish mavzusi') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="title"
                           name="title"
                           value="{{ old('title', $typeLabel . ' — ' . date('d.m.Y')) }}"
                           required
                           class="edu-input mt-1 w-full"
                           placeholder="{{ __('Masalan: Shartnoma bo‘yicha qarzni undirish haqida da’vo arizasi') }}">
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label for="document-content" class="block text-sm font-semibold text-[#0f1e3d] dark:text-white">
                            {{ __('Hujjat to‘liq matni') }} <span class="text-rose-500">*</span>
                        </label>
                        <span id="char-counter" class="text-xs text-slate-500">
                            0 {{ __('belgi') }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-[#0f1e3d]/60 dark:text-white/60">
                        {{ __('Shablon — o‘quv mashqi. Uni rasmiy hujjat sifatida yuborishdan oldin amaldagi qonun va ayni holatga mos talablarni rasmiy manbadan tekshiring.') }}
                    </p>
                    <textarea id="document-content"
                              name="content"
                              rows="18"
                              required
                              class="edu-input mt-2 font-mono text-xs sm:text-sm leading-relaxed w-full whitespace-pre-wrap">{{ old('content', $initialTemplate) }}</textarea>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('student.document-trainer.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ __('Bekor qilish') }}
                    </a>
                    <button type="submit" class="edu-btn edu-btn-primary">
                        <span class="mr-1.5">⚡</span> {{ __('Hujjatni saqlash va checklistni ko‘rish') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Yo'riqnoma va huquqiy talablar -->
        <div class="space-y-4">
            <div class="edu-card p-5">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-[#0f1e3d] dark:text-white">
                    <x-nav-icon name="law" class="h-4 w-4 text-amber-600" />
                    {{ __('O‘quv checklisti') }}
                </h3>
                <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                    {{ __('Quyidagilar — loyiha tuzishda o‘ylab ko‘rish uchun umumiy yo‘riqnoma. Bu bandlar barcha hujjatlar uchun majburiy degani emas: talab hujjat turi, holat va amaldagi tartibga qarab farq qiladi.') }}
                </p>
                <ul class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-400">
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">1.</span>
                        <span><strong>{{ __('Kimga va kimdan:') }}</strong> {{ __('Qabul qiluvchi va murojaatchini hujjat turiga mos aniqlang.') }}</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">2.</span>
                        <span><strong>{{ __('Holatlar:') }}</strong> {{ __('Muhim voqealarni sana va mavjud dalillar bilan bayon qiling.') }}</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">3.</span>
                        <span><strong>{{ __('Manbalar:') }}</strong> {{ __('Zarur bo‘lsa, tegishli amaldagi huquqiy manbani rasmiy bazadan mustaqil tekshiring.') }}</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">4.</span>
                        <span><strong>{{ __('Ko‘zlangan natija:') }}</strong> {{ __('Hujjat bilan nima so‘ralayotganini tushunarli bayon qiling.') }}</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">5.</span>
                        <span><strong>{{ __('Ilova va yakun:') }}</strong> {{ __('Faqat tegishli bo‘lsa, ilovalarni sanang; imzo va sanaga oid rasmiy talablarni tekshiring.') }}</span>
                    </li>
                </ul>
            </div>

            <div class="edu-card p-5 bg-amber-50/60 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/40">
                <h4 class="text-xs font-bold uppercase tracking-wider text-amber-900 dark:text-amber-400">
                    {{ __('Avtomatik checklist qanday ishlaydi?') }}
                </h4>
                <p class="mt-1 text-xs text-amber-950/80 dark:text-amber-300/80 leading-relaxed">
                    {{ __('Tizim matndagi ayrim so‘z va tuzilma belgilarini tekshiradi. U qonun moddalarining to‘g‘riligini, hujjatning qonuniy kuchini, topshirish muddatini yoki rasmiy talablarga muvofiqligini tekshirmaydi; ball faqat o‘quv mashqi ko‘rsatkichidir.') }}
                </p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const textarea = document.getElementById('document-content');
        const counter = document.getElementById('char-counter');
        if (textarea && counter) {
            const updateCount = () => {
                const len = textarea.value.length;
                counter.textContent = len + " {{ __('belgi') }}";
            };
            textarea.addEventListener('input', updateCount);
            updateCount();
        }
    });
</script>
@endpush
@endsection
