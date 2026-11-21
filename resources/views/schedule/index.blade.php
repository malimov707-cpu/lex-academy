@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')
@section('title', __('O‘quv jadvali'))
@section('content')
<div class="staff-shell">
    <section class="staff-hero">
        <div class="staff-hero-copy">
            <span class="staff-hero-badge"><i aria-hidden="true"></i>{{ __('Jadval') }}</span>
            <h1>{{ __('O‘quv jadvali') }}</h1>
            <p>{{ __('Darslar, mentor uchrashuvlari va davomat — bitta kalendar jadvalda.') }}</p>
        </div>
        <span class="staff-hero-mark" aria-hidden="true">⌛</span>
    </section>

    {{-- ===== FILTR ===== --}}
    <form method="GET" class="staff-panel mt-5 !py-5 flex flex-wrap items-end gap-4">
        <label class="text-xs font-semibold" style="color:#3c4c68">{{ __('Oy') }}
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="edu-input mt-1.5 block">
        </label>
        <label class="text-xs font-semibold" style="color:#3c4c68">{{ __('Boshlanish') }}
            <input type="date" name="from" value="{{ request('from') }}" class="edu-input mt-1.5 block">
        </label>
        <label class="text-xs font-semibold" style="color:#3c4c68">{{ __('Tugash') }}
            <input type="date" name="until" value="{{ request('until') }}" class="edu-input mt-1.5 block">
        </label>
        <button class="edu-btn edu-btn-primary">{{ __('Filtrlash') }}</button>
    </form>
    @if ($errors->any())<p role="alert" class="mt-3 text-red-600">{{ $errors->first() }}</p>@endif

    {{-- ===== OYLIK KALENDAR ===== --}}
    <section class="staff-panel mt-5 overflow-x-auto" aria-label="{{ __('Oylik kalendar') }}">
        <div class="staff-panel-head"><h2>{{ $month->format('Y-m') }} {{ __('— kalendar') }}</h2></div>
        <div class="grid min-w-[640px] grid-cols-7 gap-2 text-sm">
            @foreach (['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sha', 'Ya'] as $day)<div class="p-2 text-[0.7rem] font-semibold uppercase tracking-wider" style="color:var(--edu-text-muted)">{{ __($day) }}</div>@endforeach
            @for ($blank = 1; $blank < $month->isoWeekday(); $blank++)<div></div>@endfor
            @for ($day = 1; $day <= $month->daysInMonth; $day++)
                @php $date = $month->copy()->day($day)->format('Y-m-d'); @endphp
                <div class="staff-row !block min-h-24 !p-2"><span class="font-semibold">{{ __($day) }}</span>
                    @foreach ($calendar->get($date, collect()) as $entry)
                        <p class="mt-2 rounded-md px-1.5 py-1 text-[0.68rem] font-medium" style="background:#eaf2ff;color:#1765e9">{{ $entry->starts_at->format('H:i') }} {{ $entry->title }}</p>
                    @endforeach
                </div>
            @endfor
        </div>
    </section>

    <div class="staff-grid mt-5">
        <div class="staff-stack">
            @forelse ($schedules as $schedule)
                <article class="staff-panel">
                    <p class="text-xs font-semibold uppercase tracking-wide" style="color:#1765e9">{{ $schedule->starts_at->format('d.m.Y H:i') }} — {{ $schedule->ends_at->format('H:i') }}</p>
                    <h2 class="mt-2 text-base font-semibold">{{ $schedule->title }}</h2>
                    <p class="mt-1 text-sm" style="color:var(--edu-text-muted)">{{ $schedule->course->title }} · {{ $schedule->group?->name ?? 'Barcha talabalar' }} · {{ $schedule->teacher->name }} · {{ $schedule->location }}</p>
                    @if (! $canManage && $schedule->attendance->isNotEmpty())
                        <p class="mt-3 text-sm">{{ __('Davomatingiz:') }} {{ __($schedule->attendance->first()->status) }} · {{ $schedule->attendance->first()->reason }}</p>
                    @endif
                    @if ($canManage)
                        <a href="{{ route('schedule.attendance', $schedule) }}" class="mt-3 inline-block text-sm font-semibold" style="color:#1765e9">{{ __('Davomatni belgilash / hisobot') }} →</a>
                        <details class="mt-3"><summary class="cursor-pointer text-sm font-medium" style="color:#3c4c68">{{ __('Jadvalni o‘zgartirish') }}</summary>
                            <form method="POST" action="{{ route('schedule.update', $schedule) }}" class="staff-form-grid mt-3">@csrf @method('PUT')
                                <label>{{ __('Nomi') }}<input name="title" value="{{ $schedule->title }}" required class="edu-input"></label>
                                <label>{{ __('Boshlanish') }}<input name="starts_at" type="datetime-local" value="{{ $schedule->starts_at->format('Y-m-d\TH:i') }}" required class="edu-input"></label>
                                <label>{{ __('Tugash') }}<input name="ends_at" type="datetime-local" value="{{ $schedule->ends_at->format('Y-m-d\TH:i') }}" required class="edu-input"></label>
                                <label>{{ __('Manzil') }}<input name="location" value="{{ $schedule->location }}" class="edu-input"></label>
                                <button class="edu-btn edu-btn-primary self-start">{{ __('Saqlash') }}</button>
                            </form>
                        </details>
                    @endif
                </article>
            @empty
                <div class="staff-empty">{{ __('Dars rejalashtirilmagan.') }}</div>
            @endforelse
            {{ $schedules->links() }}
        </div>

        @if ($canManage)
            <form method="POST" action="{{ route('schedule.store') }}" class="staff-panel staff-form-grid">@csrf
                <div class="staff-panel-head !mb-0"><h2>{{ __('Yangi uchrashuv') }}</h2></div>
                <label>{{ __('Nomi') }}<input name="title" required class="edu-input"></label>
                <label>{{ __('Kurs') }}<select name="course_id" required class="edu-input">@foreach ($courses as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select></label>
                <label>{{ __('Guruh') }}<select name="group_id" class="edu-input"><option value="">{{ __('Barcha talabalar') }}</option>@foreach ($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select></label>
                <label>{{ __('Tur') }}<select name="kind" class="edu-input"><option value="lesson">{{ __('Dars') }}</option><option value="mentor_meeting">{{ __('Mentor uchrashuvi') }}</option></select></label>
                <label>{{ __('Olib boruvchi') }}<select name="host_id" class="edu-input"><option value="">{{ __('Kurs o‘qituvchisi') }}</option>@foreach ($hosts as $host)<option value="{{ $host->id }}">{{ $host->name }} · {{ $host->role->value }}</option>@endforeach</select></label>
                <label>{{ __('Boshlanish') }}<input name="starts_at" type="datetime-local" required class="edu-input"></label>
                <label>{{ __('Tugash') }}<input name="ends_at" type="datetime-local" required class="edu-input"></label>
                <label>{{ __('Manzil / izoh') }}<input name="location" class="edu-input"></label>
                <button class="edu-btn edu-btn-primary">{{ __('Qo‘shish') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
