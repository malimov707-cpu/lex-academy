@php
    use App\Enums\UserRole;

    $user = auth()->user();
    $role = $user->role;
    $isAdmin = $user->isAdmin();
    $isTeacher = $role === UserRole::Teacher;
    $isMentor = $role === UserRole::Mentor;
    $isStudent = $role === UserRole::Student;

    /**
     * Navigatsiya guruhlari — rol bo'yicha filtrlanadi.
     * Har bir element: [route nomi, label, ikonka, faol pattern(lar)]
     */
    $groups = [];

    /*
     * "Boshqaruv": admin uchun umumiy panel, teacher/mentor uchun o'z paneli (takror bo'lmasin),
     * student uchun umuman ko'rsatilmaydi — uning asosiy sahifasi /learn.
     */
    $groups[__('Asosiy')] = $isAdmin
        ? [['dashboard', __('Boshqaruv'), 'dashboard', ['dashboard']]]
        : [];

    // Super Admin uchun yangi Control Center (boshqaruv markazi)
    if ($user->hasRole(UserRole::SuperAdmin) && \Illuminate\Support\Facades\Route::has('admin.control-center')) {
        $groups[__('Asosiy')][] = ['admin.control-center', __('Boshqaruv markazi'), 'dashboard', ['admin.control-center']];
    }

    if ($isStudent) {
        $groups[__('Asosiy')][] = ['student.dashboard', __('O\'qish'), 'learning', ['student.dashboard', 'student.course', 'student.lesson', 'student.quiz*']];
        $groups[__('Asosiy')][] = ['student.cases.index', __('Kazuslar'), 'scale', ['student.cases.*']];
        $groups[__('Asosiy')][] = ['student.courtroom.index', __('Virtual sud'), 'courtroom', ['student.courtroom.*']];
        $groups[__('Asosiy')][] = ['courtroom.groups.index', __('Guruhli Virtual sud'), 'groups', ['courtroom.groups.*', 'courtroom.results.*']];
    }
    if ($isTeacher) {
        $groups[__('Asosiy')][] = ['teacher.dashboard', __('O\'qituvchi paneli'), 'analytics', ['teacher.dashboard']];
    }
    if ($isMentor) {
        $groups[__('Asosiy')][] = ['mentor.dashboard', __('Mentor paneli'), 'analytics', ['mentor.dashboard']];
    }

    $groups[__('Asosiy')][] = ['library.index', __('Huquqiy kutubxona'), 'sources', ['library.*', 'admin.library.*']];
    $groups[__('Asosiy')][] = ['legal-information-check.index', __('Da’voni tekshirish'), 'law', ['legal-information-check.*']];
    $groups[__('Asosiy')][] = ['ai.index', __('AI Ustoz'), 'tutor', ['ai.index', 'student.tutor*', 'admin.teacher-ai.*']];

    if ($isStudent || $isMentor) {
        $groups[__('Muloqot')] = [
            ['conversations.index', __('Shaxsiy muloqot'), 'chat', ['conversations.*']],
        ];
    }

    $taelim = [];
    if ($isAdmin || $isTeacher) {
        $taelim[] = ['admin.courses.index', __('Kurslar'), 'courses', ['admin.courses.*', 'admin.modules.*', 'admin.lessons.*']];
        $taelim[] = ['admin.courtroom.index', __('Virtual sud'), 'courtroom', ['admin.courtroom.*']];
    }
    if ($isAdmin) {
        $taelim[] = ['admin.categories.index', __('Kategoriyalar'), 'module', ['admin.categories.*']];
        $taelim[] = ['admin.legal-sources.index', __('Huquqiy manbalar'), 'sources', ['admin.legal-sources.*']];
    }
    if ($taelim !== []) {
        $groups[__('Ta\'lim boshqaruvi')] = $taelim;
    }

    $oquv = [];
    if ($isStudent) {
        $oquv[] = ['student.assignments', __('Topshiriqlar'), 'assignments', ['student.assignments*']];
        $oquv[] = ['student.document-trainer.index', __('Hujjat trenajyori'), 'law', ['student.document-trainer.*']];
    }
    if ($isAdmin || $isMentor) {
        $oquv[] = ['mentor.reviews', __('Tekshirish'), 'review', ['mentor.reviews*']];
        $oquv[] = ['mentor.students.index', __('Talabalar'), 'users', ['mentor.students.*']];
    }
    if ($oquv !== []) {
        $groups[__('O\'quv jarayoni')] = $oquv;
    }

    $tizim = [
        ['notifications.index', __('Bildirishnomalar'), 'notification', ['notifications.*']],
        ['schedule.index', __('Jadval'), 'schedule', ['schedule.*']],
        ['announcements.index', __('E\'lonlar'), 'announcement', ['announcements.*']],
        ['certificates.index', __('Sertifikatlar'), 'certificate', ['certificates.*']],
    ];
    if ($isAdmin) {
        $tizim[] = ['admin.users.index', __('Foydalanuvchilar'), 'users', ['admin.users.*']];
        $tizim[] = ['admin.groups.index', __('Guruhlar'), 'groups', ['admin.groups.*']];
        $tizim[] = ['admin.reports.index', __('Hisobotlar'), 'reports', ['admin.reports.*']];
        $tizim[] = ['admin.settings.edit', __('Sozlamalar'), 'settings', ['admin.settings.*']];
    }
    $groups[__('Tizim')] = $tizim;
    if ($role === UserRole::SuperAdmin) {
        $groups = [__('Boshqaruv') => [
            ['admin.control-center', 'Dashboard', 'dashboard', ['admin.control-center']],
            ['admin.reports.index', __('Platforma statistikasi'), 'analytics', ['admin.reports.*']],
            ['admin.users.index', __('Foydalanuvchilar'), 'users', ['admin.users.*']],
            ['admin.courses.index', __('Kurslarni boshqarish'), 'courses', ['admin.courses.*', 'admin.modules.*', 'admin.lessons.*']],
            ['admin.courtroom.index', __('Virtual sud'), 'courtroom', ['admin.courtroom.*']],
            ['ai.index', __('AI Ustoz'), 'tutor', ['ai.*']],
            ['admin.library.index', __('Kontent boshqaruvi'), 'sources', ['admin.library.*', 'library.*']],
            ['admin.settings.edit', __('Tizim sozlamalari'), 'settings', ['admin.settings.*']],
            ['announcements.index', __('E’lonlar'), 'announcement', ['announcements.*']],
            ['schedule.index', __('Dars jadvali'), 'schedule', ['schedule.*']],
            ['certificates.index', __('Sertifikatlar'), 'certificate', ['certificates.*']],
            ['notifications.index', __('Bildirishnomalar'), 'notification', ['notifications.*']],
        ], __('Qo‘shimcha bo‘limlar') => [
            ['admin.groups.index', __('Guruhlar'), 'groups', ['admin.groups.*']],
            ['admin.categories.index', __('Kategoriyalar'), 'module', ['admin.categories.*']],
            ['admin.legal-sources.index', __('Huquqiy manbalar'), 'sources', ['admin.legal-sources.*']],
            ['mentor.reviews', __('Tekshirish'), 'review', ['mentor.reviews*']],
            ['mentor.students.index', __('Talabalar'), 'users', ['mentor.students.*']],
        ]];
    }
@endphp

@foreach ($groups as $groupLabel => $items)
    @php
        $visible = array_values(array_filter($items, fn (array $item): bool => \Illuminate\Support\Facades\Route::has($item[0])));
    @endphp
    @if ($visible !== [])
        <p class="edu-nav-group-label">{{ $groupLabel }}</p>
        @foreach ($visible as [$routeName, $label, $icon, $patterns])
            @php $active = request()->routeIs(...$patterns); @endphp
            <a href="{{ route($routeName) }}"
               title="{{ $label }}"
               @if ($active) aria-current="page" @endif
               class="edu-nav-link {{ $active ? 'is-active' : '' }}">
                <x-nav-icon :name="$icon" />
                <span>{{ $label }}</span>
            </a>
        @endforeach
    @endif
@endforeach
