@extends('layouts.student')
@section('title', __('O‘quvchi paneli'))
@php
    $user = auth()->user();
    $percent = $totalLessons > 0 ? (int) round($completedCount / $totalLessons * 100) : 0;
    $continue = $enrollments->first(fn ($enrollment) => $courseProgress[$enrollment->course_id]['next_lesson'] !== null) ?? $enrollments->first();
    $nextLesson = $continue ? $courseProgress[$continue->course_id]['next_lesson'] : null;
    $continueUrl = $continue ? ($nextLesson ? route('student.lesson', [$continue->course, $nextLesson]) : route('student.course', $continue->course)) : route('ai.index');
    $activityMax = max(1, $weeklyActivity->max('count'));
@endphp
@section('content')
<section class="student-hero">
    <div class="student-hero-copy"><span>{{ __('HUQUQIY TA’LIM') }}</span><h1>{{ __('Bilimingizni yangi bosqichga olib chiqing') }}</h1><p>{{ __('Har bir dars — kelajagingiz sari yana bir qadam.') }}</p><a href="{{ $continueUrl }}">{{ $continue ? __('O‘qishni davom ettirish') : __('AI Ustoz bilan tanishish') }}<span aria-hidden="true">↗</span></a></div>
    <div class="student-hero-art" aria-hidden="true"><div class="student-hero-orb"></div><div class="student-hero-orbit"></div><div class="student-hero-symbol">§</div><span class="student-hero-star">✦</span></div>
</section>
<div class="student-summary-grid">
    @foreach ([['courses', $enrollments->count(), 'Kurslarim'], ['lesson', $completedCount.' / '.$totalLessons, 'Tugallangan darslar'], ['analytics', $percent.'%', 'O‘zlashtirish'], ['assignments', $assignments->count(), 'Yaqin deadline']] as [$icon, $value, $label])<div class="student-summary"><span><x-nav-icon :name="$icon" /></span><div><small>{{ __($label) }}</small><strong>{{ $value }}</strong></div></div>@endforeach
</div>
<a href="{{ route('library.index') }}" class="student-library-banner"><span><x-nav-icon name="sources" /></span><div><strong>{{ __('Huquqiy kutubxona') }}</strong><small>{{ __('Kodekslar, qonunlar va darsliklar — o‘qish va izlanish uchun.') }}</small></div><span aria-hidden="true">↗</span></a>
<a href="{{ route('student.courtroom.index') }}" class="student-library-banner student-courtroom-banner"><span><x-nav-icon name="courtroom" /></span><div><strong>{{ __('Virtual sud') }}</strong><small>{{ __('Rol tanlang, dalillarni tahlil qiling va sud jarayonida huquqiy pozitsiyangizni himoya qiling.') }}</small></div><span aria-hidden="true">↗</span></a>
<section id="my-courses" class="student-course-section">
    <div class="student-section-title"><h2>{{ __('O‘qishni davom ettirish') }}</h2><span>{{ __(':n ta kurs', ['n' => $enrollments->count()]) }}</span></div>
    <div class="student-course-grid">
        @forelse ($enrollments as $enrollment)
            @php $progress = $courseProgress[$enrollment->course_id]; $course = $enrollment->course; @endphp
            <article class="student-course-card" data-student-course data-completed="{{ $progress['percent'] >= 100 ? 'true' : 'false' }}">
                <a class="student-course-cover student-course-cover-{{ $loop->index % 3 }}" href="{{ route('student.course', $course) }}" aria-label="{{ $course->title }}">
                    @if ($course->cover_image)<img src="{{ route('courses.cover', $course) }}" alt="" loading="lazy">@else<span class="student-cover-category">{{ $course->category->name }}</span><span class="student-cover-symbol" aria-hidden="true">{{ $loop->index % 2 ? '§' : '⚖' }}</span><span class="student-cover-line" aria-hidden="true"></span>@endif
                    <span class="student-cover-status">{{ $progress['percent'] }}%</span>
                </a>
                <div class="student-course-card-body"><span class="student-category-tag">{{ $course->category->name }}</span><h3><a href="{{ route('student.course', $course) }}">{{ $course->title }}</a></h3><p>{{ Str::limit($course->description ?: __('Kurs tavsifi kiritilmagan.'), 90) }}</p>
                    <div class="student-progress-track" role="progressbar" aria-label="{{ $course->title }}" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $progress['percent'] }}%"></span></div>
                    <div class="student-course-meta"><span>{{ $progress['completed'] }} / {{ $progress['total'] }} {{ __('dars') }}</span><a href="{{ route('student.analytics', $course) }}" aria-label="{{ __('O‘quv natijalari') }}">{{ __('Natijalar') }} ↗</a></div>
                    <div class="student-course-footer"><span class="student-person-avatar">{{ mb_strtoupper(mb_substr($course->teacher->name, 0, 1)) }}</span><span>{{ $course->teacher->name }}</span><a class="student-course-play" href="{{ $progress['next_lesson'] ? route('student.lesson', [$course, $progress['next_lesson']]) : route('student.course', $course) }}" aria-label="{{ __('Davom ettirish') }}: {{ $course->title }}"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m9 6 9 6-9 6z"/></svg></a></div>
                </div>
            </article>
        @empty
            <div class="student-empty"><x-nav-icon name="courses" /><strong>{{ __('Hali kurs biriktirilmagan') }}</strong><p>{{ __('Administrator kursga biriktirgach, bu yerda ko‘rinadi.') }}</p><a href="{{ route('ai.index') }}">{{ __('AI Ustoz') }} →</a></div>
        @endforelse
    </div>
    <p class="student-filter-empty" data-student-filter-empty role="status" hidden>{{ __('Tugallanmagan kurslar yo‘q.') }}</p>
