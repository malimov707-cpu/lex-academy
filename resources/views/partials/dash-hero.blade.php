{{--
    Umumiy hero banner — barcha rol dashboardlari uchun.
    Parametrlar: $title, $subtitle, $badge, $ctaUrl, $ctaLabel
--}}
<section class="edu-card relative overflow-hidden p-6 sm:p-8"
         style="background:linear-gradient(135deg,#0f1e3d 0%,#14315f 52%,#1d4ed8 140%);border-color:transparent">
    <div class="relative z-10 max-w-2xl">
        <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[0.7rem] font-medium uppercase tracking-[0.12em] text-white/90">
            <span class="h-1.5 w-1.5 rounded-full bg-[var(--edu-gold)]"></span>
            {{ $badge }}
        </span>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ $title }}</h1>
        <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/70">{{ $subtitle }}</p>
        @isset($ctaUrl)
            <a href="{{ $ctaUrl }}" class="edu-btn edu-btn-hero mt-5">
                {{ $ctaLabel }} <span aria-hidden="true">→</span>
            </a>
        @endisset
    </div>
    <div aria-hidden="true" class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full"
         style="background:radial-gradient(circle,rgba(198,161,91,.28),transparent 70%)"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 right-10 h-56 w-56 rounded-full"
         style="background:radial-gradient(circle,rgba(59,110,240,.35),transparent 70%)"></div>
</section>
