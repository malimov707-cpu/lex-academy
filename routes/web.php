<?php

use App\Http\Controllers\Admin\AcademySettingsController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\CourseModuleController;
use App\Http\Controllers\Admin\CourseSettingsController;
use App\Http\Controllers\Admin\CourtroomAssetController;
use App\Http\Controllers\Admin\CourtroomController as AdminCourtroomController;
use App\Http\Controllers\Admin\CourtroomEvidenceController as AdminCourtroomEvidenceController;
use App\Http\Controllers\Admin\CourtroomPilotController;
use App\Http\Controllers\Admin\CourtroomRehearsalController;
use App\Http\Controllers\Admin\CourtroomReviewController;
use App\Http\Controllers\Admin\EducationCategoryController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\LegalSourceController;
use App\Http\Controllers\Admin\LessonBlockController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LibraryResourceController;
use App\Http\Controllers\Admin\QuizController;
use App\Http\Controllers\Admin\QuizGuidanceController;
use App\Http\Controllers\Admin\QuizReviewController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\TeacherAiController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiWorkspaceController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\LegalInformationCheckController;
use App\Http\Controllers\LegalUpdateController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\Mentor\MentorDashboardController;
use App\Http\Controllers\Mentor\ReviewController;
use App\Http\Controllers\Mentor\StudentProgressController;
use App\Http\Controllers\Mentor\SubmissionGuidanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Public\PageController as PublicPageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Student\AiTutorController;
use App\Http\Controllers\Student\CourtroomEvidenceController;
use App\Http\Controllers\Student\CourtroomRealtimeController;
use App\Http\Controllers\Student\CourtroomScenarioController;
use App\Http\Controllers\Student\CourtroomSessionController;
use App\Http\Controllers\Student\CourtroomSpeechController;
use App\Http\Controllers\Student\CourtroomVoiceController;
use App\Http\Controllers\Student\DocumentTrainerController;
use App\Http\Controllers\Student\LearningAnalyticsController;
use App\Http\Controllers\Student\LearningController;
use App\Http\Controllers\Student\LegalCaseController;
use App\Http\Controllers\Student\QuizAttemptController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\SubmissionController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Http\Controllers\Teacher\TeacherStudentController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPageController::class, 'home'])->name('public.home');
Route::get('courses', [PublicPageController::class, 'courses'])->name('public.courses');
Route::get('courses/{slug}', [PublicPageController::class, 'courseShow'])->name('public.courses.show');
Route::get('about', [PublicPageController::class, 'about'])->name('public.about');
Route::get('contact', [PublicPageController::class, 'contact'])->name('public.contact');
Route::get('faq', [PublicPageController::class, 'faq'])->name('public.faq');

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:student'])->prefix('learn')->name('student.')->group(function (): void {
    Route::get('/', [StudentDashboardController::class, 'index'])->name('dashboard');
    Route::get('/courses/{course}', [LearningController::class, 'show'])->name('course');
    Route::get('/courses/{course}/lessons/{lesson}', [LearningController::class, 'showLesson'])->name('lesson');
    Route::get('/cases', [LegalCaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/{legalCase}', [LegalCaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{legalCase}/feedback', [LegalCaseController::class, 'feedback'])->middleware('throttle:10,1')->name('cases.feedback');
    Route::get('/assignments', [SubmissionController::class, 'index'])->name('assignments');
    Route::post('/assignments/{assignment}/submit', [SubmissionController::class, 'store'])->name('assignments.submit');
    Route::post('/courses/{course}/lessons/{lesson}/complete', [LearningController::class, 'completeLesson'])->name('lesson.complete');
    Route::get('/courses/{course}/quizzes/{quiz}', [QuizAttemptController::class, 'show'])->name('quiz.show');
    Route::post('/courses/{course}/quizzes/{quiz}/start', [QuizAttemptController::class, 'start'])->name('quiz.start');
    Route::post('/courses/{course}/quizzes/{quiz}/autosave', [QuizAttemptController::class, 'autosave'])->name('quiz.autosave');
    Route::post('/courses/{course}/quizzes/{quiz}/submit', [QuizAttemptController::class, 'submit'])->name('quiz.submit');
    Route::get('/courses/{course}/lessons/{lesson}/tutor', [AiTutorController::class, 'history'])->name('tutor');
    Route::post('/courses/{course}/lessons/{lesson}/tutor', [AiTutorController::class, 'send'])->middleware('throttle:10,1')->name('tutor.send');
    Route::get('/document-trainer', [DocumentTrainerController::class, 'index'])->name('document-trainer.index');
    Route::get('/document-trainer/create', [DocumentTrainerController::class, 'create'])->name('document-trainer.create');
    Route::post('/document-trainer', [DocumentTrainerController::class, 'store'])->name('document-trainer.store');
    Route::get('/document-trainer/{draft}', [DocumentTrainerController::class, 'show'])->name('document-trainer.show');
    Route::post('/document-trainer/{draft}/analyze', [DocumentTrainerController::class, 'analyze'])->name('document-trainer.analyze');
});