</section>
<section class="student-mentor-section">
    <div class="student-section-title"><h2>{{ __('Mentorlarim') }}</h2><a href="{{ route('conversations.index') }}">{{ __('Barchasini ko‘rish') }} ↗</a></div>
    <div class="student-table-wrap"><table class="student-mentor-table"><thead><tr><th>{{ __('Mentor') }}</th><th>{{ __('Kurs') }}</th><th>{{ __('Biriktirilgan sana') }}</th><th><span class="sr-only">{{ __('Muloqot') }}</span></th></tr></thead><tbody>
    @forelse ($mentorAssignments->take(5) as $assignment)<tr><td><a href="{{ route('conversations.show', $assignment) }}" class="student-person"><span class="student-person-avatar">{{ mb_strtoupper(mb_substr($assignment->mentor->name, 0, 1)) }}</span><span><strong>{{ $assignment->mentor->name }}</strong><small>{{ __('Mentor') }}</small></span></a></td><td>{{ $assignment->course->title }}</td><td>{{ $assignment->created_at?->format('d.m.Y') }}</td><td><a class="student-table-action" href="{{ route('conversations.show', $assignment) }}" aria-label="{{ __('Xabar yozish') }}: {{ $assignment->mentor->name }}"><x-nav-icon name="chat" /></a></td></tr>@empty<tr><td colspan="4" class="student-table-empty">{{ __('Mentor hali biriktirilmagan.') }}</td></tr>@endforelse
    </tbody></table></div>
</section>
<div class="student-lower-grid">
    <section class="student-small-panel"><div class="student-section-title"><h2>{{ __('Bugungi darslar') }}</h2><a href="{{ route('schedule.index') }}">{{ __('Kalendar') }} ↗</a></div><ul>
    @forelse ($todaySessions as $session)<li><span class="student-session-time">{{ $session->starts_at->format('H:i') }}</span><span>{{ $session->title }}</span></li>@empty<li class="student-muted-note">{{ __('Bugun dars belgilanmagan.') }}</li>@endforelse
    </ul></section>
    <section class="student-small-panel"><div class="student-section-title"><h2>{{ __('Yaqin imtihonlar') }}</h2><x-nav-icon name="quiz" /></div><ul>
    @forelse ($upcomingExams as $exam)<li><a href="{{ route('student.quiz.show', [$exam->course_id, $exam]) }}"><strong>{{ $exam->title }}</strong><small>{{ $exam->available_from?->format('d.m.Y H:i') ?? __('Ochiq') }}</small></a></li>@empty<li class="student-muted-note">{{ __('Imtihon belgilanmagan.') }}</li>@endforelse
    </ul></section>
