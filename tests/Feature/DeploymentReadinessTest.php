<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('rejects insecure release settings without printing secret configuration values', function () {
    config()->set('app.debug', true);
    config()->set('app.url', 'http://localhost');
    config()->set('app.key', 'sensitive-value-not-to-print');
    config()->set('session.secure', false);

    $code = Artisan::call('academy:readiness', ['--json' => true]);
    expect($code)->toBe(1);
    $output = Artisan::output();
    $data = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    expect($data['ready'])->toBeFalse()
        ->and($data['failed'])->toContain('debug_disabled', 'https_url', 'secure_session_cookie')
        ->and($output)->not->toContain('sensitive-value-not-to-print');
});

it('detects demo passwords and requires a real administrator', function () {
    User::factory()->academyAdmin()->create(['password' => 'password']);

    $code = Artisan::call('academy:readiness', ['--json' => true]);
    expect($code)->toBe(1);

    $data = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($data['checks']['administrator_exists'])->toBeTrue()
        ->and($data['checks']['demo_passwords_removed'])->toBeFalse()
        ->and($data['checks']['migrations_current'])->toBeTrue();
});

it('bootstraps an administrator without exposing or accepting a default password', function () {
    config()->set('academy.bootstrap_password', 'password');
    $this->artisan('academy:create-admin', ['email' => 'bootstrap@example.test', '--name' => 'Academy Admin'])->assertFailed();
    expect(User::query()->where('email', 'bootstrap@example.test')->exists())->toBeFalse();
    config()->set('academy.bootstrap_password', 'BootstrapStrong123');

    $code = Artisan::call('academy:create-admin', ['email' => 'bootstrap@example.test', '--name' => 'Academy Admin', '--super' => true]);

    expect($code)->toBe(0);
    $user = User::query()->where('email', 'bootstrap@example.test')->firstOrFail();
    expect($user->role->value)->toBe('super_admin');
    expect(Hash::check('BootstrapStrong123', $user->password))->toBeTrue();
    expect(Artisan::output())->not->toContain('BootstrapStrong123');
    $this->artisan('academy:create-admin', ['email' => 'bootstrap@example.test', '--name' => 'Duplicate'])->assertFailed();
    expect(User::query()->where('email', 'bootstrap@example.test')->count())->toBe(1);
});
