<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

class AcademyRestoreCheck extends Command
{
    protected $signature = 'academy:restore-check {backup : Directory name within storage/app/backups}';

    protected $description = 'Restore a backup into an isolated temporary directory and verify database and file integrity';

    public function handle(): int
    {
        $name = (string) $this->argument('backup');
        if (! preg_match('/\A[A-Za-z0-9_-]+\z/', $name)) {
            $this->error('Invalid backup directory name.');

            return self::FAILURE;
        }
        $source = storage_path('app/backups/'.$name);
        if (! File::isDirectory($source) || is_link($source) || ! File::isFile($source.'/database.sqlite')) {
            $this->error('Backup not found.');

            return self::FAILURE;
        }
        $working = storage_path('app/restore-checks/'.Str::uuid());
        try {
            foreach (File::allFiles($source) as $file) {
                if ($file->isLink()) {
                    throw new RuntimeException('Backup contains a symbolic link.');
                }
            }
            File::ensureDirectoryExists($working, 0700, true);
            if (! File::copyDirectory($source, $working)) {
                throw new RuntimeException('Cannot copy backup.');
            }
            $connection = new PDO('sqlite:'.$working.'/database.sqlite');
            if ($connection->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
                throw new RuntimeException('Database integrity check failed.');
            }
            $connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $connection->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
            $connection = null;
            $verified = 0;
            foreach (File::allFiles($source) as $file) {
                $relative = $file->getRelativePathname();
                if (! hash_equals(hash_file('sha256', $file->getPathname()), hash_file('sha256', $working.'/'.$relative))) {
                    throw new RuntimeException('File checksum mismatch.');
                }
                $verified++;
            }
            $this->info('Restore check passed: '.$verified.' files verified. Live database was not changed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Restore check failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($working);
        }
    }
}
