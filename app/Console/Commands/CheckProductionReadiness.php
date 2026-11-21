<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class CheckProductionReadiness extends Command
{
    protected $signature = 'academy:readiness {--json : Return machine-readable checks without configuration values}';

    protected $description = 'Check local release prerequisites without changing environment settings or calling external services';

    public function handle(Migrator $migrator): int
    {
        $url = parse_url((string) config('app.url'));
        $checks = [
            'production_environment' => app()->isProduction(),
            'debug_disabled' => config('app.debug') === false,
            'https_url' => ($url['scheme'] ?? null) === 'https' && ! in_array($url['host'] ?? '', ['', 'localhost', '127.0.0.1', '::1'], true),
            'application_key_present' => filled(config('app.key')),
            'secure_session_cookie' => config('session.secure') === true,
            'http_only_session_cookie' => config('session.http_only') === true,
            'same_site_session_cookie' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'persistent_sessions' => ! in_array(config('session.driver'), ['array', 'cookie', null], true),
            'storage_writable' => is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')),
            'frontend_build' => $this->hasFrontendBuild(),
        ];
        try {
            DB::connection()->getPdo();
            $checks['database_connection'] = true;
            $files = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));
            $checks['migrations_current'] = array_diff(array_keys($files), $migrator->getRepository()->getRan()) === [];
            $demoPassword = false;
            $adminExists = false;
            foreach (User::query()->select('id', 'role', 'password')->cursor() as $user) {
                $adminExists = $adminExists || $user->isAdmin();
                if (Hash::check('password', $user->password)) {
                    $demoPassword = true;
                }
            }
            $checks['administrator_exists'] = $adminExists;
            $checks['demo_passwords_removed'] = ! $demoPassword;
        } catch (Throwable) {
            $checks['database_connection'] = false;
            $checks['migrations_current'] = false;
            $checks['administrator_exists'] = false;
            $checks['demo_passwords_removed'] = false;
        }
        $failed = array_keys(array_filter($checks, fn (bool $passed): bool => ! $passed));
        if ($this->option('json')) {
            $this->line(json_encode(['ready' => $failed === [], 'checks' => $checks, 'failed' => $failed,
                'scope' => 'Local prerequisites only; HTTPS reachability, scheduler, offsite backup, load and live AI require separate verification.'], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Result'], array_map(fn (string $name, bool $passed): array => [$name, $passed ? 'PASS' : 'FAIL'], array_keys($checks), array_values($checks)));
            $this->line('Local prerequisites only. Verify live infrastructure and AI separately.');
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    private function hasFrontendBuild(): bool
    {
        $path = public_path('build/manifest.json');
        if (! is_file($path)) {
            return false;
        }
        $manifest = json_decode((string) file_get_contents($path), true);
        $entries = ['resources/js/app.js', 'resources/css/app.css'];
        if (config('virtual_courtroom.enabled')) {
            $entries[] = 'resources/js/virtual-courtroom.js';
        }
        foreach ($entries as $entry) {
            $file = $manifest[$entry]['file'] ?? null;
            if (! is_string($file) || str_contains($file, '..') || ! is_file(public_path('build/'.$file))) {
                return false;
            }
        }

        return true;
    }
}
