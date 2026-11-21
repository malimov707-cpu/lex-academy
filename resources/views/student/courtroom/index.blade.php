@extends('layouts.student')
@section('title', __('Virtual sud'))
@section('content')
<nav class="mb-5 flex gap-2" aria-label="{{ __('Virtual sud rejimlari') }}">
    <a class="edu-btn edu-btn-primary" href="{{ route('student.courtroom.index') }}">{{ __('Yakka AI sud') }}</a>
    <a class="edu-btn edu-btn-secondary" href="{{ route('courtroom.groups.index') }}">{{ __('Guruhli sud') }}</a>
</nav>
<section class="courtroom-catalog-hero">
    <div>
        <span class="courtroom-eyebrow">{{ __('HUQUQIY SIMULYATSIYA') }}</span>
        <h1>{{ __('Sud jarayonini amalda o‘rganing') }}</h1>
        <p>{{ __('Rol tanlang, guvohga savol bering va fikringizni ishdagi dalil bilan asoslang. Mashq yakunida natijangizni mezonlar bo‘yicha ko‘rasiz.') }}</p>
    </div>
    <div class="courtroom-hero-emblem" aria-hidden="true"><span>§</span><i></i><b>⚖</b></div>
</section>
<form method="GET" class="courtroom-filters" role="search">
    <label><span class="sr-only">{{ __('Ssenariy qidirish') }}</span><input name="search" value="{{ request('search') }}" placeholder="{{ __('Ssenariy qidirish…') }}"></label>
    <label><span class="sr-only">{{ __('Huquq sohasi') }}</span><select name="legal_area"><option value="">{{ __('Barcha huquq sohalari') }}</option>@foreach($legalAreas as $area)<option value="{{ $area }}" @selected(request('legal_area') === $area)>{{ __(Str::headline($area)) }}</option>@endforeach</select></label>
    <label><span class="sr-only">{{ __('Qiyinlik') }}</span><select name="difficulty"><option value="">{{ __('Barcha darajalar') }}</option>@foreach(['beginner' => 'Boshlang‘ich', 'intermediate' => 'O‘rta', 'advanced' => 'Murakkab'] as $key => $label)<option value="{{ $key }}" @selected(request('difficulty') === $key)>{{ __($label) }}</option>@endforeach</select></label>
    <button>{{ __('Qidirish') }}</button>
</form>

<section class="courtroom-catalog-section">
    <div class="student-section-title"><h2>{{ __('O‘quv ssenariylari') }}</h2><span>{{ __(':count ta ssenariy', ['count' => $scenarios->total()]) }}</span></div>
    <div class="courtroom-card-grid">
        @forelse($scenarios as $scenario)
            @php($version = $scenario->versions->first())
            <article class="courtroom-card">
                <a href="{{ route('student.courtroom.scenarios.show', $scenario) }}" class="courtroom-card-visual" aria-label="{{ $scenario->title }}">
                    <span>{{ __(Str::headline($scenario->legal_area)) }}</span>
                    <div aria-hidden="true"><i></i><b>§</b></div>
                    <small>{{ __('O‘qituvchi tasdiqlagan :version-tahrir', ['version' => $version?->version]) }}</small>
                </a>
                <div class="courtroom-card-body">
                    <div class="courtroom-card-meta"><span>{{ __(['beginner' => 'Boshlang‘ich', 'intermediate' => 'O‘rta', 'advanced' => 'Murakkab'][$scenario->difficulty] ?? $scenario->difficulty) }}</span><span>{{ $scenario->estimated_duration_minutes }} {{ __('daqiqa') }}</span></div>
                    <h3><a href="{{ route('student.courtroom.scenarios.show', $scenario) }}">{{ $scenario->title }}</a></h3>
                    <p>{{ Str::limit($scenario->description, 145) }}</p>
                    <div class="courtroom-role-stack" aria-label="{{ __('Mavjud rollar') }}">@foreach($version?->roles?->where('is_student_selectable', true)->take(3) ?? [] as $role)<span title="{{ $role->name }}">{{ mb_strtoupper(mb_substr($role->name, 0, 1)) }}</span>@endforeach<small>{{ $version?->roles?->where('is_student_selectable', true)->pluck('name')->join(' · ') }}</small></div>
                    <a href="{{ route('student.courtroom.scenarios.show', $scenario) }}" class="courtroom-card-action">{{ __('Ssenariyni ochish') }} <span aria-hidden="true">↗</span></a>
                </div>
            </article>
        @empty
            <div class="student-empty courtroom-empty"><x-nav-icon name="courtroom" /><strong>{{ __('Hozircha nashr qilingan ssenariy yo‘q') }}</strong><p>{{ __('O‘qituvchi ssenariyni tekshirib nashr qilgach, bu yerda ko‘rinadi.') }}</p></div>
        @endforelse
    </div>
    <div class="courtroom-pagination">{{ $scenarios->links() }}</div>
</section>
@endsection
