@extends('layouts.student')

@section('title', $quiz->title)

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-semibold">{{ $quiz->title }}</h1>
    <p class="text-sm text-[#0f1e3d]/60 dark:text-white/60">{{ $quiz->kind === 'exam' ? __('Imtihon') : __('Test') }} · {{ $quiz->max_attempts }} {{ __('urinish · o\'tish') }} {{ $quiz->pass_score_percent }}% @if($quiz->time_limit_minutes) · {{ $quiz->time_limit_minutes }} {{ __('daqiqa') }} @endif</p>

    @if (! $activeAttempt && ! $canStart)
        <p class="mt-6 rounded-xl bg-amber-50 p-4">{{ __('Test hozir ochiq emas. Natijalaringiz quyida.') }}</p>
    @elseif ($attemptsUsed >= $quiz->max_attempts && ! $activeAttempt)
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm">{{ __('Urinishlar soni tugagan.') }}</div>
    @elseif (! $activeAttempt)
        <form method="POST" action="{{ route('student.quiz.start', [$course, $quiz]) }}" class="mt-6">
            @csrf
            <button class="edu-btn edu-btn-primary">{{ __('Boshlash') }}</button>
        </form>
    @else
        <form id="quiz-form" method="POST" action="{{ route('student.quiz.submit', [$course, $quiz]) }}" class="mt-6 space-y-4">
            @csrf
            @foreach ($questions as $q)
                <div class="edu-card p-5" data-question="{{ $q->id }}">
                    <div class="font-medium">{{ $q->text }} <span class="text-xs text-[#0f1e3d]/40 dark:text-white/40">({{ $q->points }} {{ __('ball)') }}</span></div>
                    @if (in_array($q->type, ['single', 'true_false', 'multiple']))
                        <div class="mt-2 space-y-1">
                            @foreach ($q->options as $opt)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="{{ $q->type === 'multiple' ? 'checkbox' : 'radio' }}"
                                           name="answers[{{ $q->id }}][selected_option_ids][]"
                                           value="{{ $opt->id }}" @checked(in_array($opt->id, $savedAnswers->get($q->id)?->selected_option_ids ?? []))>
                                    {{ $opt->text }}
                                </label>
                            @endforeach
                        </div>
                    @else
                        <label class="block text-xs">{{ in_array($q->type, ['matching', 'ordering']) ? 'Javobni savolda ko‘rsatilgan JSON formatida kiriting' : 'Javob' }}</label><textarea name="answers[{{ $q->id }}][text_answer]" rows="3" class="mt-2 w-full rounded-lg border border-[#0f1e3d]/15 px-3 py-2 text-sm">{{ $savedAnswers->get($q->id)?->text_answer }}</textarea>
                    @endif
                </div>
            @endforeach
            <button class="edu-btn edu-btn-primary">{{ __('Yuborish') }}</button>
        </form>

        @if ($expiresAt)<p id="quiz-clock" role="timer" class="mt-4 font-semibold text-blue-700"></p>@endif
        <script>
        @if ($expiresAt)
        const expiresAt = {{ $expiresAt }} * 1000;
        const countdown = setInterval(() => {
            const remaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            document.getElementById('quiz-clock').textContent = @json(__('Qolgan vaqt:')) + ` ${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
            if (remaining === 0) { clearInterval(countdown); document.getElementById('quiz-form').requestSubmit(); }
        }, 1000);
        @endif
        const saveStatus = document.createElement('p');
        saveStatus.setAttribute('role', 'status');
        document.getElementById('quiz-form').append(saveStatus);
        // Auto-save har 10 soniyada
        setInterval(async () => {
            const answers = [];
            document.querySelectorAll('[data-question]').forEach(div => {
                const qid = div.dataset.question;
                const opts = [...div.querySelectorAll('input:checked')].map(i => parseInt(i.value));
                const text = div.querySelector('textarea')?.value ?? null;
                answers.push({ question_id: parseInt(qid), selected_option_ids: opts.length ? opts : null, text_answer: text });
            });
            if (!answers.length) return;
            try {
            const response = await fetch('{{ route('student.quiz.autosave', [$course, $quiz]) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ answers }),
            });
            saveStatus.textContent = response.ok ? @json(__('Javoblar saqlandi.')) : @json(__('Saqlanmadi. Sahifani yangilashdan oldin qayta urinib ko‘ring.'));
            } catch { saveStatus.textContent = @json(__('Aloqa uzildi. Javoblar keyingi urinishda saqlanadi.')); }
        }, 10000);
        </script>
    @endif

    @foreach ($results as $result)
        <div class="mt-4 rounded-xl bg-white p-4 text-sm">
            {{ __('Urinish') }} {{ $result->attempt }}: <strong>{{ $result->score }}%</strong>
            @if ($result->answers->contains(fn ($answer) => $answer->points_earned === null))
                <p>{{ __('Bu dastlabki natija. O‘qituvchi bahosi kutilmoqda.') }}</p>
            @else
                <p>{{ __('Yakuniy natija') }}</p>
            @endif
            @foreach ($result->answers as $answer)
                <p class="mt-2">{{ $answer->question->text }} — {{ $answer->points_earned ?? __('Kutilmoqda') }} / {{ $answer->question->points }}</p>
            @endforeach
        </div>
    @endforeach
</div>
@endsection
