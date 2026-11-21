@extends('layouts.app')

@section('title', __('Virtual sud natijalari'))

@section('content')
<main class="mx-auto w-full max-w-7xl px-4 py-8 lg:px-8">
    @include('layouts.partials.feedback')

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Virtual sud mashg‘uloti') }}</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-950 dark:text-white">{{ $report['session']['scenario'] }}</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                {{ $report['session']['group'] }} · {{ $report['session']['course'] }}
                @if($report['session']['completed_at'])
                    · {{ \Illuminate\Support\Carbon::parse($report['session']['completed_at'])->format('d.m.Y H:i') }}
                @endif
            </p>
        </div>
        @if($report['teacher'] || $report['published'])
            <div class="flex gap-2">
                <a class="edu-btn edu-btn-secondary" href="?format=csv">{{ __('CSV yuklab olish') }}</a>
                <a class="edu-btn edu-btn-secondary" href="?format=pdf">{{ __('PDF / chop etish') }}</a>
            </div>
        @endif
    </div>

    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="{{ __('Umumiy ma’lumot') }}">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm text-slate-500">{{ __('Davomiylik') }}</p><strong class="mt-2 block text-2xl">{{ max(1, (int) ceil($report['session']['duration_seconds'] / 60)) }} {{ __('daqiqa') }}</strong>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm text-slate-500">{{ __('Faol ishtirokchilar') }}</p><strong class="mt-2 block text-2xl">{{ $report['session']['active'] }}</strong>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm text-slate-500">{{ __('Kuzatuvchilar') }}</p><strong class="mt-2 block text-2xl">{{ $report['session']['observers'] }}</strong>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm text-slate-500">{{ __('Holat') }}</p><strong class="mt-2 block text-lg">{{ \App\Services\VirtualCourtroom\GroupResultReport::statusLabel($report['status']) }}</strong>
        </article>
    </section>

    @if($report['outcome'])
        @php
            $outcome = $report['outcome'];
        @endphp
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm font-semibold text-slate-500">{{ __('O‘quv bahsi natijasi') }}</p>
            <h2 class="mt-2 text-2xl font-bold">{{ \App\Services\VirtualCourtroom\GroupResultReport::winnerLabel($outcome['winner']) }}</h2>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $outcome['reason'] }}</p>
            <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                {{ __('Bu faqat virtual sud mashg‘uloti natijasi. Real ayblov, aybsizlik yoki sud qarori emas.') }}
            </p>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @foreach(['prosecution' => __('Ayblov tomoni'), 'defense' => __('Himoya tomoni')] as $side => $label)
                    <article class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-center justify-between gap-3"><strong>{{ $label }}</strong><b>{{ $outcome['teams'][$side]['score'] === null ? __('Aniqlanmadi') : number_format($outcome['teams'][$side]['score'], 2).'/100' }}</b></div>
                        <div class="mt-3 grid gap-2">
                            @foreach($outcome['teams'][$side]['criteria'] as $key => $score)
                                <div class="flex justify-between gap-3 text-sm"><span>{{ $report['rubric']['team'][$key]['label'] ?? \App\Services\VirtualCourtroom\GroupAssessmentRubric::teamCriterionLabel($key) ?? \Illuminate\Support\Str::headline($key) }}</span><b>{{ $score === null ? '—' : number_format($score, 1).'%' }}</b></div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @if(!empty($outcome['awards']))
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-xl font-bold">{{ __('Eng yaxshi chiqishlar') }}</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach($outcome['awards'] as $award)
                        <article class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800"><strong>{{ $award['label'] }}</strong><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $award['name'] }} · {{ \App\Services\VirtualCourtroom\GroupResultReport::roleLabel($award['role_key']) }}</p></article>
                    @endforeach
                </div>
            </section>
        @endif
    @endif

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">{{ __('Talabalar natijalari') }}</caption>
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr><th class="p-4">{{ __('Talaba') }}</th><th class="p-4">{{ __('Rol') }}</th><th class="p-4">{{ __('Individual') }}</th><th class="p-4">{{ __('Faollik') }}</th><th class="p-4">{{ __('AI tavsiya') }}</th><th class="p-4">{{ __('O‘qituvchi') }}</th><th class="p-4">{{ __('Holat') }}</th></tr>
                </thead>
                <tbody>
                    @foreach($report['people'] as $person)
                        @php
                            $grade = $person['grade'] ?? null;
                            $individualScore = data_get($grade, 'individual_score');
                            $activityScore = data_get($grade, 'activity_score');
                            $aiScore = data_get($grade, 'ai_recommendation.score');
                            $teacherFinal = data_get($grade, 'teacher_final_grade');
                        @endphp
                        <tr class="border-t border-slate-200 dark:border-slate-700">
                            <th class="p-4">{{ $person['name'] }}</th>
                            <td class="p-4">{{ $person['role'] }}{{ $person['observer'] ? ' · '.__('Kuzatuvchi') : '' }}</td>
                            <td class="p-4">{{ $individualScore === null ? '—' : number_format((float) $individualScore, 1).' / 100' }}</td>
                            <td class="p-4">{{ $activityScore === null ? '—' : number_format((float) $activityScore, 1).' / 10' }}</td>
                            <td class="p-4">{{ $aiScore === null ? __('Dalil yetarli emas') : number_format((float) $aiScore, 1).' / 100' }}</td>
                            <td class="p-4">{{ $teacherFinal === null ? '—' : number_format((float) $teacherFinal, 1).' / 100' }}</td>
                            <td class="p-4">{{ \App\Services\VirtualCourtroom\GroupResultReport::statusLabel(data_get($grade, 'status', 'preliminary')) }}</td>
                        </tr>
                        @if($grade)
                            <tr class="border-t border-slate-100 dark:border-slate-800"><td colspan="7" class="p-4">
                                @if(!empty($grade['comment']))
                                    <p><strong>{{ __('O‘qituvchi izohi') }}:</strong> {{ $grade['comment'] }}</p>
                                @endif
                                @if(!$report['teacher'] && !empty($grade['reassessment']))
                                    <p class="mt-2 text-sm"><strong>{{ __('Qayta ko‘rib chiqish') }}:</strong> {{ data_get($grade, 'reassessment.status') }}</p>
                                @endif
                            </td></tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if($report['teacher'])
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="mb-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('O‘qituvchi baholash paneli') }}</p>
                <h2 class="mt-1 text-xl font-bold">{{ __('Individual va akademik faollik baholari') }}</h2>
            </div>
            <form method="POST" action="{{ route('courtroom.results.grade', $report['session']['id']) }}" class="grid gap-5">
                @csrf
                <input type="hidden" name="version" value="{{ $report['version'] }}">
                @foreach($report['people'] as $index => $person)
                    @php
                        $grade = $person['grade'] ?? [];
                        $roleKey = str_starts_with($person['role_key'], 'observer_') ? 'observer' : $person['role_key'];
                        $roleCriteria = $report['rubric']['roles'][$roleKey] ?? [];
                        $activityGroup = $person['observer'] ? 'observer' : 'active';
                        $activityCriteria = $report['rubric']['academic'][$activityGroup] ?? [];
                        $criterionScores = $grade['criteria'] ?? [];
                        $activityScores = $grade['activity'] ?? [];
                    @endphp
                    <fieldset class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <legend class="px-2 font-bold">{{ $person['name'] }} · {{ $person['role'] }}</legend>
                        <input type="hidden" name="grades[{{ $index }}][student_id]" value="{{ $person['student_id'] }}">
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div>
                                <h3 class="mb-3 text-sm font-semibold">{{ __('Individual rol mezonlari') }}</h3>
                                <div class="grid gap-3">
                                    @foreach($roleCriteria as $key => $criterion)
                                        <label class="grid gap-1 text-sm">
                                            <span>{{ $criterion['label'] }} <small class="text-slate-500">0–{{ $criterion['max'] }}</small></span>
                                            <input class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" type="number" min="0" max="{{ $criterion['max'] }}" step="0.01" name="grades[{{ $index }}][criteria][{{ $key }}]" value="{{ $criterionScores[$key] ?? '' }}">
                                            <input class="rounded-lg border border-slate-300 px-3 py-2 text-xs dark:border-slate-600 dark:bg-slate-900" type="text" name="grades[{{ $index }}][reference_text][{{ $key }}]" value="{{ implode(', ', $grade['evidence'][$key] ?? []) }}" placeholder="{{ __('Dalil ID yoki havolalari') }}">
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <h3 class="mb-3 text-sm font-semibold">{{ __('Akademik faollik — jami 10') }}</h3>
                                <div class="grid gap-3">
                                    @foreach($activityCriteria as $key => $criterion)
                                        <label class="grid gap-1 text-sm">
                                            <span>{{ $criterion['label'] }} <small class="text-slate-500">0–{{ $criterion['max'] }}</small></span>
                                            <input class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" type="number" min="0" max="{{ $criterion['max'] }}" step="0.01" name="grades[{{ $index }}][activity][{{ $key }}]" value="{{ $activityScores[$key] ?? '' }}">
                                        </label>
                                    @endforeach
                                    <label class="grid gap-1 text-sm"><span>{{ __('Davomat') }}</span>
                                        <select class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" name="grades[{{ $index }}][attendance]">
                                            @foreach(['present' => __('Qatnashdi'), 'late' => __('Keckikdi'), 'absent' => __('Yo‘q'), 'excused' => __('Sababli'), 'unmarked' => __('Belgilanmagan')] as $key => $label)
                                                <option value="{{ $key }}" @selected(($grade['attendance'] ?? 'unmarked') === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="grid gap-1 text-sm"><span>{{ __('O‘qituvchi yakuniy bahosi') }} <small class="text-slate-500">0–100</small></span>
                                        <input class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" type="number" min="0" max="100" step="0.01" name="grades[{{ $index }}][teacher_final_grade]" value="{{ $grade['teacher_final_grade'] ?? '' }}">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm"><span>{{ __('Izoh') }}</span><textarea class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" name="grades[{{ $index }}][comment]" rows="2">{{ $grade['comment'] ?? '' }}</textarea></label>
                            <label class="grid gap-1 text-sm"><span>{{ __('Kuchli tomonlar') }}</span><textarea class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" name="grades[{{ $index }}][strengths]" rows="2">{{ $grade['strengths'] ?? '' }}</textarea></label>
                            <label class="grid gap-1 text-sm"><span>{{ __('Yaxshilash yo‘nalishlari') }}</span><textarea class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" name="grades[{{ $index }}][improvements]" rows="2">{{ $grade['improvements'] ?? '' }}</textarea></label>
                            <label class="grid gap-1 text-sm"><span>{{ __('O‘zgarish sababi') }}</span><textarea class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" name="grades[{{ $index }}][reason]" rows="2">{{ $grade['reason'] ?? '' }}</textarea></label>
                        </div>
                        @if(!empty($grade['reassessment']['status']) && $grade['reassessment']['status'] === 'pending')
                            <label class="mt-3 grid gap-1 text-sm"><span>{{ __('Qayta ko‘rib chiqish javobi') }}</span><textarea class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" name="grades[{{ $index }}][reassessment_response]" rows="2"></textarea></label>
                        @endif
                    </fieldset>
                @endforeach
                <button type="submit" class="edu-btn edu-btn-primary justify-self-start">{{ __('Bulk baholarni saqlash') }}</button>
            </form>
        </section>

        @if($report['status'] === 'teacher_reviewed')
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-xl font-bold">{{ __('Yakuniy natijalarni tasdiqlash') }}</h2>
                <form method="POST" action="{{ route('courtroom.results.approve', $report['session']['id']) }}" class="mt-4 grid gap-3">
                    @csrf
                    <input type="hidden" name="version" value="{{ $report['version'] }}">
                    <label class="grid gap-1 text-sm"><span>{{ __('Umumiy natija') }}</span><textarea class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" name="conclusion[summary]" required rows="3">{{ $report['conclusion']['summary'] ?? '' }}</textarea></label>
                    @foreach(['strengths' => __('Kuchli tomonlar'), 'mistakes' => __('Takrorlangan xatolar'), 'knowledge_gaps' => __('Bilimdagi bo‘shliq'), 'next_steps' => __('Keyingi mashg‘ulot tavsiyasi')] as $key => $label)
                        <label class="grid gap-1 text-sm"><span>{{ $label }}</span><textarea class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" name="conclusion[{{ $key }}]" rows="2"></textarea></label>
                    @endforeach
                    <button type="submit" class="edu-btn edu-btn-primary justify-self-start">{{ __('Natijalarni tasdiqlash') }}</button>
                </form>
            </section>
        @endif
    @endif

    @if(!$report['teacher'] && $report['published'])
        @php
            $ownGrade = collect($report['people'])->first()['grade'] ?? null;
        @endphp
        @if($ownGrade && ($ownGrade['reassessment']['status'] ?? null) !== 'pending')
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold">{{ __('Bahoni qayta ko‘rib chiqish so‘rovi') }}</h2>
                <form method="POST" action="{{ route('courtroom.results.reassess', $report['session']['id']) }}" class="mt-3 grid gap-3">
                    @csrf
                    <label class="grid gap-1 text-sm"><span>{{ __('Izoh') }}</span><textarea class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-900" name="message" required maxlength="2000" rows="3"></textarea></label>
                    <button type="submit" class="edu-btn edu-btn-secondary justify-self-start">{{ __('So‘rov yuborish') }}</button>
                </form>
            </section>
        @endif
    @endif

    @if(!empty($report['conclusion']))
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-xl font-bold">{{ __('O‘qituvchi yakuniy xulosasi') }}</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach(['summary' => __('Umumiy natija'), 'strengths' => __('Kuchli tomonlar'), 'mistakes' => __('Takrorlangan xatolar'), 'knowledge_gaps' => __('Bilimdagi bo‘shliq'), 'next_steps' => __('Keyingi tavsiya')] as $key => $label)
                    @if(!empty($report['conclusion'][$key]))
                        <div><dt class="font-semibold">{{ $label }}</dt><dd class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $report['conclusion'][$key] }}</dd></div>
                    @endif
                @endforeach
            </dl>
        </section>
    @endif
</main>
@endsection
