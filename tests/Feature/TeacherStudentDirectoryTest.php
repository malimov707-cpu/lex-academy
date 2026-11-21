<?php

use App\Models\Course;
use App\Models\SimulationScenario;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('links teachers from search to their enrolled students and their own courtroom scenarios', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create(['name' => 'Panel Student']);
    $foreign = User::factory()->student()->create(['name' => 'Panel Foreign']);
    $assigned = Course::factory()->published()->create();
    $assigned->teacherAssignments()->create(['teacher_id' => $teacher->id]);
    $assigned->enrollments()->create(['student_id' => $student->id]);
    $privateCourse = Course::factory()->published()->create();
    foreach ([$student, $foreign] as $learner) {
        $privateCourse->enrollments()->create(['student_id' => $learner->id]);
    }
    $ownCase = SimulationScenario::factory()->courtroomReady()->create(['title' => 'Panel Hearing', 'created_by' => $teacher->id]);
    $privateCase = SimulationScenario::factory()->courtroomReady()->create(['title' => 'Panel Hidden Case']);

    actingAs($teacher)->get(route('search', ['q' => 'Panel']))->assertOk()
        ->assertSee(route('teacher.students.index', ['student' => $student->id]))
        ->assertSee(route('admin.courtroom.preview', $ownCase))
        ->assertDontSeeText($foreign->name)->assertDontSeeText($privateCase->title);
    $this->get(route('teacher.students.index', ['student' => $student->id]))->assertOk()
        ->assertSeeText($student->name)->assertSeeText($assigned->title)->assertDontSeeText($privateCourse->title);
    $this->get(route('teacher.students.index', ['student' => $foreign->id]))->assertOk()->assertDontSeeText($foreign->name);
    actingAs($student)->get(route('teacher.students.index'))->assertForbidden();
});