Route::middleware(['auth', 'role:student'])->prefix('virtual-courtroom')->name('student.courtroom.')->group(function (): void {
    Route::get('/', [CourtroomScenarioController::class, 'index'])->name('index');
    Route::get('/scenarios/{scenario}', [CourtroomScenarioController::class, 'show'])->name('scenarios.show');
    Route::post('/sessions', [CourtroomSessionController::class, 'store'])->name('sessions.store');
    Route::get('/sessions/{session}', [CourtroomSessionController::class, 'show'])->name('sessions.show');
    Route::post('/sessions/{session}/start', [CourtroomSessionController::class, 'start'])->middleware('throttle:courtroom-actions')->name('sessions.start');
    Route::post('/sessions/{session}/actions', [CourtroomSessionController::class, 'action'])->middleware('throttle:courtroom-actions')->name('sessions.actions');
    Route::post('/sessions/{session}/complete', [CourtroomSessionController::class, 'complete'])->middleware('throttle:courtroom-actions')->name('sessions.complete');
    Route::get('/sessions/{session}/timeline', [CourtroomSessionController::class, 'timeline'])->name('sessions.timeline');
    Route::get('/sessions/{session}/report', [CourtroomSessionController::class, 'report'])->name('sessions.report');
    Route::post('/sessions/{session}/report/feedback', [CourtroomSessionController::class, 'feedback'])->middleware('throttle:courtroom-feedback')->name('sessions.report.feedback');
    Route::get('/sessions/{session}/evidence', [CourtroomEvidenceController::class, 'index'])->name('evidence.index');
    Route::get('/sessions/{session}/evidence/{evidence}', [CourtroomEvidenceController::class, 'show'])->name('evidence.show');
    Route::post('/sessions/{session}/evidence/{evidence}/present', [CourtroomEvidenceController::class, 'present'])->middleware('throttle:courtroom-actions')->name('evidence.present');
    Route::get('/sessions/{session}/evidence/{evidence}/annotations', [CourtroomEvidenceController::class, 'annotations'])->name('evidence.annotations');
    Route::post('/sessions/{session}/evidence/{evidence}/annotations', [CourtroomEvidenceController::class, 'annotate'])->middleware('throttle:courtroom-actions')->name('evidence.annotations.store');
    Route::delete('/sessions/{session}/evidence/{evidence}/annotations/{annotation}', [CourtroomEvidenceController::class, 'deleteAnnotation'])->whereNumber('annotation')->middleware('throttle:courtroom-actions')->name('evidence.annotations.destroy');
    Route::post('/sessions/{session}/voice', [CourtroomVoiceController::class, 'store'])->middleware('throttle:courtroom-voice')->name('voice.store');
    Route::post('/sessions/{session}/voice/{voiceSession}/segments', [CourtroomVoiceController::class, 'segment'])->middleware('throttle:courtroom-voice')->name('voice.segments.store');
    Route::post('/sessions/{session}/voice/{voiceSession}/end', [CourtroomVoiceController::class, 'destroy'])->middleware('throttle:courtroom-voice')->name('voice.destroy');
    Route::post('/sessions/{session}/voice/{voiceSession}/transcribe', [CourtroomSpeechController::class, 'transcribe'])->middleware('throttle:courtroom-speech')->name('voice.transcribe');
    Route::post('/sessions/{session}/voice/{voiceSession}/realtime', CourtroomRealtimeController::class)->middleware('throttle:courtroom-realtime')->name('voice.realtime');
    Route::post('/sessions/{session}/voice/{voiceSession}/speech/{turn}', [CourtroomSpeechController::class, 'speak'])->middleware('throttle:courtroom-speech')->name('voice.speak');
});

