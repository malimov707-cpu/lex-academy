<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('redirects the shared dashboard to the new student panel', function () {
    $student = User::factory()->student()->create();

    actingAs($student)->get(route('dashboard'))
        ->assertRedirectToRoute('student.dashboard');
});

it('renders every primary student area in the white and blue student shell', function () {
    $student = User::factory()->student()->create();
    actingAs($student);

    foreach ([
        'student.dashboard',
        'student.cases.index',
        'student.assignments',
        'library.index',
        'schedule.index',
        'certificates.index',
        'conversations.index',
        'notifications.index',
        'announcements.index',
        'search',
    ] as $routeName) {
        $this->get(route($routeName))
            ->assertOk()
            ->assertSee('id="student-navigation"', false)
            ->assertDontSee('id="academy-navigation"', false);
    }
});

it('keeps shared pages in the staff shell for administrators', function () {
    $admin = User::factory()->academyAdmin()->create();

    actingAs($admin)->get(route('library.index'))
        ->assertOk()
        ->assertSee('id="academy-navigation"', false)
        ->assertDontSee('id="student-navigation"', false);
});
