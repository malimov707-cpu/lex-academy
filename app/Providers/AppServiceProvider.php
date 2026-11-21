<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\AcademySetting;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\ClassSchedule;
use App\Models\CourseEnrollment;
use App\Models\LegalCase;
use App\Models\Lesson;
use App\Models\MentorAssignment;
use App\Models\MentorReview;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Observers\EducationNotificationObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer(['layouts.app', 'layouts.ai', 'layouts.student', 'layouts.courtroom'], function (View $view): void {
            $view->with('academySettings', AcademySetting::query()->find(1));
        });
        \Illuminate\Support\Facades\View::composer('layouts.app', function (View $view): void {
            $user = auth()->user();
            $view->with('unread', $user && Schema::hasTable('notifications') ? $user->unreadNotifications()->count() : 0);
        });
        \Illuminate\Support\Facades\View::composer('layouts.student', function (View $view): void {
            $user = auth()->user();

            if (! $user?->hasRole(UserRole::Student)) {
                $view->with('studentNavigationMentors', collect());

                return;
            }

            $viewData = $view->getData();
            $mentorAssignments = array_key_exists('mentorAssignments', $viewData)
                ? collect($viewData['mentorAssignments'])
                : MentorAssignment::query()
                    ->where('student_id', $user->id)
                    ->with('mentor:id,name')
                    ->latest('id')
                    ->limit(10)
                    ->get();

            $view->with('studentNavigationMentors', $mentorAssignments->unique('mentor_id')->take(3)->values());
        });
        foreach ([CourseEnrollment::class, Certificate::class, MentorReview::class,
            AssignmentSubmission::class, Assignment::class, Quiz::class,
            ClassSchedule::class, Lesson::class, LegalCase::class, QuizAttempt::class] as $model) {
            $model::observe(EducationNotificationObserver::class);
        }
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::connection()->getPdo();
            Cache::get('academy-health');
        });
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(20)->by($request->ip()),
            Limit::perHour(50)->by($request->ip()),
        ]);
        RateLimiter::for('courtroom-actions', fn (Request $request) => [
            Limit::perMinute(30)->by(($request->user()?->id ?? $request->ip()).'|'.(is_object($request->route('session')) ? $request->route('session')->getKey() : ($request->route('session') ?? 'new'))),
            Limit::perHour(600)->by($request->user()?->id ?? $request->ip()),
        ]);
        RateLimiter::for('courtroom-voice', fn (Request $request) => [
            Limit::perMinute(60)->by(($request->user()?->id ?? $request->ip()).'|'.(is_object($request->route('session')) ? $request->route('session')->getKey() : ($request->route('session') ?? 'new'))),
            Limit::perHour(900)->by($request->user()?->id ?? $request->ip()),
        ]);
        RateLimiter::for('courtroom-feedback', fn (Request $request) => Limit::perMinute(3)->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('courtroom-speech', fn (Request $request) => [
            Limit::perMinute(12)->by($request->user()?->id ?? $request->ip()),
            Limit::perHour(100)->by($request->user()?->id ?? $request->ip()),
        ]);
        RateLimiter::for('courtroom-realtime', fn (Request $request) => [
            Limit::perMinute(6)->by($request->user()?->id ?? $request->ip()),
            Limit::perHour(36)->by($request->user()?->id ?? $request->ip()),
        ]);
    }
}
