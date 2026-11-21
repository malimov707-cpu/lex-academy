@extends('layouts.app')
@section('title', __('Guruhli sud mashg‘uloti'))
@section('content')
<a class="text-sm text-[var(--edu-royal)]" href="{{ route('courtroom.groups.index') }}">← {{ __('Guruhli mashg‘ulotlar') }}</a>
<h1 class="edu-page-title mt-3">{{ __('Guruhli sud mashg‘uloti') }}</h1>
<form class="edu-card my-4 flex flex-wrap items-end gap-3 p-4" method="GET" action="{{ route('courtroom.groups.create') }}">
    <label class="flex-1">{{ __('Kurs va guruh') }}
        <select class="edu-input mt-1" name="group_id" required>
            @foreach ($groups as $option)<option value="{{ $option->id }}" @selected($option->is($group))>{{ $option->course->title }} · {{ $option->name }}</option>@endforeach
        </select>
    </label>
    <button class="edu-btn edu-btn-secondary">{{ __('Guruhni ochish') }}</button>
</form>
@if ($members->isEmpty() || $scenarios->isEmpty())
    <p class="edu-card p-4">{{ __('Mashg‘ulot yaratish uchun talabali kurs guruhi va nashr qilingan ssenariy kerak.') }}</p>
@else
<form method="POST" class="grid gap-5" action="{{ route('courtroom.groups.store') }}">
    @csrf
    <input type="hidden" name="group_id" value="{{ $group->id }}">
    <input type="hidden" name="creation_key" value="{{ old('creation_key', (string) Str::uuid()) }}">
    <section class="edu-card grid gap-4 p-5 md:grid-cols-2">
        <label>{{ __('Sud ssenariysi') }}<select class="edu-input mt-1" name="scenario_id" required>
            @foreach ($scenarios as $scenario)<option value="{{ $scenario->id }}" @selected((int) old('scenario_id') === $scenario->id)>{{ $scenario->title }}</option>@endforeach
        </select></label>
        <label>{{ __('Jadvaldagi mashg‘ulot') }}<select class="edu-input mt-1" name="class_schedule_id"><option value="">{{ __('Ixtiyoriy') }}</option>
            @foreach ($schedules as $slot)<option value="{{ $slot->id }}" @selected((int) old('class_schedule_id') === $slot->id)>{{ $slot->title }} · {{ $slot->starts_at->format('d.m.Y H:i') }}</option>@endforeach
        </select></label>
    </section>
    <section class="edu-card p-5">
        <h2 class="text-lg font-semibold">{{ __('Rollar taqsimoti') }}</h2>
        <p class="my-2 text-sm">{{ __('Faol rol berilmagan guruh a’zolari kuzatuvchi sifatida qatnashadi.') }}</p>
        <div class="grid gap-4 md:grid-cols-3">
        @foreach ($roles as $key => $title)
            <div class="rounded-xl border border-[var(--edu-line)] p-3">
                <label class="font-medium">{{ __($title) }}
                    <select class="edu-input mt-1" name="roles[{{ $key }}]"><option value="">{{ __('Tayinlanmagan') }}</option>
                        @foreach ($members as $student)<option value="{{ $student->id }}" @selected((int) old('roles.'.$key) === $student->id)>{{ $student->name }}</option>@endforeach
                    </select>
                </label>
                <details class="mt-2 text-sm"><summary class="cursor-pointer">{{ __('Rol uchun maxfiy yo‘riqnoma') }}</summary>
                    <textarea class="edu-input mt-2" name="briefs[{{ $key }}]" aria-label="{{ __($title) }} · {{ __('Yo‘riqnoma') }}" rows="2" maxlength="4000">{{ old('briefs.'.$key) }}</textarea>
                </details>
            </div>
        @endforeach
        </div>
    </section>
    <details class="edu-card p-5" open>
        <summary class="cursor-pointer text-lg font-semibold">{{ __('Tomonlarni taqqoslash mezonlari') }}</summary>
        <div class="my-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($defaults['team'] as $key => $criterion)
                <label>{{ __($criterion['label']) }} (%)
                    <input class="edu-input" name="rubric[team_weights][{{ $key }}]" type="number" min="1" max="100" required value="{{ old('rubric.team_weights.'.$key, $criterion['weight']) }}">
                </label>
            @endforeach
        </div>
        <label>{{ __('Durang chegarasi (ball)') }} <input class="edu-input max-w-28" type="number" min="0" max="100" step=".01" name="rubric[tie_threshold]" required value="{{ old('rubric.tie_threshold', $defaults['tie_threshold']) }}"></label>
    </details>
    <details class="edu-card p-5">
        <summary class="cursor-pointer text-lg font-semibold">{{ __('Faollik mezonlari va kurs jurnali') }}</summary>
        <div class="my-4 grid gap-5 lg:grid-cols-2">
            @foreach (['active' => 'Faol ishtirokchilar', 'observer' => 'Kuzatuvchilar'] as $groupKey => $title)
                <fieldset data-academic-group="{{ $groupKey }}">
                    <legend class="mb-2 font-semibold">{{ __($title) }} · 10 {{ __('ball') }}</legend>
                    <div data-criteria class="grid gap-2">
                    @foreach (old('rubric.academic.'.$groupKey, $defaults['academic'][$groupKey]) as $key => $criterion)
                        <div class="flex items-center gap-2" data-criterion>
                            <input class="edu-input min-w-0 flex-1" name="rubric[academic][{{ $groupKey }}][{{ $key }}][label]" value="{{ $criterion['label'] ?? '' }}" placeholder="{{ __('Mezon nomi') }}" aria-label="{{ __('Mezon nomi') }}" maxlength="100" required>
                            <input class="edu-input w-20" name="rubric[academic][{{ $groupKey }}][{{ $key }}][max]" type="number" min="1" max="10" value="{{ $criterion['max'] ?? 1 }}" aria-label="{{ __('Maksimal ball') }}" required>
                            <button type="button" class="edu-btn edu-btn-ghost" data-remove aria-label="{{ __('Mezonni olib tashlash') }}">×</button>
                        </div>
                    @endforeach
                    </div>
                    <button type="button" class="edu-btn edu-btn-secondary mt-2" data-add>+ {{ __('Mezon qo‘shish') }}</button>
                </fieldset>
            @endforeach
        </div>
        <fieldset><legend class="font-semibold">{{ __('Kursda hisobga olinadigan ko‘rsatkichlar') }}</legend>
        <input type="hidden" name="gradebook_configured" value="1">
        @foreach (['attendance' => 'Davomat', 'classroom_activity' => 'Dars faolligi', 'simulation_performance' => 'Simulyatsiya natijasi', 'individual_role_score' => 'Individual rol balli', 'teacher_final_grade' => 'O‘qituvchining yakuniy bahosi'] as $key => $title)
            <label class="me-3 inline-flex items-center gap-1"><input type="checkbox" name="rubric[gradebook][]" value="{{ $key }}" @checked(in_array($key, old('rubric.gradebook', $defaults['gradebook']), true))> {{ __($title) }}</label>
        @endforeach
        </fieldset>
    </details>
    <details class="edu-card p-5">
        <summary class="cursor-pointer text-lg font-semibold">{{ __('Kuzatuvchilar uchun qisqa test') }}</summary>
        <p class="my-2 text-sm">{{ __('Savol va ikkita javob variantini yozib, to‘g‘ri javobni belgilang. Bo‘sh satrlar o‘tkazib yuboriladi.') }}</p>
        @for ($i = 0; $i < 3; $i++)
            <fieldset class="mb-3 grid gap-2 rounded-xl border border-[var(--edu-line)] p-3 sm:grid-cols-2">
                <legend class="px-2">{{ __('Savol') }} {{ $i + 1 }}</legend>
                <label class="sm:col-span-2">{{ __('Savol matni') }}<input class="edu-input" name="quiz[{{ $i }}][question]" maxlength="500" value="{{ old('quiz.'.$i.'.question') }}"></label>
                @foreach (['A', 'B'] as $choice)
                    <label>{{ $choice }}<input class="edu-input" name="quiz[{{ $i }}][options][{{ $choice }}]" maxlength="250" value="{{ old('quiz.'.$i.'.options.'.$choice) }}"></label>
                @endforeach
                <label>{{ __('To‘g‘ri javob') }} <select class="edu-input" name="quiz[{{ $i }}][correct]"><option>A</option><option @selected(old('quiz.'.$i.'.correct') === 'B')>B</option></select></label>
            </fieldset>
        @endfor
    </details>
    <button class="edu-btn edu-btn-primary justify-self-start">{{ __('Mashg‘ulotni yaratish') }}</button>
</form>
@push('scripts')
<script>
for (const group of document.querySelectorAll('[data-academic-group]')) {
    const rows = group.querySelector('[data-criteria]');
    const template = rows.querySelector('[data-criterion]')?.cloneNode(true);
    group.addEventListener('click', event => {
        if (event.target.closest('[data-remove]') && rows.children.length > 1) event.target.closest('[data-criterion]').remove();
        if (!event.target.closest('[data-add]') || !template || rows.children.length >= 10) return;
        const item = template.cloneNode(true);
        const key = 'custom_' + Date.now();
        for (const input of item.querySelectorAll('input')) {
            const type = input.type === 'number' ? 'max' : 'label';
            input.name = 'rubric[academic][' + group.dataset.academicGroup + '][' + key + '][' + type + ']';
            input.value = type === 'max' ? '1' : '';
        }
        rows.append(item);
        item.querySelector('input').focus();
    });
}
</script>
@endpush
@endif
@endsection
