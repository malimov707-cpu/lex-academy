<?php

use App\Models\ClassSchedule;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\EducationGroup;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\MentorAssignment;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('continues with the next published lesson in teacher-defined module order', function () {
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    $course->enrollments()->create(['student_id' => $student->id]);
    $laterModule = CourseModule::factory()->create(['course_id' => $course->id, 'sort_order' => 2]);
    $firstModule = CourseModule::factory()->create(['course_id' => $course->id, 'sort_order' => 1]);
    $later = Lesson::query()->create(['module_id' => $laterModule->id, 'title' => 'Later module lesson', 'sort_order' => 0, 'status' => 'published']);
    $done = Lesson::query()->create(['module_id' => $firstModule->id, 'title' => 'Completed', 'sort_order' => 1, 'status' => 'published']);
    $next = Lesson::query()->create(['module_id' => $firstModule->id, 'title' => 'Continue here', 'sort_order' => 2, 'status' => 'published']);
    Lesson::query()->create(['module_id' => $firstModule->id, 'title' => 'Unpublished', 'sort_order' => 0, 'status' => 'draft']);
    LessonProgress::query()->create(['lesson_id' => $done->id, 'student_id' => $student->id, 'status' => 'completed']);

    actingAs($student)->get(route('student.dashboard'))->assertOk()
        ->assertViewHas('totalLessons', 3)->assertViewHas('completedCount', 1)
        ->assertViewHas('courseProgress', fn ($progress) => $progress[$course->id]['next_lesson']->id === $next->id)
        ->assertSee(route('student.lesson', [$course, $next]), false);
    actingAs($student)->get(route('student.lesson', [$course, $next]))->assertOk()
        ->assertViewHas('previousLesson', fn ($lesson) => $lesson->id === $done->id)
        ->assertViewHas('nextLesson', fn ($lesson) => $lesson->id === $later->id);
    actingAs($student)->get(route('student.course', $course))->assertOk()
        ->assertViewHas('lessons', fn ($lessons) => $lessons->pluck('id')->all() === [$done->id, $next->id, $later->id]);
    actingAs($student)->get(route('student.analytics', $course))->assertOk()
        ->assertViewHas('analytics', fn ($analytics) => $analytics['next_lesson']->id === $next->id);
});

it('shows only today sessions for the students active course and own groups', function () {
    $this->freezeTime();
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    $course->enrollments()->create(['student_id' => $student->id]);
    $ownGroup = EducationGroup::factory()->create(['course_id' => $course->id]);
    $ownGroup->members()->attach($student);
    $foreignGroup = EducationGroup::factory()->create(['course_id' => $course->id]);
    $general = ClassSchedule::factory()->create(['course_id' => $course->id, 'starts_at' => today()->addHours(9), 'ends_at' => today()->addHours(10)]);
    $own = ClassSchedule::factory()->create(['course_id' => $course->id, 'group_id' => $ownGroup->id, 'starts_at' => today()->addHours(11), 'ends_at' => today()->addHours(12)]);
    ClassSchedule::factory()->create(['course_id' => $course->id, 'group_id' => $foreignGroup->id, 'starts_at' => today()->addHours(13), 'ends_at' => today()->addHours(14)]);
    ClassSchedule::factory()->create(['course_id' => $course->id, 'starts_at' => today()->addDay(), 'ends_at' => today()->addDay()->addHour()]);
    ClassSchedule::factory()->create(['starts_at' => today()->addHours(15), 'ends_at' => today()->addHours(16)]);

    actingAs($student)->get(route('student.dashboard'))->assertOk()
        ->assertViewHas('todaySessions', fn ($sessions) => $sessions->pluck('id')->all() === [$general->id, $own->id]);
});