</div>
@if ($recentResults->isNotEmpty())<section class="student-results"><div class="student-section-title"><h2>{{ __('So‘nggi test natijalari') }}</h2></div>@foreach ($recentResults as $attempt)<a href="{{ route('student.quiz.show', [$attempt->quiz->course_id, $attempt->quiz]) }}" class="student-result-row"><span class="student-result-score">{{ (int) $attempt->score }}%</span><span><strong>{{ $attempt->quiz->title }}</strong><small>{{ $attempt->pending_answers_count ? __('Yakuniy baho kutilmoqda') : __('Yakuniy natija') }}</small></span><span aria-hidden="true">↗</span></a>@endforeach</section>@endif
@endsection
@section('profile')
<div class="student-profile-heading"><h2>{{ __('Mening profilim') }}</h2><span>{{ __('Talaba') }}</span></div>
<div class="student-profile-intro"><div class="student-profile-ring" style="--student-progress:{{ $percent }}%"><span>{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span></div><h2>{{ __('Salom,') }} {{ $user->name }}</h2><p>{{ __('Maqsadingiz sari o‘rganishda davom eting.') }}</p><div class="student-profile-actions"><a href="{{ route('notifications.index') }}" aria-label="{{ __('Bildirishnomalar') }}"><x-nav-icon name="notification" />@if($unreadMessages)<small>{{ min(99, $unreadMessages) }}</small>@endif</a><a href="{{ route('conversations.index') }}" aria-label="{{ __('Xabarlar') }}"><x-nav-icon name="chat" /></a><a href="{{ route('ai.index') }}" aria-label="{{ __('AI Ustoz') }}"><x-nav-icon name="tutor" /></a></div></div>
<section class="student-activity"><div class="student-section-title"><h2>{{ __('Haftalik faollik') }}</h2><strong>{{ $weeklyActivity->sum('count') }} {{ __('dars') }}</strong></div><div class="student-activity-chart" role="img" aria-label="{{ __('Oxirgi 7 kunda :n ta dars tugallandi.', ['n' => $weeklyActivity->sum('count')]) }}">@foreach ($weeklyActivity as $day)<div class="student-activity-column"><span class="student-activity-value">{{ $day['count'] }}</span><div class="student-activity-bar" style="height:{{ $day['count'] ? max(10, (int) round($day['count'] / $activityMax * 82)) : 3 }}px" title="{{ $day['label'] }}: {{ $day['count'] }}"></div><small>{{ $day['label'] }}</small></div>@endforeach</div></section>
<section class="student-profile-mentors"><div class="student-section-title"><h2>{{ __('Mentorlarim') }}</h2><a href="{{ route('conversations.index') }}" aria-label="{{ __('Barchasini ko‘rish') }}">↗</a></div>@forelse ($mentorAssignments->unique('mentor_id')->take(4) as $assignment)<div class="student-rail-mentor"><span class="student-person-avatar">{{ mb_strtoupper(mb_substr($assignment->mentor->name, 0, 1)) }}</span><span><strong>{{ $assignment->mentor->name }}</strong><small>{{ Str::limit($assignment->course->title, 35) }}</small></span><a href="{{ route('conversations.show', $assignment) }}">{{ __('Yozish') }}</a></div>@empty<p class="student-muted-note">{{ __('Mentor hali biriktirilmagan.') }}</p>@endforelse</section>
<a href="{{ route('ai.index') }}" class="student-ai-card"><img src="{{ asset('images/ai-tutor-mascot.png') }}" width="90" height="90" alt=""><span class="student-ai-label">{{ __('AI Ustoz') }}</span><strong>{{ __('Savolingiz bormi?') }}</strong><p>{{ __('AI Ustoz bilan birga o‘rganing.') }}</p><span class="student-ai-link">{{ __('Suhbatni boshlash') }} ↗</span></a>
<section class="student-deadlines"><div class="student-section-title"><h2>{{ __('Yaqin deadline‘lar') }}</h2><x-nav-icon name="schedule" /></div>@forelse ($assignments as $assignment)<a href="{{ route('student.assignments') }}"><span class="student-deadline-icon"><x-nav-icon name="assignments" /></span><span><strong>{{ $assignment->title }}</strong><small>{{ $assignment->deadline->format('d.m.Y H:i') }}</small></span></a>@empty<p class="student-muted-note">{{ __('Deadline yo‘q') }}</p>@endforelse</section>
@endsection