Route::middleware(['auth', 'role:mentor,academy_admin,super_admin'])->prefix('mentor')->name('mentor.')->group(function (): void {
    Route::get('/', [MentorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews');
    Route::get('/reviews/{submission}', [ReviewController::class, 'show'])->name('reviews.show');
    Route::post('/reviews/{submission}', [ReviewController::class, 'storeReview'])->name('reviews.store');
});

Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function (): void {
    Route::get('/', [TeacherDashboardController::class, 'index'])->name('dashboard');
    Route::get('/students', TeacherStudentController::class)->name('students.index');
});

// Academy admin (super admin ham kiradi)
Route::middleware(['auth', 'role:super_admin,academy_admin,teacher'])
    ->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/virtual-courtroom', [AdminCourtroomController::class, 'index'])->name('courtroom.index');
        Route::get('/virtual-courtroom/pilot', CourtroomPilotController::class)->name('courtroom.pilot');
        Route::get('/virtual-courtroom/create', [AdminCourtroomController::class, 'create'])->name('courtroom.create');
        Route::post('/virtual-courtroom', [AdminCourtroomController::class, 'store'])->name('courtroom.store');
        Route::get('/virtual-courtroom/scenarios/{scenario}/edit', [AdminCourtroomController::class, 'edit'])->name('courtroom.edit');
        Route::get('/virtual-courtroom/scenarios/{scenario}/preview', [AdminCourtroomController::class, 'preview'])->name('courtroom.preview');
        Route::match(['get', 'post'], '/virtual-courtroom/scenarios/{scenario}/versions/{version}/rehearsal', CourtroomRehearsalController::class)->whereNumber('version')->name('courtroom.rehearsal');
        Route::get('/virtual-courtroom/scenarios/{scenario}/references/library', [AdminCourtroomController::class, 'library'])->name('courtroom.references.library');
        Route::post('/virtual-courtroom/scenarios/{scenario}/references/library/{source}', [AdminCourtroomController::class, 'attachLibraryReference'])->whereNumber('source')->name('courtroom.references.attach');
        Route::patch('/virtual-courtroom/scenarios/{scenario}/references/{reference}/verify', [AdminCourtroomController::class, 'verifyReference'])->whereNumber('reference')->name('courtroom.references.verify');
        Route::get('/virtual-courtroom/scenarios/{scenario}/definition', [AdminCourtroomController::class, 'definition'])->name('courtroom.definition');
        Route::put('/virtual-courtroom/scenarios/{scenario}/definition', [AdminCourtroomController::class, 'updateDefinition'])->name('courtroom.definition.update');
        Route::get('/virtual-courtroom/scenarios/{scenario}/history', [AdminCourtroomController::class, 'history'])->name('courtroom.history');
        Route::post('/virtual-courtroom/scenarios/{scenario}/duplicate', [AdminCourtroomController::class, 'duplicate'])->name('courtroom.duplicate');
        Route::get('/virtual-courtroom/scenarios/{scenario}/evidence', [AdminCourtroomEvidenceController::class, 'index'])->name('courtroom.evidence.index');
        Route::post('/virtual-courtroom/scenarios/{scenario}/evidence', [AdminCourtroomEvidenceController::class, 'store'])->name('courtroom.evidence.store');
        Route::get('/virtual-courtroom/scenarios/{scenario}/evidence/{evidence}', [AdminCourtroomEvidenceController::class, 'show'])->name('courtroom.evidence.show');
        Route::delete('/virtual-courtroom/scenarios/{scenario}/evidence/{evidence}', [AdminCourtroomEvidenceController::class, 'destroy'])->name('courtroom.evidence.destroy');
        Route::get('/virtual-courtroom/assets', [CourtroomAssetController::class, 'index'])->name('courtroom.assets.index');
        Route::patch('/virtual-courtroom/assets/{asset}', [CourtroomAssetController::class, 'update'])->name('courtroom.assets.update');
        Route::put('/virtual-courtroom/scenarios/{scenario}', [AdminCourtroomController::class, 'update'])->name('courtroom.update');
        Route::patch('/virtual-courtroom/scenarios/{scenario}/publish', [AdminCourtroomController::class, 'publish'])->name('courtroom.publish');
        Route::patch('/virtual-courtroom/scenarios/{scenario}/archive', [AdminCourtroomController::class, 'archive'])->name('courtroom.archive');
        Route::get('/virtual-courtroom/evaluations/{evaluation}', [CourtroomReviewController::class, 'show'])->name('courtroom.reviews.show');
        Route::post('/virtual-courtroom/evaluations/{evaluation}', [CourtroomReviewController::class, 'store'])->name('courtroom.reviews.store');

        Route::resource('categories', EducationCategoryController::class)
            ->only(['index', 'store', 'update', 'destroy'])->parameters(['categories' => 'category']);

        Route::get('/courses/{course}/cases', [App\Http\Controllers\Admin\LegalCaseController::class, 'index'])->name('courses.cases.index');
        Route::post('/courses/{course}/cases', [App\Http\Controllers\Admin\LegalCaseController::class, 'store'])->name('courses.cases.store');
        Route::get('/cases/{legalCase}/edit', [App\Http\Controllers\Admin\LegalCaseController::class, 'edit'])->name('cases.edit');
        Route::put('/cases/{legalCase}', [App\Http\Controllers\Admin\LegalCaseController::class, 'update'])->name('cases.update');

        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::patch('/courses/{course}/publish', [CourseController::class, 'publish'])->name('courses.publish');
        Route::patch('/courses/{course}/archive', [CourseController::class, 'archive'])->name('courses.archive');
        Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

        Route::get('/courses/{course}/enrollments', [EnrollmentController::class, 'index'])->name('courses.enrollments.index');
        Route::post('/courses/{course}/enrollments', [EnrollmentController::class, 'store'])->name('courses.enrollments.store');
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('courses.enrollments.destroy');

        Route::get('/courses/{course}/assignments', [AssignmentController::class, 'index'])->name('courses.assignments.index');
        Route::post('/courses/{course}/assignments', [AssignmentController::class, 'store'])->name('courses.assignments.store');
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('courses.assignments.destroy');

        Route::get('/courses/{course}/quizzes', [QuizController::class, 'index'])->name('courses.quizzes.index');
        Route::post('/courses/{course}/quizzes', [QuizController::class, 'store'])->name('courses.quizzes.store');
        Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy'])->name('courses.quizzes.destroy');

        Route::get('/courses/{course}/modules', [CourseModuleController::class, 'index'])->name('courses.modules.index');
        Route::post('/courses/{course}/modules', [CourseModuleController::class, 'store'])->name('courses.modules.store');
        Route::put('/modules/{module}', [CourseModuleController::class, 'update'])->name('modules.update');
        Route::patch('/modules/{module}/up', [CourseModuleController::class, 'moveUp'])->name('modules.up');
        Route::patch('/modules/{module}/down', [CourseModuleController::class, 'moveDown'])->name('modules.down');
        Route::delete('/modules/{module}', [CourseModuleController::class, 'destroy'])->name('modules.destroy');

        Route::get('/modules/{module}/lessons', [LessonController::class, 'index'])->name('modules.lessons.index');
        Route::post('/modules/{module}/lessons', [LessonController::class, 'store'])->name('modules.lessons.store');
        Route::get('/lessons/{lesson}/edit', [LessonController::class, 'edit'])->name('lessons.edit');
        Route::put('/lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
        Route::patch('/lessons/{lesson}/publish', [LessonController::class, 'publish'])->name('lessons.publish');
        Route::patch('/lessons/{lesson}/up', [LessonController::class, 'moveUp'])->name('lessons.up');
        Route::patch('/lessons/{lesson}/down', [LessonController::class, 'moveDown'])->name('lessons.down');
        Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');
        Route::post('/lessons/{lesson}/materials', [LessonController::class, 'addMaterial'])->name('lessons.materials.store');
    });

