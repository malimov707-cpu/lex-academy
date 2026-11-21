<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAcademyAdmin extends Command
{
    protected $signature = 'academy:create-admin {email} {--name= : Administrator display name} {--super : Create a platform super administrator}';

    protected $description = 'Create an administrator using the configured bootstrap password without exposing it in arguments or output';

    public function handle(): int
    {
        $data = ['email' => $this->argument('email'), 'name' => $this->option('name'), 'password' => config('academy.bootstrap_password')];
        $validator = Validator::make($data, ['email' => ['required', 'email', 'max:255', 'unique:users,email'], 'name' => ['required', 'string', 'max:255'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            $this->error('Administrator was not created. Provide a unique email, --name and a strong ACADEMY_BOOTSTRAP_PASSWORD (12+ characters, mixed case and digits).');

            return self::FAILURE;
        }
        User::query()->create($data + ['role' => $this->option('super') ? UserRole::SuperAdmin : UserRole::AcademyAdmin]);
        $this->info('Administrator created. Remove ACADEMY_BOOTSTRAP_PASSWORD from the environment and rebuild the configuration cache.');

        return self::SUCCESS;
    }
}
