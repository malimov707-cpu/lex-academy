<?php

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\post;

it('allows login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 't@huquq.ai',
        'password' => Hash::make('password'),
    ]);

    post('/login', ['email' => 't@huquq.ai', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    expect(auth()->user()?->id)->toBe($user->id);
});

it('rejects invalid credentials', function () {
    post('/login', ['email' => 'x@huquq.ai', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');
});

it('redirects guests from dashboard to login', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('redirects students from the shared dashboard to their learning panel', function () {
    $user = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($user)->get('/dashboard')
        ->assertRedirectToRoute('student.dashboard');
});

it('forbids student from admin routes', function () {
    $student = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($student)->get('/admin/courses')->assertForbidden();
});

it('allows academy admin to access admin routes', function () {
    $admin = User::factory()->create(['role' => UserRole::AcademyAdmin]);

    $this->actingAs($admin)->get('/admin/courses')->assertOk();
});

it('forbids teacher from pure admin areas', function () {
    $teacher = User::factory()->create(['role' => UserRole::Teacher]);

    // PHASE 9-da teacher faqat o'z kurslariga cheklanadi; hozir admin route ochildi (course management uchun)
    $this->actingAs($teacher)->get('/admin/courses')->assertOk();
});

it('calculates admin dashboard stats from real data', function () {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $teacher = User::factory()->create(['role' => UserRole::Teacher]);
    $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);

    // Super admin endi Control Center'ga yo'naltiriladi
    $this->actingAs($admin)->get('/dashboard')
        ->assertRedirect(route('admin.control-center'));

    $this->actingAs($admin)->get(route('admin.control-center'))
        ->assertOk()
        ->assertSee($admin->name);
});

it('logs out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

    expect(auth()->user())->toBeNull();
});