it('isolates upcoming exams and recent results and marks pending manual grades', function () {
    $this->freezeTime();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $course->enrollments()->create(['student_id' => $student->id]);
    $exam = $course->quizzes()->create(['title' => 'Upcoming exam', 'kind' => 'exam', 'available_from' => now()->addDay(), 'available_until' => now()->addDays(2)]);
    $course->quizzes()->create(['title' => 'Closed exam', 'kind' => 'exam', 'available_until' => now()->subDay()]);
    $quiz = $course->quizzes()->create(['title' => 'Pending review quiz', 'kind' => 'quiz']);
    $question = $course->questions()->create(['type' => 'long_answer', 'text' => 'Analysis', 'points' => 2]);
    $quiz->questions()->attach($question);
    $attempt = $quiz->attempts()->create(['student_id' => $student->id, 'status' => 'submitted', 'score' => 50]);
    $attempt->answers()->create(['question_id' => $question->id, 'text_answer' => 'My analysis']);
    $quiz->attempts()->create(['student_id' => User::factory()->student()->create()->id, 'status' => 'graded', 'score' => 99]);
    $quiz->attempts()->create(['student_id' => $student->id, 'status' => 'in_progress']);
    $privateCourse = Course::factory()->published()->create();
    $privateExam = $privateCourse->quizzes()->create(['title' => 'Private exam', 'kind' => 'exam']);
    $privateExam->attempts()->create(['student_id' => $student->id, 'status' => 'graded', 'score' => 90]);
    $draft = Course::factory()->create(['status' => 'draft']);
    $draft->enrollments()->create(['student_id' => $student->id]);
    $draft->quizzes()->create(['title' => 'Draft course exam', 'kind' => 'exam']);

    actingAs($student)->get(route('student.dashboard'))->assertOk()
        ->assertViewHas('upcomingExams', fn ($exams) => $exams->pluck('id')->all() === [$exam->id])
        ->assertViewHas('recentResults', fn ($results) => $results->pluck('id')->all() === [$attempt->id] && $results->first()->pending_answers_count === 1)
        ->assertDontSee('Private exam')->assertDontSee('Draft course exam')->assertDontSee('Closed exam');
});

it('does not promise a certificate solely because published lessons are completed', function () {
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    $course->enrollments()->create(['student_id' => $student->id]);
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Completed lesson', 'status' => 'published']);
    LessonProgress::query()->create(['lesson_id' => $lesson->id, 'student_id' => $student->id, 'status' => 'completed']);
    $course->assignments()->create(['teacher_id' => $course->teacher_id, 'title' => 'Still required']);

    actingAs($student)->get(route('student.dashboard'))->assertOk()
        ->assertDontSee(__('Kurs yakunlandi — sertifikatni olishingiz mumkin.'));
});

it('shows only mentors assigned to the student within active published courses', function () {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $course->enrollments()->create(['student_id' => $student->id]);
    $mentor = User::factory()->mentor()->create(['name' => 'My assigned mentor']);
    $mine = MentorAssignment::query()->create(['student_id' => $student->id, 'mentor_id' => $mentor->id, 'course_id' => $course->id]);
    $other = User::factory()->mentor()->create(['name' => 'Foreign student mentor']);
    MentorAssignment::query()->create(['student_id' => User::factory()->student()->create()->id, 'mentor_id' => $other->id, 'course_id' => $course->id]);
    $inactive = Course::factory()->published()->create();
    $inactive->enrollments()->create(['student_id' => $student->id, 'status' => 'suspended']);
    MentorAssignment::query()->create(['student_id' => $student->id, 'mentor_id' => $other->id, 'course_id' => $inactive->id]);

    actingAs($student)->get(route('student.dashboard'))->assertOk()->assertSee('My assigned mentor')->assertDontSee('Foreign student mentor')
        ->assertSee(route('conversations.show', $mine), false)
        ->assertViewHas('mentorAssignments', fn ($assignments) => $assignments->pluck('id')->all() === [$mine->id]);
});

it('charts the last seven days from the students real published lesson completions', function () {
    $this->freezeTime();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $course->enrollments()->create(['student_id' => $student->id]);
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    foreach ([0, 6, 8] as $daysAgo) {
        $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => "Published {$daysAgo}", 'status' => 'published']);
        LessonProgress::query()->create(['lesson_id' => $lesson->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => today()->subDays($daysAgo)->addHour()]);
    }
    $draft = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Draft', 'status' => 'draft']);
    LessonProgress::query()->create(['lesson_id' => $draft->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => today()->addHour()]);
    LessonProgress::query()->create(['lesson_id' => $lesson->id, 'student_id' => User::factory()->student()->create()->id, 'status' => 'completed', 'completed_at' => today()->addHour()]);

    actingAs($student)->get(route('student.dashboard'))->assertOk()->assertViewHas('completedCount', 3)
        ->assertViewHas('weeklyActivity', fn ($activity) => $activity->pluck('count')->all() === [1, 0, 0, 0, 0, 0, 1]);
});
