@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')

@section('title', __('Huquqiy ma’lumotni tekshirish'))

@section('content')
<div class="mx-auto w-full max-w-5xl space-y-6 px-4 py-8 sm:px-6">
    <header class="space-y-2">
        <p class="text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">{{ __('Huquqiy savodxonlik') }}</p>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Shubhali huquqiy ma’lumotni tekshirish') }}</h1>
        <p class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">{{ __('Ijtimoiy tarmoq posti yoki huquqiy da’voni kiriting. Tahlil faqat mos tasdiqlangan manba parchalariga tayanadi. Manba topilmasligi da’vo yolg‘onligini anglatmaydi.') }}</p>
    </header>

    <div class="edu-card space-y-4 p-5 sm:p-7">
        <form action="{{ route('legal-information-check.store') }}" method="POST" data-legal-check-form>
            @csrf
            <label for="legal-claim-text" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Tekshiriladigan matn') }}</label>
            <textarea id="legal-claim-text" name="text" rows="7" minlength="20" maxlength="4000" required aria-describedby="legal-claim-help" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-6 text-slate-900 shadow-sm outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('text') }}</textarea>
            <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p id="legal-claim-help" class="text-xs leading-5 text-slate-500 dark:text-slate-400">{{ __('4 000 belgigacha. Shaxsiy ma’lumotlarni kiritmang. Matn saqlanmaydi; tahlil uchun sozlangan AI provayderiga yuboriladi.') }}</p>
                <button type="submit" data-legal-check-submit class="edu-btn edu-btn-primary shrink-0">{{ __('Manbalar bilan solishtirish') }}</button>
            </div>
        </form>

        <p data-legal-check-status role="status" aria-live="polite" class="text-sm text-slate-600 dark:text-slate-300"></p>
        <section data-legal-check-results hidden aria-live="polite" class="space-y-4 border-t border-slate-200 pt-5 dark:border-slate-700">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('Tahlil natijasi') }}</h2>
                <span data-legal-check-overall class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"></span>
            </div>
            <div data-legal-check-claims class="space-y-4"></div>
            <p data-legal-check-disclaimer class="rounded-lg bg-amber-50 p-3 text-xs leading-5 text-amber-900 dark:bg-amber-950/30 dark:text-amber-200"></p>
        </section>
    </div>

    <aside class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-100">
        <strong>{{ __('Eslatma:') }}</strong> {{ __('Bu ta’limiy, dastlabki tahlil. AI izohi rasmiy bayonot yoki yuridik maslahat emas. Qarama-qarshi dalil faqat ko‘rsatilgan iqtibos doirasida talqin qilinadi; muhim masalada rasmiy LexUZ matni va mutaxassisga murojaat qiling.') }}
    </aside>

    <span class="sr-only" data-legal-check-label-supported>{{ __('Tasdiqlangan manbalar bilan qo‘llab-quvvatlanadi') }}</span>
    <span class="sr-only" data-legal-check-label-partial>{{ __('Qisman qo‘llab-quvvatlanadi') }}</span>
    <span class="sr-only" data-legal-check-label-misleading>{{ __('Chalg‘ituvchi bo‘lishi mumkin') }}</span>
    <span class="sr-only" data-legal-check-label-contradicted>{{ __('Tasdiqlangan manbaga zid dalil bor') }}</span>
    <span class="sr-only" data-legal-check-label-cannot>{{ __('Tekshirib bo‘lmadi') }}</span>
    <span class="sr-only" data-legal-check-label-review>{{ __('Mutaxassis ko‘rigi kerak') }}</span>
    <span class="sr-only" data-legal-check-loading>{{ __('Mos manbalar qidirilib, da’volar tekshirilmoqda…') }}</span>
    <span class="sr-only" data-legal-check-failed>{{ __('Tahlil bajarilmadi. Xabarni tekshirib qayta urinib ko‘ring.') }}</span>
</div>
@endsection
