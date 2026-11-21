@extends('layouts.app')
@section('title', __('Sinov darsi natijalari'))
@section('content')
<a href="{{ route('admin.courtroom.index') }}" class="text-sm text-[var(--edu-royal)]">← {{ __('Virtual sud boshqaruvi') }}</a>
<h1 class="edu-page-title mt-3">{{ __('Sinov darsi natijalari') }}</h1>
<p class="edu-page-lead">{{ __('Talabalar qayta urinishda dalillarni qanday tahlil qilgani va o‘z fikrini qanday asoslaganini solishtiring.') }}</p>

@if($scenarios->isEmpty())
    <div class="edu-card mt-6 p-6">{{ __('Natijalarni ko‘rish uchun avval boshqaruvingizdagi ssenariyni nashr qiling.') }}</div>
@else
    <div class="edu-card mt-6 grid gap-5 p-5 md:grid-cols-3">
        <form method="GET" action="{{ route('admin.courtroom.pilot') }}">
            <label class="edu-field" for="pilot-scenario">{{ __('Ssenariy') }}</label>
            <div class="mt-1 flex gap-2">
                <select class="edu-input min-w-0 flex-1" name="scenario" id="pilot-scenario">
                    @foreach($scenarios as $scenario)
                        <option value="{{ $scenario->slug }}" @selected($selectedScenario?->id === $scenario->id)>{{ $scenario->title }}</option>
                    @endforeach
                </select>
                @if($scenarios->count() > 1)
                    <button class="edu-btn edu-btn-secondary" aria-label="{{ __('Ssenariyni tanlash') }}">{{ __('Tanlash') }}</button>
                @endif
            </div>
        </form>
        @if($selectedScenario && $versions->isNotEmpty())
            <form method="GET" action="{{ route('admin.courtroom.pilot') }}">
                <input type="hidden" name="scenario" value="{{ $selectedScenario->slug }}">
                <label class="edu-field" for="pilot-version">{{ __('Tahrir') }}</label>
                <div class="mt-1 flex gap-2">
                    <select class="edu-input min-w-0 flex-1" name="version" id="pilot-version">
                        @foreach($versions as $version)
                            <option value="{{ $version->version }}" @selected($selectedVersion?->id === $version->id)>{{ __(':number-tahrir', ['number' => $version->version]) }}</option>
                        @endforeach
                    </select>
                    @if($versions->count() > 1)
                        <button class="edu-btn edu-btn-secondary" aria-label="{{ __('Tahrirni tanlash') }}">{{ __('Tanlash') }}</button>
                    @endif
                </div>
            </form>
        @endif
        @if($selectedVersion && $roles->isNotEmpty())
            <form method="GET" action="{{ route('admin.courtroom.pilot') }}">
                <input type="hidden" name="scenario" value="{{ $selectedScenario->slug }}">
                <input type="hidden" name="version" value="{{ $selectedVersion->version }}">
                <label class="edu-field" for="pilot-role">{{ __('Rol') }}</label>
                <div class="mt-1 flex gap-2">
                    <select class="edu-input min-w-0 flex-1" name="role" id="pilot-role">
                        @foreach($roles as $role)
                            <option value="{{ $role->key }}" @selected($selectedRole?->key === $role->key)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @if($roles->count() > 1)
                        <button class="edu-btn edu-btn-secondary" aria-label="{{ __('Rolni tanlash') }}">{{ __('Tanlash') }}</button>
                    @endif
                </div>
            </form>
        @endif
    </div>

    @if(!$report)
        <p class="edu-card mt-5 p-5">{{ __('Taqqoslash uchun nashr qilingan tahrir va talaba roli kerak.') }}</p>
    @else
        <section class="mt-6">
            <h2 class="text-lg font-semibold">{{ __('Mezonlar bo‘yicha o‘zgarish') }}</h2>
            <p class="mt-1 text-sm text-[var(--edu-text-muted)]">
                {{ __('Mashqni :completed talaba yakunladi. Ulardan :repeat nafari kamida ikki marta yakunladi.', ['completed' => $report['completed_students'], 'repeat' => $report['repeat_students']]) }}
            </p>
            @if($report['criteria'])
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach($report['criteria'] as $criterion)
                        <article class="edu-card p-5">
                            <h3 class="font-semibold">{{ $criterion['name'] }}</h3>
                            @if($criterion['average_delta'] !== null)
                                <strong class="mt-2 block text-2xl tabular-nums">{{ __(':change ball', ['change' => sprintf('%+.2f', $criterion['average_delta'])]) }}</strong>
                                <p class="mt-1 text-sm text-[var(--edu-text-muted)]">{{ __(':count talaba bo‘yicha o‘rtacha farq. Mezon uchun eng yuqori ball — :max.', ['count' => $criterion['count'], 'max' => (float) $criterion['max_score']]) }}</p>
                            @else
                                <p class="mt-2 text-sm text-[var(--edu-text-muted)]">{{ __('Taqqoslash uchun ikkala urinish bo‘yicha ham o‘qituvchi bahosi kerak.') }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <p class="edu-card mt-4 p-5">{{ __('Bu tahrirda dalillar tahlili va huquqiy asoslash mezonlari belgilanmagan.') }}</p>
            @endif
        </section>

        @if($report['pairs'])
            <section class="edu-card mt-6 overflow-hidden">
                <div class="p-5">
                    <h2 class="text-lg font-semibold">{{ __('Talabalar natijalari') }}</h2>
                    <p class="mt-1 text-sm text-[var(--edu-text-muted)]">{{ __('Har bir talabaning bir xil usulda baholangan birinchi va oxirgi urinishlari ko‘rsatiladi. Farq oxirgi balldan birinchi ballni ayirish orqali hisoblanadi.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="edu-table">
                        <thead><tr><th scope="col">{{ __('Talaba') }}</th><th scope="col">{{ __('Mezon') }}</th><th scope="col">{{ __('Birinchi urinish') }}</th><th scope="col">{{ __('Oxirgi urinish') }}</th><th scope="col">{{ __('Farq') }}</th></tr></thead>
                        <tbody>
                            @foreach($report['pairs'] as $pair)
                                @foreach($pair['scores'] as $key => $score)
                                    <tr>
                                        <td>{{ $pair['student_name'] }}</td>
                                        <td>{{ $report['criteria'][$key]['name'] }}</td>
                                        <td><a class="underline" href="{{ route('admin.courtroom.reviews.show', $pair['first_evaluation_id']) }}">{{ number_format($score['first'], 2) }} <small class="block">{{ $pair['first_completed_at']->format('d.m.Y H:i') }}</small></a></td>
                                        <td><a class="underline" href="{{ route('admin.courtroom.reviews.show', $pair['last_evaluation_id']) }}">{{ number_format($score['last'], 2) }} <small class="block">{{ $pair['last_completed_at']->format('d.m.Y H:i') }}</small></a></td>
                                        <td class="font-semibold tabular-nums">{{ sprintf('%+.2f', $score['delta']) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @elseif(!$report['pending_pairs'] && $report['criteria'])
            <p class="edu-card mt-5 p-5">{{ __('Tanlangan rolda bir xil usulda baholangan ikkita urinish yakunlangach, ularning hisobotlari shu yerda ko‘rinadi.') }}</p>
        @endif

        @if($report['pending_pairs'])
            <section class="edu-card mt-6 p-5">
                <h2 class="text-lg font-semibold">{{ __('Tekshiriladigan urinishlar') }}</h2>
                <p class="mt-1 text-sm text-[var(--edu-text-muted)]">{{ __('Hisobotlarni ochib, ko‘rsatilgan mezonlarga ball qo‘ying. Ikkala baho saqlangach, farq hisoblanadi.') }}</p>
                <ul class="mt-4 grid gap-4">
                    @foreach($report['pending_pairs'] as $pair)
                        <li class="flex flex-wrap items-center justify-between gap-2 border-t border-[var(--edu-line)] pt-3">
                            <div>
                                <strong>{{ $pair['student_name'] }}</strong>
                                <p class="text-sm text-[var(--edu-text-muted)]">{{ collect($pair['missing_criteria'])->map(fn ($key) => $report['criteria'][$key]['name'])->join(' · ') }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a class="edu-btn edu-btn-secondary edu-btn-sm" href="{{ route('admin.courtroom.reviews.show', $pair['first_evaluation_id']) }}">{{ __('Birinchi urinish') }}</a>
                                <a class="edu-btn edu-btn-secondary edu-btn-sm" href="{{ route('admin.courtroom.reviews.show', $pair['last_evaluation_id']) }}">{{ __('Oxirgi urinish') }}</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
@endif
@endsection
