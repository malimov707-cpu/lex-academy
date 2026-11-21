<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('defaults to light theme', function () {
    $this->get('/login')->assertOk()->assertSee('data-theme="light"', false);
});

it('defaults the super admin control center to dark while respecting an explicit light preference', function () {
    $admin = User::factory()->superAdmin()->create();

    actingAs($admin)->get(route('admin.control-center'))->assertOk()
        ->assertSee('data-theme="dark"', false)->assertSee('cc-admin-frame', false);
    $this->withCookie('academy_theme', 'light')->get(route('admin.control-center'))->assertOk()
        ->assertSee('data-theme="light"', false);
});

it('sets and persists the dark theme cookie', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post('/theme', ['theme' => 'dark'])
        ->assertRedirect()
        ->assertCookie('academy_theme', 'dark');
});

it('toggles back to light', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post('/theme', ['theme' => 'light'])
        ->assertCookie('academy_theme', 'light');
});

it('rejects invalid theme values', function () {
    $user = User::factory()->create();

    actingAs($user)->post('/theme', ['theme' => 'neon'])->assertSessionHasErrors('theme');
});

it('exposes both theme variants in the shared layout', function () {
    $user = User::factory()->academyAdmin()->create();

    actingAs($user)
        ->get('/notifications')
        ->assertOk()
        ->assertSee('data-theme="', false)
        ->assertSee('action="'.route('theme.update').'"', false)
        ->assertSee('edu-skip-link', false)
        ->assertSee('data-navigation-panel', false)
        ->assertSee('edu-drawer-scrim', false);
});

it('exposes theme controls in the student layout', function () {
    $student = User::factory()->student()->create();

    actingAs($student)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('data-theme="', false)
        ->assertSee('action="'.route('theme.update').'"', false)
        ->assertSee('edu-skip-link', false)
        ->assertSee('data-student-navigation', false)
        ->assertSee('data-student-menu-close', false);
});
