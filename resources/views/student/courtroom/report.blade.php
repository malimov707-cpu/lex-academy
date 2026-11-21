@extends('layouts.student')
@section('title', __('Virtual sud hisoboti'))
@section('content')
@php
    $latestReview = $evaluation->reviews->sortByDesc('id')->first();
    $displayScore = $latestReview?->adjusted_score ?? $evaluation->overall_score;
@endphp
<nav class="courtroom-breadcrumb" aria-label="{{ __('Yo‘l') }}"><a href="{{ route('student.courtroom.index') }}">{{ __('Virtual sud') }}</a><span>/</span><a href="{{ route('student.courtroom.scenarios.show', $session->scenario) }}">{{ $session->scenario->title }}</a><span>/</span><span>{{ __('Hisobot') }}</span></nav>

<section class="courtroom-report-hero">
    <div class="courtroom-score-ring" style="--score:{{ min(100, (float) $displayScore) }}%"><span><strong>{{ number_format((float) $displayScore, 1) }}</strong><small>/ 100</small></span></div>
    <div><span class="courtroom-eyebrow">{{ $latestReview ? __('O‘QITUVCHI TEKSHIRGAN HISOBOT') : __('DASTLABKI TIZIM TAHLILI') }}</span><h1>{{ $session->scenario->title }}</h1><p>{{ __(':role roli · :date · :minutes daqiqa', ['role' => $session->version->roles->firstWhere('key', $session->selected_role_key)?->name, 'date' => $session->completed_at?->format('d.m.Y H:i'), 'minutes' => max(1, (int) ceil($session->elapsed_seconds / 60))]) }}</p><div class="courtroom-report-warning"><strong>{{ __('Bu yakuniy yuridik baho emas') }}</strong><span>{{ __('Avtomatik ball faqat action/event indikatorlariga asoslangan. Huquqiy aniqlik va nutq sifati o‘qituvchi tomonidan tekshiriladi.') }}</span></div></div>
    <div class="courtroom-report-actions"><a href="{{ route('student.courtroom.scenarios.show', [$session->scenario, 'role' => $session->selected_role_key]) }}#rol-tanlash">{{ __('Qayta urinish') }}</a><button type="button" onclick="window.print()">{{ __('PDF / Chop etish') }}</button></div>
</section>

<section class="courtroom-report-section">
    <div class="student-section-title"><h2>{{ __('Mezonlar bo‘yicha natija') }}</h2><span>{{ $evaluation->criterionResults->count() }} {{ __('mezon') }}</span></div>
    <div class="courtroom-criteria-grid">@foreach($evaluation->criterionResults->sortBy('criterion.sort_order') as $result)
@php($criterionScore = $latestReview?->criterion_overrides[$result->evaluation_criterion_id] ?? $result->score)
<article><div><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><strong>{{ $result->criterion->name }}</strong><b>{{ number_format((float) $criterionScore, 1) }} / {{ $result->criterion->max_score }}</b></div><div class="courtroom-criterion-track"><i style="width:{{ min(100, $result->criterion->max_score ? ((float) $criterionScore / $result->criterion->max_score * 100) : 0) }}%"></i></div><p>{{ $result->rationale }}</p><small>{{ __('Dalil: :actions ta harakat, :events ta event', ['actions' => count($result->evidence_action_ids ?? []), 'events' => count($result->evidence_event_ids ?? [])]) }}</small></article>@endforeach</div>
</section>

@if($previousAttempt)
    @php($previousScores = $previousAttempt->evaluation->criterionResults->keyBy('evaluation_criterion_id'))
    <section class="courtroom-report-section">
        <div class="student-section-title"><h2>{{ __('Oldingi urinish bilan taqqoslash') }}</h2></div>
        <p>{{ __('Shu rol va ssenariy versiyasida bir xil usulda hisoblangan avtomatik ballar taqqoslandi. O‘qituvchi kiritgan tuzatishlar alohida ko‘rsatiladi.') }}</p>
        <a class="courtroom-secondary-button" href="{{ route('student.courtroom.sessions.report', $previousAttempt) }}">{{ __('Oldingi hisobot') }} · {{ $previousAttempt->completed_at->format('d.m.Y H:i') }}</a>
        <div class="overflow-x-auto">
            <table class="mt-3 w-full text-left text-sm">
                <caption class="sr-only">{{ __('Ikki urinishdagi avtomatik ballar') }}</caption>
                <thead><tr><th scope="col">{{ __('Mezon') }}</th><th scope="col">{{ __('Oldingi') }}</th><th scope="col">{{ __('Hozirgi') }}</th><th scope="col">{{ __('Farq') }}</th></tr></thead>
                <tbody>
                    @foreach($evaluation->criterionResults->sortBy('criterion.sort_order') as $result)
                        @php($previousResult = $previousScores->get($result->evaluation_criterion_id))
                        @if($previousResult)
                            @php($difference = round((float) $result->score - (float) $previousResult->score, 2))
                            <tr><th scope="row" class="py-2 pr-2">{{ $result->criterion->name }}</th><td>{{ number_format((float) $previousResult->score, 2) }}</td><td>{{ number_format((float) $result->score, 2) }}</td><td>{{ ($difference > 0 ? '+' : '').number_format($difference, 2) }}</td></tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

