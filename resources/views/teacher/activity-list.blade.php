@php
    $activityIcons = ['courtroom' => 'scale', 'enrollment' => 'open-book', 'quiz' => 'assignments', 'submission' => 'lesson'];
@endphp
<ul class="teacher-activity-list">
    @forelse ($items as $event)
        <li>
            <span class="teacher-event-avatar teacher-avatar-{{ $event['type'] }}" aria-hidden="true">
                {{ \Illuminate\Support\Str::initials($event['student'], true) }}
                <span class="teacher-event-badge"><x-nav-icon :name="$activityIcons[$event['type']]" /></span>
            </span>
            <div class="teacher-event-copy">
                <strong>{{ $event['student'] }}</strong>
                <p>{{ $activityLabels[$event['type']] }} · {{ $event['title'] }}</p>
            </div>
            <span class="teacher-event-meta">
                <time datetime="{{ $event['at']->toAtomString() }}">{{ $event['at']->diffForHumans() }}</time>
                @if ($event['score'] !== null)
                    <span class="teacher-event-score">{{ $event['score'] }}%</span>
                @endif
            </span>
        </li>
    @empty
        <li class="teacher-empty"><x-nav-icon name="clock" /><strong>{{ __('Hozircha faollik yo‘q') }}</strong><p>{{ __('Talabalarning kurs, test va simulyatsiyalardagi faolligi shu yerda ko‘rinadi.') }}</p></li>
    @endforelse
</ul>
