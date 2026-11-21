<?php

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

test('login page renders', function () {
    $this->get('/login')->assertOk()->assertSee('Tizimga kirish');
});

test('user can login with valid credentials', function () {
    $user = User::factory()->create(['role' => UserRole::Student]);

    post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
});

test('user cannot login with invalid password', function () {
    $user = User::factory()->create(['role' => UserRole::Student]);

    post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');
});

test('dashboard requires auth', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('dashboard shows real stats for admin', function () {
    $admin = User::factory()->create(['role' => UserRole::AcademyAdmin]);
    User::factory()->count(3)->create(['role' => UserRole::Student]);

    actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertSee('3');
});

test('admin route rejects students', function () {
    $student = User::factory()->create(['role' => UserRole::Student]);

    actingAs($student)->get('/admin/courses')->assertForbidden();
});

test('admin route allows academy admin', function () {
    $admin = User::factory()->create(['role' => UserRole::AcademyAdmin]);

    actingAs($admin)->get('/admin/courses')->assertOk();
});