Route::middleware(['auth', 'role:super_admin,academy_admin,teacher'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/quizzes/{quiz}/results', [QuizReviewController::class, 'index'])->name('quizzes.results');
    Route::post('/quiz-attempts/{attempt}/grade', [QuizReviewController::class, 'store'])->name('quizzes.grade');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('/certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('/materials/{material}/download', [MaterialController::class, 'lesson'])->name('materials.download');
    Route::get('/submission-files/{file}/download', [MaterialController::class, 'submission'])->name('submission-files.download');
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
});
Route::get('/verify/{code}', [CertificateController::class, 'verify'])->middleware('throttle:30,1')->name('certificates.verify');
Route::middleware(['auth', 'role:super_admin,academy_admin,teacher'])->group(function (): void {
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::put('/schedule/{schedule}', [ScheduleController::class, 'update'])->name('schedule.update');
    Route::get('/schedule/{schedule}/attendance', [ScheduleController::class, 'show'])->name('schedule.attendance');
    Route::post('/schedule/{schedule}/attendance', [ScheduleController::class, 'attendance'])->name('schedule.attendance.store');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
});
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/control-center', [DashboardController::class, 'controlCenter'])->name('control-center');
});
Route::middleware(['auth', 'role:super_admin,academy_admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/mentor-assignments', [UserController::class, 'assignMentor'])->name('mentor-assignments.store');
    Route::resource('groups', GroupController::class)->only('index', 'store', 'update', 'destroy');
    Route::post('/groups/{group}/members', [GroupController::class, 'member'])->name('groups.members');
    Route::delete('/groups/{group}/members/{student}', [GroupController::class, 'removeMember'])->name('groups.members.destroy');
    Route::post('/courses/{course}/certificates', [CertificateController::class, 'store'])->name('certificates.store');
});
Route::patch('/certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])->middleware(['auth', 'role:super_admin,academy_admin'])->name('certificates.revoke');

Route::get('/notifications', [NotificationController::class, 'index'])->middleware('auth')->name('notifications.index');
Route::patch('/notifications/{notification}', [NotificationController::class, 'update'])->middleware('auth')->name('notifications.update');

Route::post('/admin/quiz-answers/{answer}/guidance', [QuizGuidanceController::class, 'store'])
    ->middleware(['auth', 'role:super_admin,academy_admin,teacher', 'throttle:10,1'])->name('admin.quizzes.guidance');

Route::middleware(['auth', 'role:super_admin,academy_admin,teacher'])->group(function (): void {
    Route::put('/admin/lessons/{lesson}/blocks', [LessonBlockController::class, 'update'])->name('admin.lessons.blocks.update');
    Route::post('/admin/lessons/{lesson}/blocks/reorder', [LessonBlockController::class, 'reorder'])->name('admin.lessons.blocks.reorder');
    Route::get('/admin/lessons/{lesson}/preview', [LessonBlockController::class, 'preview'])->name('admin.lessons.preview');
    Route::get('/admin/courses/{course}/ai', [TeacherAiController::class, 'index'])->name('admin.teacher-ai.index');
    Route::post('/admin/courses/{course}/ai', [TeacherAiController::class, 'store'])->middleware('throttle:10,1')->name('admin.teacher-ai.store');
    Route::patch('/admin/ai-drafts/{draft}', [TeacherAiController::class, 'update'])->name('admin.teacher-ai.approve');
});
Route::post('/learn/blocks/{block}/respond', [LessonBlockController::class, 'respond'])->middleware(['auth', 'role:student'])->name('student.blocks.respond');
Route::middleware(['auth', 'role:student,mentor'])->group(function (): void {
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{assignment}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations/{assignment}', [ConversationController::class, 'store'])->middleware('throttle:30,1')->name('conversations.store');
});
Route::middleware('auth')->group(function (): void {
    Route::get('/courses/{course}/discussions', [DiscussionController::class, 'index'])->name('discussions.index');
    Route::post('/courses/{course}/discussions', [DiscussionController::class, 'store'])->middleware('throttle:10,1')->name('discussions.store');
    Route::get('/discussions/{discussion}', [DiscussionController::class, 'show'])->name('discussions.show');
    Route::post('/discussions/{discussion}/replies', [DiscussionController::class, 'reply'])->middleware('throttle:30,1')->name('discussions.reply');
    Route::patch('/discussions/{discussion}', [DiscussionController::class, 'update'])->name('discussions.update');
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
Route::post('/theme', [ThemeController::class, 'update'])->middleware('throttle:30,1')->name('theme.update');
Route::post('/bandwidth', [ThemeController::class, 'bandwidth'])->middleware('throttle:30,1')->name('bandwidth.update');
Route::get('/search', [SearchController::class, 'index'])->middleware(['auth', 'throttle:60,1'])->name('search');

Route::post('/admin/courses/{course}/modules/reorder', [CourseModuleController::class, 'reorder'])->middleware(['auth', 'role:super_admin,academy_admin,teacher'])->name('admin.modules.reorder');
Route::post('/admin/modules/{module}/lessons/reorder', [LessonController::class, 'reorder'])->middleware(['auth', 'role:super_admin,academy_admin,teacher'])->name('admin.lessons.reorder');

Route::middleware(['auth', 'role:super_admin,academy_admin,teacher'])->group(function (): void {
    Route::get('/admin/courses/{course}/settings', [CourseSettingsController::class, 'edit'])->name('admin.course-settings.edit');
    Route::put('/admin/courses/{course}/settings', [CourseSettingsController::class, 'update'])->name('admin.course-settings.update');
    Route::post('/admin/courses/{course}/teachers', [CourseSettingsController::class, 'assignTeacher'])->name('admin.course-settings.teachers.store');
    Route::delete('/admin/courses/{course}/teachers/{teacher}', [CourseSettingsController::class, 'removeTeacher'])->name('admin.course-settings.teachers.destroy');
    Route::get('/admin/legal-sources', [LegalSourceController::class, 'index'])->name('admin.legal-sources.index');
    Route::post('/admin/legal-sources', [LegalSourceController::class, 'store'])->name('admin.legal-sources.store');
    Route::patch('/admin/legal-sources/{source}', [LegalSourceController::class, 'update'])->name('admin.legal-sources.update');
    Route::post('/admin/legal-sources/{source}/checks/{check}/review', [LegalSourceController::class, 'reviewCheck'])->name('admin.legal-sources.checks.review');
});
Route::get('/learn/courses/{course}/analytics', [LearningAnalyticsController::class, 'show'])->middleware(['auth', 'role:student'])->name('student.analytics');

Route::middleware(['auth', 'role:mentor,super_admin,academy_admin'])->group(function (): void {
    Route::get('/mentor/students', [StudentProgressController::class, 'index'])->name('mentor.students.index');
    Route::get('/mentor/students/{assignment}', [StudentProgressController::class, 'show'])->name('mentor.students.show');
});

Route::get('/courses/{course}/cover', [MaterialController::class, 'cover'])->middleware('auth')->name('courses.cover');

Route::post('/learn/assignments/{assignment}/draft', [SubmissionController::class, 'draft'])->middleware(['auth', 'role:student'])->name('student.assignments.draft');

Route::post('/mentor/submissions/{submission}/guidance', [SubmissionGuidanceController::class, 'store'])->middleware(['auth', 'role:mentor,academy_admin,super_admin', 'throttle:10,1'])->name('mentor.submissions.guidance');

Route::put('/admin/assignments/{assignment}', [AssignmentController::class, 'update'])->middleware(['auth', 'role:teacher,academy_admin,super_admin'])->name('admin.assignments.update');

Route::post('/mentor/reviews/{submission}/claim', [ReviewController::class, 'claim'])->middleware(['auth', 'role:mentor,academy_admin,super_admin'])->name('mentor.reviews.claim');

Route::middleware(['auth', 'role:super_admin,academy_admin'])->group(function (): void {
    Route::get('/admin/settings', [AcademySettingsController::class, 'edit'])->name('admin.settings.edit');
    Route::put('/admin/settings', [AcademySettingsController::class, 'update'])->name('admin.settings.update');
});

Route::get('/ai', [AiWorkspaceController::class, 'index'])->middleware('auth')->name('ai.index');

Route::middleware('auth')->prefix('ai')->name('ai.')->group(function (): void {
    Route::get('/conversations', [AiTutorController::class, 'listSessions'])->name('conversations.index');
    Route::post('/conversations', [AiTutorController::class, 'storeSession'])->name('conversations.store');
    Route::get('/conversations/{session}', [AiTutorController::class, 'showSession'])->name('conversations.show');
    Route::patch('/conversations/{session}', [AiTutorController::class, 'updateSession'])->name('conversations.update');
    Route::delete('/conversations/{session}', [AiTutorController::class, 'destroySession'])->name('conversations.destroy');
    Route::post('/conversations/{session}/messages', [AiTutorController::class, 'sendMessage'])->name('conversations.messages');
    Route::post('/conversations/{session}/actions', [AiTutorController::class, 'executeAction'])->name('conversations.actions');
    Route::post('/chat', [AiTutorController::class, 'sendMessage'])->name('chat.send');
    Route::post('/action', [AiTutorController::class, 'executeAction'])->name('action.execute');
    Route::get('/health', [AiTutorController::class, 'health'])->name('health');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/legal-updates', [LegalUpdateController::class, 'index'])->name('legal-updates.index');
    Route::put('/legal-updates/subscriptions', [LegalUpdateController::class, 'updateSubscriptions'])->name('legal-updates.subscriptions.update');
    Route::get('/legal-check', [LegalInformationCheckController::class, 'index'])->name('legal-information-check.index');
    Route::post('/legal-check', [LegalInformationCheckController::class, 'store'])->middleware('throttle:5,1')->name('legal-information-check.store');
    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/library/{resource}', [LibraryController::class, 'show'])->name('library.show');
    Route::put('/library/{resource}/bookmark', [LibraryController::class, 'bookmark'])->name('library.bookmark');
    Route::put('/library/{resource}/reading', [LibraryController::class, 'reading'])->name('library.reading');
});
Route::middleware(['auth', 'role:super_admin,academy_admin'])->group(function (): void {
    Route::get('/admin/library', [LibraryResourceController::class, 'index'])->name('admin.library.index');
    Route::post('/admin/library', [LibraryResourceController::class, 'store'])->name('admin.library.store');
    Route::get('/admin/library/{resource}/edit', [LibraryResourceController::class, 'edit'])->name('admin.library.edit');
    Route::put('/admin/library/{resource}', [LibraryResourceController::class, 'update'])->name('admin.library.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/library/{resource}/read', [LibraryController::class, 'reader'])->name('library.reader');
    Route::get('/library/{resource}/file', [LibraryController::class, 'file'])->name('library.file');
});

require __DIR__.'/courtroom-groups.php';

require __DIR__.'/courtroom-results.php';
require __DIR__.'/teacher-workspace.php';