<div class="courtroom-report-columns">
    <section class="courtroom-info-panel"><h2>{{ __('Kuchli tomonlar') }}</h2><ul class="courtroom-objectives">@forelse($evaluation->strengths ?? [] as $strength)<li><span>✓</span>{{ $strength }}</li>@empty<li>{{ __('Hali yuqori ishonchli avtomatik indikator yo‘q; o‘qituvchi tekshiruvi kutilmoqda.') }}</li>@endforelse</ul></section>
    <section class="courtroom-info-panel"><h2>{{ __('Yaxshilash yo‘nalishlari') }}</h2><ul class="courtroom-improvement-list">@foreach($evaluation->improvements ?? [] as $improvement)<li>{{ $improvement }}</li>@endforeach</ul></section>
</div>

@if($evaluation->ai_feedback)
    @include('student.courtroom.ai-feedback', ['evaluation' => $evaluation, 'actions' => $session->actions])
@elseif($canRequestFeedback)
    <section class="courtroom-info-panel">
        <h2>{{ __('Keyingi mashq uchun AI tavsiyasi') }}</h2>
        <p>{{ __('Tavsiyaga asos bo‘lishi uchun so‘nggi harakatlaringiz matni AI xizmatiga yuboriladi.') }}</p>
        <form method="POST" action="{{ route('student.courtroom.sessions.report.feedback', $session) }}">
            @csrf
            <button type="submit" class="courtroom-secondary-button">{{ __('AI tavsiyasini olish') }}</button>
        </form>
    </section>
@endif

<section class="courtroom-info-panel courtroom-report-timeline">
    <div class="student-section-title"><h2>{{ __('Jarayon xronologiyasi') }}</h2><span>{{ $session->events->count() }} {{ __('event') }}</span></div>
    <ol>
        @foreach($session->events as $event)
            @php($recipient = $session->version->roles->firstWhere('key', $event->payload['target_role_key'] ?? null))
            <li>
                <time>{{ $event->occurred_at->format('H:i:s') }}</time>
                <span>{{ __(Str::headline($event->type)) }}</span>
                <small>{{ $event->stage_key }}@if($recipient) · {{ __('Kimga') }} {{ $recipient->name }}@endif</small>
            </li>
        @endforeach
    </ol>
</section>

<section class="courtroom-info-panel courtroom-teacher-feedback"><h2>{{ __('O‘qituvchi tekshiruvi') }}</h2>@forelse($evaluation->reviews as $review)<article><div><strong>{{ $review->reviewer->name }}</strong><span>{{ __(Str::headline($review->decision)) }} · {{ $review->created_at->format('d.m.Y H:i') }}</span></div><p>{{ $review->comment }}</p>@if($review->adjusted_score !== null)<b>{{ __('O‘qituvchi bahosi: :score / 100', ['score' => $review->adjusted_score]) }}</b>@endif</article>@empty<p>{{ __('Hisobot hali o‘qituvchi tomonidan ko‘rib chiqilmagan.') }}</p>@endforelse</section>
@if($personalNotes?->isNotEmpty() || $personalNotes?->previousCursor())
    <section class="courtroom-info-panel courtroom-learning-feedback" id="shaxsiy-qaydlar">
        <h2>{{ __('Dalillardagi shaxsiy qaydlar') }}</h2>
        @foreach($personalNotes as $annotation)
            @continue(!$annotation->evidence->isVisibleTo($session->selected_role_key))
            <article>
                <h3>{{ $annotation->evidence->title }}</h3>
                @if($annotation->start_offset !== null)
                    <p><q>{{ $annotation->excerptFrom($annotation->evidence) }}</q></p>
                @endif
                @if($annotation->note)
                    <p>{{ $annotation->note }}</p>
                @endif
                <form method="POST" action="{{ route('student.courtroom.evidence.annotations.destroy', [$session, $annotation->evidence, $annotation]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="courtroom-secondary-button" type="submit">{{ __('Qaydni o‘chirish') }}</button>
                </form>
            </article>
        @endforeach
        {{ $personalNotes->fragment('shaxsiy-qaydlar')->links() }}
    </section>
@endif
@endsection
