<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('shows the registration page', function () {
    get('/register')->assertOk()->assertSee('Ro‘yxatdan o‘tish', false);
});

it('registers a new student and logs them in', function () {
    $response = post('/register', [
        'name' => 'Yangi Talaba',
        'email' => 'yangi@huquq.ai',
        'phone' => '+998901112233',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ]);

    $response->assertRedirect(route('student.dashboard'));

    $user = User::query()->where('email', 'yangi@huquq.ai')->firstOrFail();

    expect($user->role)->toBe(UserRole::Student)
        ->and($user->name)->toBe('Yangi Talaba')
        ->and(auth()->id())->toBe($user->id);
});

it('always registers as student even if a role is submitted', function () {
    post('/register', [
        'name' => 'Bad Actor',
        'email' => 'bad@huquq.ai',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
        'role' => 'super_admin',
    ])->assertRedirect();

    expect(User::query()->where('email', 'bad@huquq.ai')->firstOrFail()->role)
        ->toBe(UserRole::Student);
});

it('rejects duplicate email', function () {
    User::factory()->create(['email' => 'bor@huquq.ai']);

    post('/register', [
        'name' => 'Dup',
        'email' => 'bor@huquq.ai',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'bor@huquq.ai')->count())->toBe(1);
});

it('rejects weak password and mismatched confirmation', function () {
    post('/register', [
        'name' => 'Weak',
        'email' => 'weak@huquq.ai',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    post('/register', [
        'name' => 'Mismatch',
        'email' => 'mismatch@huquq.ai',
        'password' => 'Secret123',
        'password_confirmation' => 'Different123',
    ])->assertSessionHasErrors('password');
});

it('redirects authenticated users away from register', function () {
    actingAs(User::factory()->student()->create())
        ->get('/register')
        ->assertRedirect();
});

it('hashes the password', function () {
    post('/register', [
        'name' => 'Hash Test',
        'email' => 'hash@huquq.ai',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ]);

    $user = User::query()->where('email', 'hash@huquq.ai')->firstOrFail();

    expect($user->password)->not->toBe('Secret123')
        ->and(Hash::check('Secret123', $user->password))->toBeTrue();
});
