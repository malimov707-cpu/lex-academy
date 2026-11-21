@php
    $dashboardUrl = route('teacher.dashboard');
    $courtroomUrl = route('admin.courtroom.index');
    $primaryLinks = [
        [__('Bosh sahifa'), 'home', $dashboardUrl, request()->routeIs('teacher.dashboard')],
        [__('Mening kurslarim'), 'open-book', route('admin.courses.index'), request()->routeIs('admin.courses.index', 'admin.course-settings.*', 'admin.courses.modules.*', 'admin.courses.cases.*', 'admin.cases.*')],
        [__('Virtual Courtroom'), 'courtroom', $courtroomUrl, request()->routeIs('admin.courtroom.*') && ! request()->routeIs('admin.courtroom.reviews.*', 'admin.courtroom.pilot')],
        [__('Guruhli Virtual sud'), 'groups', route('courtroom.groups.index'), request()->routeIs('courtroom.groups.*', 'courtroom.results.*')],
        [__('Talabalar'), 'users', route('teacher.students.index'), request()->routeIs('teacher.students.*', 'admin.courses.enrollments.*')],
        [__('Baholash'), 'assignments', route('teacher.assessments.index'), request()->routeIs('teacher.assessments.*', 'admin.courses.assignments.*', 'admin.courses.quizzes.*', 'admin.quizzes.*', 'admin.courtroom.reviews.*')],
        [__('Simulyatsiyalar'), 'simulation', $courtroomUrl.'#courtroom-sessions', request()->routeIs('admin.courtroom.pilot')],
        [__('Darslar'), 'lesson', route('teacher.lessons.index'), request()->routeIs('teacher.lessons.*', 'admin.modules.*', 'admin.lessons.*')],
        [__('Statistika'), 'analytics', $dashboardUrl.'#teacher-statistics', false],
        [__('Xabarlar'), 'mail', route('notifications.index'), request()->routeIs('notifications.*')],
    ];
    $additionalLinks = [
        [__('Dars jadvali'), 'lesson', 'schedule.index'],
        [__('Huquqiy kutubxona'), 'sources', 'library.index'],
        [__('Da’voni tekshirish'), 'law', 'legal-information-check.index'],
        [__('AI Ustoz'), 'tutor', 'ai.index'],
        [__('E’lonlar'), 'announcement', 'announcements.index'],
        [__('Sertifikatlar'), 'certificate', 'certificates.index'],
    ];
@endphp

<a href="{{ $dashboardUrl }}" class="teacher-brand">
    <x-nav-icon name="academy-scale" class="teacher-brand-emblem" />
    <strong>Legal EDU</strong>
    <small>Teacher Panel</small>
</a>
<button type="button" class="teacher-drawer-close edu-only-mobile-wide" data-navigation-toggle aria-label="{{ __('Menyuni yopish') }}">
    <x-nav-icon name="close" />
</button>

<nav class="teacher-navigation" aria-label="{{ __('Akademiya bo‘limlari') }}">
    @foreach ($primaryLinks as [$label, $icon, $url, $active])
        <a href="{{ $url }}" @if ($active) aria-current="page" @endif class="teacher-nav-item" data-teacher-nav-link>
            <x-nav-icon :name="$icon" />
            <span>{{ $label }}</span>
            @if ($icon === 'mail' && $unread > 0)
                <small class="teacher-unread">{{ $unread > 99 ? '99+' : $unread }}</small>
            @endif
        </a>
    @endforeach
    <button type="button" class="teacher-nav-item" aria-haspopup="dialog" aria-controls="teacher-preferences" data-teacher-preferences-open>
        <x-nav-icon name="gear" />
        <span>{{ __('Sozlamalar') }}</span>
    </button>
</nav>

<div class="teacher-account">
    <details class="teacher-account-details">
        <summary title="{{ __('Qo‘shimcha bo‘limlar') }}">
            <span class="teacher-account-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::initials($navUser->name, true) }}</span>
            <span class="min-w-0 flex-1">
                <strong>{{ $navUser->name }}</strong>
                <small>{{ __('O‘qituvchi') }}</small>
            </span>
            <x-nav-icon name="chevron-down" class="teacher-account-chevron" />
            <span class="sr-only">{{ __('Qo‘shimcha bo‘limlar') }}</span>
        </summary>
        <nav class="teacher-account-links" aria-label="{{ __('Qo‘shimcha bo‘limlar') }}">
            <p>{{ $navUser->email }}</p>
            @foreach ($additionalLinks as [$label, $icon, $routeName])
                <a href="{{ route($routeName) }}" @if (request()->routeIs($routeName)) aria-current="page" @endif>
                    <x-nav-icon :name="$icon" />{{ $label }}
                </a>
            @endforeach
        </nav>
    </details>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="teacher-logout"><x-nav-icon name="logout" />{{ __('Chiqish') }}</button>
    </form>
</div>
