<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

it('restores a backup in isolation and verifies private file checksums', function () {
    $name = 'test-'.Str::uuid();
    $directory = storage_path('app/backups/'.$name);
    File::ensureDirectoryExists($directory.'/submissions', 0700, true);
    $connection = new PDO('sqlite:'.$directory.'/database.sqlite');
    $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY); CREATE TABLE migrations (id INTEGER PRIMARY KEY)');
    $connection = null;
    File::put($directory.'/submissions/example.txt', 'Private work for restore verification');

    try {
        $this->artisan('academy:restore-check', ['backup' => $name])->assertSuccessful();
        expect(File::get($directory.'/submissions/example.txt'))->toBe('Private work for restore verification');
        expect(File::exists($directory.'/database.sqlite'))->toBeTrue();
    } finally {
        File::deleteDirectory($directory);
    }
});

it('rejects backup path traversal and invalid database snapshots', function () {
    $this->artisan('academy:restore-check', ['backup' => '../../database'])->assertFailed();
    $name = 'test-invalid-'.Str::uuid();
    $directory = storage_path('app/backups/'.$name);
    File::ensureDirectoryExists($directory, 0700, true);
    File::put($directory.'/database.sqlite', 'This is not a database');

    try {
        $this->artisan('academy:restore-check', ['backup' => $name])->assertFailed();
    } finally {
        File::deleteDirectory($directory);
    }
});
