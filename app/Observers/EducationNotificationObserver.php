<?php

namespace App\Observers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\ClassSchedule;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LegalCase;
use App\Models\Lesson;
use App\Models\MentorAssignment;
use App\Models\MentorReview;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Notifications\AcademyNotification;
use Illuminate\Database\Eloquent\Model;

class EducationNotificationObserver
{
    public function created(Model $model): void
    {
        if ($model instanceof CourseEnrollment) {
            User::find($model->student_id)?->notify(new AcademyNotification('Yangi kursga biriktirildingiz.', '/learn'));
        } elseif ($model instanceof Certificate) {
            $model->student->notify(new AcademyNotification('Sertifikat berildi.', '/certificates/'.$model->id));
        } elseif ($model instanceof MentorReview) {
            $submission = AssignmentSubmission::findOrFail($model->submission_id);
            $submission->student->notify(new AcademyNotification('Mentor ishingizni tekshirdi: :decision', '/learn/assignments', ['decision' => $model->decision]));
        } elseif ($model instanceof AssignmentSubmission && $model->status !== 'in_progress') {
            $ids = MentorAssignment::query()->where('student_id', $model->student_id)->where('course_id', $model->assignment->course_id)->pluck('mentor_id');
            foreach (User::query()->whereIn('id', $ids)->get() as $mentor) {
                $mentor->notify(new AcademyNotification('Tekshirish uchun yangi ish yuborildi.', '/mentor/reviews/'.$model->id));
            }
        } elseif ($model instanceof Assignment && $model->type !== 'legal_case') {
            $this->course($model->course, 'Yangi topshiriq: :title', '/learn/assignments', parameters: ['title' => $model->title]);
        } elseif ($model instanceof Quiz) {
            $this->course($model->course, 'Yangi test yoki imtihon: :title', '/learn/courses/'.$model->course_id.'/quizzes/'.$model->id, parameters: ['title' => $model->title]);
        } elseif ($model instanceof ClassSchedule) {
            $this->schedule($model, 'Yangi dars jadvalga qo‘shildi.');
        } elseif ($model instanceof Lesson && $model->status === 'published') {
            $this->lesson($model);
        } elseif ($model instanceof LegalCase && $model->status === 'published') {
            $this->course($model->assignment->course, 'Yangi kazus nashr qilindi.', '/learn/cases/'.$model->id);
        }
    }

    public function updated(Model $model): void
    {
        if ($model instanceof AssignmentSubmission && $model->wasChanged('status') && $model->getOriginal('status') === 'in_progress' && $model->status === 'submitted') {
            $this->created($model);
        }

        if ($model instanceof Lesson && $model->wasChanged('status') && $model->status === 'published') {
            $this->lesson($model);
        } elseif ($model instanceof LegalCase && $model->wasChanged('status') && $model->status === 'published') {
            $this->course($model->assignment->course, 'Yangi kazus nashr qilindi.', '/learn/cases/'.$model->id);
        } elseif ($model instanceof ClassSchedule && $model->wasChanged(['starts_at', 'ends_at', 'location'])) {
            $this->schedule($model, 'Dars jadvali o‘zgardi.');
        } elseif ($model instanceof QuizAttempt && $model->wasChanged('status') && $model->status !== 'in_progress') {
            $model->student->notify(new AcademyNotification('Test natijasi yangilandi.', '/learn/courses/'.$model->quiz->course_id.'/quizzes/'.$model->quiz_id));
        }
    }

    private function lesson(Lesson $lesson): void
    {
        $this->course($lesson->module->course, 'Yangi dars: :title', '/learn/courses/'.$lesson->module->course_id.'/lessons/'.$lesson->id, parameters: ['title' => $lesson->title]);
    }

    private function schedule(ClassSchedule $schedule, string $title): void
    {
        $ids = $schedule->group_id ? $schedule->group->members()->pluck('users.id')->all() : null;
        $this->course($schedule->course, $title, '/schedule', $ids);
    }

    /** @param list<int>|null $studentIds */
    private function course(Course $course, string $title, string $path, ?array $studentIds = null, array $parameters = []): void
    {
        if ($course->status !== 'published') {
            return;
        }
        $ids = $course->enrollments()->where('status', 'active')->pluck('student_id');
        foreach (User::query()->whereIn('id', $ids)->when($studentIds !== null, fn ($query) => $query->whereIn('id', $studentIds))->cursor() as $student) {
            $student->notify(new AcademyNotification($title, $path, $parameters));
        }
    }
}
