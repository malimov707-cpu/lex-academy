<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AcademyBackup extends Command
{
    protected $signature = 'academy:backup';

    protected $description = 'Create a consistent SQLite database snapshot and copy private academy files';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('Bu buyruq SQLite uchun. Server bazasida provayderning backup vositasidan foydalaning.');

            return self::FAILURE;
        }
        $directory = storage_path('app/backups/'.now()->format('Y-m-d-His').'-'.Str::random(8));
        File::ensureDirectoryExists($directory, 0700, true);
        $path = $directory.'/database.sqlite';
        DB::connection()->getPdo()->exec('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
        chmod($path, 0600);
        $snapshot = new \PDO('sqlite:'.$path);
        if ($snapshot->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
            throw new \RuntimeException('Backup yaxlitligi tekshiruvi muvaffaqiyatsiz.');
        }
        $snapshot = null;
        $folders = ['submissions', 'lesson-materials', 'course-covers', 'library', 'courtroom-evidence'];
        foreach ($folders as $folder) {
            $source = storage_path('app/private/'.$folder);
            if (File::isDirectory($source)) {
                File::copyDirectory($source, $directory.'/'.$folder);
            }
        }
        File::put($directory.'/manifest.json', json_encode(['created_at' => now()->toIso8601String(), 'database' => 'sqlite', 'files' => $folders], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->info('Backup yaratildi: '.$directory);

        return self::SUCCESS;
    }
}
