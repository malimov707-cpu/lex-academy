{{-- KPI karta — $icon, $value, $label, $hint (ixtiyoriy) --}}
<article class="edu-card edu-card-hover flex min-w-0 items-center gap-3.5 p-4">
    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[rgb(29_78_216_/_0.1)] text-[var(--edu-royal)]">
        <x-nav-icon :name="$icon" style="width:20px;height:20px" />
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-[1.55rem] font-semibold leading-none tracking-tight tabular-nums">{{ $value }}</p>
        <p class="mt-1 truncate text-xs" style="color:var(--edu-text-muted)">{{ $label }}</p>
        @isset($hint)
            <p class="mt-0.5 truncate text-[0.68rem]" style="color:var(--edu-text-muted)">{{ $hint }}</p>
        @endisset
    </div>
</article>
