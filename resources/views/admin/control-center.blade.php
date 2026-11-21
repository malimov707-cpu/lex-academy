@extends('layouts.app')

@section('title', __('Super Admin — Control Center'))

@php
    $greeting = __('Assalomu alaykum');
@endphp

@section('content')
<div class="cc-shell">
    {{-- ===== SALOM + SOAT ===== --}}
    <section class="cc-greet">
        <div class="cc-greet-copy">
            <h1>{{ $greeting }}, {{ $user->name }}!</h1>
            <p>{{ __('Legal EDU platformasining boshqaruv markaziga xush kelibsiz') }}</p>
        </div>
        <div class="cc-greet-meta">
            <div>
                <span class="cc-meta-icon" aria-hidden="true"><x-nav-icon name="schedule" /></span>
                <div>
                    <strong>{{ now('Asia/Tashkent')->translatedFormat('j F, Y') }}</strong>
                    <small>{{ now('Asia/Tashkent')->translatedFormat('l') }}</small>
                </div>
            </div>
            <div>
                <span class="cc-meta-icon" aria-hidden="true"><x-nav-icon name="clock" /></span>
                <div>
                    <strong data-cc-clock>{{ now('Asia/Tashkent')->format('H:i:s') }}</strong>
                    <small>{{ __('Toshkent vaqti') }}</small>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== RASMLI BANNER ===== --}}
    <section class="cc-hero">
        <div class="cc-hero-copy">
            <span class="cc-hero-badge"><i aria-hidden="true"></i>{{ __('Platforma holati: Barqaror') }}</span>
            <h1>{{ __('Legal EDU — Huquqiy ta’limning eng zamonaviy platformasi') }}</h1>
            <p>{{ __('Bilim, amaliyot va innovatsiya bir platformada') }}</p>
            <div class="cc-hero-actions">
                <a href="{{ route('admin.reports.index') }}" class="cc-hero-btn">{{ __('Tizimni ko‘rish') }} <span aria-hidden="true">→</span></a>
                <a href="{{ route('admin.courtroom.index') }}" class="cc-hero-play"><span class="cc-hero-play-icon" aria-hidden="true">▶</span> {{ __('Virtual sudga o‘tish') }}</a>
            </div>
        </div>
    </section>

    {{-- ===== KPI KARTALAR ===== --}}
    <section class="cc-kpi-grid" aria-label="{{ __('Asosiy ko‘rsatkichlar') }}">
        @foreach ($kpis as $kpi)
            <article class="cc-kpi cc-kpi-{{ $kpi['accent'] }}">
                <span class="cc-kpi-icon" aria-hidden="true">{!! $kpi['icon'] !!}</span>
                <div class="cc-kpi-body">
                    <small>{{ $kpi['label'] }}</small>
                    <strong>{{ number_format($kpi['value'], 0, '', ' ') }}</strong>
                    <span class="cc-kpi-trend {{ $kpi['trend'] >= 0 ? 'up' : 'down' }}">
                        {{ $kpi['trend'] >= 0 ? '↑' : '↓' }} {{ abs($kpi['trend']) }}%
                    </span>
                </div>
                <svg class="cc-spark" viewBox="0 0 120 40" preserveAspectRatio="none" aria-hidden="true">
                    <path d="{{ $kpi['spark'] }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </article>
        @endforeach
    </section>

    <div class="cc-main-grid">
        <div class="cc-main-col">
            {{-- ===== PLATFORMA STATISTIKASI ===== --}}
            <section class="cc-panel" aria-label="{{ __('Platforma statistikasi') }}">
                <div class="cc-panel-head">
                    <h2><span aria-hidden="true">📊</span> {{ __('Platforma statistikasi') }}</h2>
                    <div class="cc-range" role="tablist">
                        @foreach ([7 => __('7 kun'), 30 => __('30 kun'), 90 => __('90 kun'), 365 => __('1 yil')] as $days => $label)
                            <a href="{{ route('admin.control-center', ['range' => $days]) }}"
                               class="cc-range-btn {{ (int) ($range ?? 30) === $days ? 'active' : '' }}"
                               role="tab" aria-selected="{{ (int) ($range ?? 30) === $days ? 'true' : 'false' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>

                {{-- Chiziqli grafik (SVG) --}}
                <div class="cc-chart" id="platform-statistics">
                    <svg viewBox="0 0 720 240" preserveAspectRatio="none" role="img"
                         aria-label="{{ __('Platforma statistikasi grafigi') }}">
                        @php
                            $max = max(array_merge($chart['users'], $chart['students'], $chart['sessions'])) ?: 1;
                            $stepX = 720 / (count($chart['users']) - 1 ?: 1);
                        @endphp

                        {{-- gorizontal to'rlar --}}
                        @foreach ([0.25, 0.5, 0.75, 1.0] as $f)
                            <line x1="0" y1="{{ 210 - 180 * $f }}" x2="720" y2="{{ 210 - 180 * $f }}"
                                  class="cc-grid-line"/>
                        @endforeach

                        {{-- chiziqlar --}}
                        @foreach ([
                            ['points' => $chart['users'], 'class' => 'cc-line-users'],
                            ['points' => $chart['students'], 'class' => 'cc-line-students'],
                            ['points' => $chart['sessions'], 'class' => 'cc-line-sessions'],
                        ] as $series)
                            <polyline points="{{ implode(' ', collect($series['points'])->map(fn ($v, $i) => ($i * $stepX).','.(210 - $v / $max * 180))->all()) }}"
                                      fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                      class="{{ $series['class'] }}"/>
                        @endforeach

                        {{-- oxirgi nuqtalar --}}
                        @foreach (['users' => 'cc-dot-users', 'students' => 'cc-dot-students', 'sessions' => 'cc-dot-sessions'] as $key => $class)
                            @php $last = end($chart[$key]); @endphp
                            <circle cx="{{ (count($chart[$key]) - 1) * $stepX }}" cy="{{ 210 - $last / $max * 180 }}" r="4" class="{{ $class }}"/>
                        @endforeach

                        {{-- Y o'q belgilari --}}
                        <text x="6" y="34" class="cc-axis-label">{{ number_format($max, 0, '', ' ') }}</text>
                        <text x="6" y="210" class="cc-axis-label">0</text>

                        {{-- X o'q belgilari --}}
                        @foreach ($chart['labels'] as $i => $label)
                            @if ($i % max(1, intdiv(count($chart['labels']) - 1, 4)) === 0)
                                <text x="{{ $i * $stepX }}" y="232" text-anchor="middle" class="cc-axis-label">{{ $label }}</text>
                            @endif
                        @endforeach
                    </svg>

                    <div class="cc-legend">
                        <span class="cc-legend-item cc-legend-users"><i aria-hidden="true"></i>{{ __('Foydalanuvchilar') }}</span>
                        <span class="cc-legend-item cc-legend-students"><i aria-hidden="true"></i>{{ __('Faol talabalar') }}</span>
                        <span class="cc-legend-item cc-legend-sessions"><i aria-hidden="true"></i>{{ __('Sessiyalar') }}</span>
                    </div>
                </div>
            </section>

            {{-- ===== TEZKOR BOSHQARUV ===== --}}
            <section class="cc-panel" aria-label="{{ __('Tezkor boshqaruv') }}">
                <div class="cc-panel-head">
                    <h2><span aria-hidden="true">⚡</span> {{ __('Tezkor boshqaruv') }}</h2>
                    <a href="{{ route('admin.reports.index') }}" class="cc-link">{{ __('Barchasi') }} <span aria-hidden="true">→</span></a>
                </div>
                <div class="cc-quick-grid">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['url'] }}" class="cc-quick">
                            <span class="cc-quick-icon cc-quick-{{ $action['accent'] }}" aria-hidden="true">{!! $action['icon'] !!}</span>
                            <span>{{ $action['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="cc-side-col">
            {{-- ===== IZIM FAOLIYATI ===== --}}
            <section class="cc-panel" aria-label="{{ __('So‘nggi faoliyat') }}">
                <div class="cc-panel-head">
                    <h2><span aria-hidden="true">🔔</span> {{ __('Tizim faoliyati') }}</h2>
                    <span class="cc-live-badge"><i aria-hidden="true"></i>{{ __('Real vaqt rejimida') }}</span>
                </div>
                <ul class="cc-activity">
                    @forelse ($activity as $item)
                        <li>
                            <span class="cc-activity-icon cc-quick-{{ $item['accent'] }}" aria-hidden="true">{{ $item['icon'] }}</span>
                            <div class="cc-activity-body">
                                <strong>{{ $item['title'] }}</strong>
                                <small>{{ $item['time'] }}</small>
                            </div>
                        </li>
                    @empty
                        <li class="cc-empty">{{ __('Hozircha faoliyat yo‘q.') }}</li>
                    @endforelse
                </ul>
            </section>

            {{-- ===== PLATFORMA BO'LIMLARI ===== --}}
            <section class="cc-panel" aria-label="{{ __('Platforma bo‘limlari') }}">
                <div class="cc-panel-head">
                    <h2><span aria-hidden="true">🗂</span> {{ __('Platforma bo‘limlari') }}</h2>
                    <a href="{{ route('admin.reports.index') }}" class="cc-link">{{ __('Batafsil') }} <span aria-hidden="true">→</span></a>
                </div>

                <div class="cc-donut-wrap">
                    @php $overall = $modules['overall'] ?? 0; @endphp
                    <svg viewBox="0 0 120 120" class="cc-donut" role="img" aria-label="{{ __('Umumiy faollik: :pct%', ['pct' => $overall]) }}">
                        <defs><linearGradient id="ccDonutGradient" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#00d5d0"/><stop offset="65%" stop-color="#28c0db"/><stop offset="100%" stop-color="#c4de88"/></linearGradient></defs><circle cx="60" cy="60" r="48" class="cc-donut-track"/>
                        <circle cx="60" cy="60" r="48" class="cc-donut-bar"
                                stroke-dasharray="{{ $overall * 3.016 }} 301.6"
                                transform="rotate(-90 60 60)"/>
                        <text x="60" y="54" text-anchor="middle" class="cc-donut-pct">{{ $overall }}%</text>
                        <text x="60" y="73" text-anchor="middle" class="cc-donut-sub">{{ __('Umumiy faollik') }}</text>
                    </svg>

                    <ul class="cc-module-list">
                        @foreach ($modules['sections'] as $section)
                            <li>
                                <i class="cc-dot cc-dot-{{ $section['accent'] }}" aria-hidden="true"></i>
                                <span class="cc-module-name">{{ $section['label'] }}</span>
                                <strong>{{ $section['value'] }}%</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Jonli soat (Toshkent vaqti)
    (function () {
        var el = document.querySelector('[data-cc-clock]');
        if (!el) return;
        setInterval(function () {
            el.textContent = new Date().toLocaleTimeString('uz-UZ', { hour12: false, timeZone: 'Asia/Tashkent' });
        }, 1000);
    })();
</script>
@endpush
