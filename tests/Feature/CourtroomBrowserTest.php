<?php

use App\Models\LegalSource;
use App\Models\SimulationScenario;
use App\Models\User;
use Database\Seeders\SimulationScenarioSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

it('opens the courtroom and teacher builder in an isolated real browser', function () {
    $module = getenv('COURTROOM_PLAYWRIGHT_MODULE');
    $directory = sys_get_temp_dir().'/courtroom-browser-'.Str::uuid();
    File::ensureDirectoryExists($directory, 0700);
    $database = $directory.'/database.sqlite';
    touch($database);
    $original = config('database.default');
    config()->set('database.connections.courtroom_browser', [...config('database.connections.sqlite'), 'database' => $database]);
    $server = null;
    try {
        Artisan::call('migrate', ['--database' => 'courtroom_browser', '--force' => true, '--no-interaction' => true]);
        DB::setDefaultConnection('courtroom_browser');
        Artisan::call('db:seed', ['--class' => 'CourtroomAssetSeeder', '--database' => 'courtroom_browser', '--no-interaction' => true]);
        $teacher = User::factory()->teacher()->create(['email' => 'teacher.browser@example.test', 'password' => Hash::make('browser-test-password')]);
        User::factory()->student()->create(['email' => 'student.browser@example.test', 'password' => Hash::make('browser-test-password')]);
        $scenario = SimulationScenario::factory()->courtroomReady()->create(['created_by' => $teacher->id]);
        $version = $scenario->versions()->sole();
        $version->evidence()->create([
            'title' => 'Ikkinchi doska dalili', 'type' => 'protocol', 'summary' => 'Doskada almashtirish sinovi.',
            'body' => 'Ikkinchi hujjatning to‘liq matni.', 'source_label' => 'Brauzer o‘quv materiali',
            'visibility' => 'all', 'status' => 'active', 'sort_order' => 2,
        ]);
        $version->evidence()->create([
            'title' => 'Guvohning maxfiy dalili', 'type' => 'protocol', 'summary' => 'Sudya ko‘rmasligi kerak.',
            'body' => 'Guvohgagina ochiq matn.', 'source_label' => 'Maxfiy o‘quv materiali',
            'visibility' => 'roles', 'allowed_role_keys' => ['witness'], 'status' => 'active', 'sort_order' => 3,
        ]);
        $version->stages()->where('key', 'PREPARATION')->sole()->actionRules()->create([
            'role_key' => '*', 'action_type' => 'ask_question', 'label' => 'Savol berish', 'requires_content' => true,
            'response_role_keys' => ['witness'],
        ]);
        $reference = $version->legalReferences()->create([
            'title' => 'Brauzer sinovidagi huquqiy manba', 'source_url' => 'https://example.test/courtroom-reference',
            'citation_text' => 'Sinovdagi norma va tahrir izohi.', 'verified_by' => $teacher->id, 'verified_at' => now(),
        ]);
        $this->seed(SimulationScenarioSeeder::class);
        $administrator = User::factory()->academyAdmin()->create();
        LegalSource::factory()->create([
            'title' => 'Brauzer kutubxonasidagi norma', 'url' => 'https://lex.uz/docs/example-browser',
            'status' => 'verified', 'verified_at' => now(), 'verified_by' => $administrator->id,
        ]);
        $port = random_int(18000, 23000);
        $server = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$port, '--no-reload', '--no-interaction'], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
            'DB_URL' => '', 'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'array',
            'AI_TUTOR_API_KEY' => '', 'AI_VOICE_API_KEY' => '', 'VIRTUAL_COURTROOM_SERVER_SPEECH_ENABLED' => 'true',
            'VIRTUAL_COURTROOM_VOICE_MODE' => 'realtime',
        ]);
        $server->start();
        $ready = false;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $port, $errorNumber, $errorMessage, .1);
            if ($socket) {
                fclose($socket);
                $ready = true;
                break;
            }
            usleep(100000);
        }
        expect($ready)->toBeTrue();
        $browser = new Process(['node', base_path('tests/Feature/courtroom-browser.mjs')], base_path(), [
            'COURTROOM_PLAYWRIGHT_MODULE' => $module, 'COURTROOM_BROWSER_URL' => 'http://127.0.0.1:'.$port,
            'COURTROOM_SCENARIO' => $scenario->getRouteKey(), 'COURTROOM_BROWSER_ARTIFACTS' => $directory,
            'COURTROOM_REFERENCE_ID' => (string) $reference->id,
        ]);
        $browser->setTimeout(120);
        $browser->run();
        expect($browser->getExitCode())->toBe(0, $browser->getOutput().$browser->getErrorOutput());
        expect(File::exists($directory.'/courtroom-desktop.png'))->toBeTrue();
        $this->assertTrue(true, 'Browser screenshots: '.$directory);
    } finally {
        $server?->stop();
        DB::purge('courtroom_browser');
        DB::setDefaultConnection($original);
        File::delete($database, $database.'-wal', $database.'-shm');
    }
})->skip(! getenv('COURTROOM_PLAYWRIGHT_MODULE'), 'Set COURTROOM_PLAYWRIGHT_MODULE to an installed Playwright index.mjs to run browser QA.');
