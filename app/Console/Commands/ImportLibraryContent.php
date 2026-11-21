<?php

namespace App\Console\Commands;

use App\Models\LibraryResource;
use App\Services\LibraryContentImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportLibraryContent extends Command
{
    protected $signature = 'library:import-content {--key=* : Specific catalogue keys} {--books-only} {--laws-only} {--refresh : Explicitly replace an existing snapshot}';

    protected $description = 'Import official legal texts and explicitly licensed open textbooks for local reading';

    public function handle(LibraryContentImporter $importer): int
    {
        if ($this->option('books-only') && $this->option('laws-only')) {
            $this->error('Choose books-only or laws-only.');

            return self::FAILURE;
        }
        $keys = $this->option('key');
        $failed = 0;
        $selected = 0;
        if (! $this->option('books-only')) {
            foreach (LibraryResource::query()->where('status', 'published')->where('access', 'open')->whereIn('type', ['constitution', 'code', 'law'])
                ->when($keys !== [], fn ($query) => $query->whereIn('catalog_key', $keys))->lazyById(1) as $resource) {
                $selected++;
                if ($resource->local_path && Storage::disk('local')->exists($resource->local_path) && ! $this->option('refresh')) {
                    $this->line('Already local: '.$resource->catalog_key);

                    continue;
                }
                try {
                    $importer->importLaw($resource);
                    $this->info('Imported: '.$resource->catalog_key);
                } catch (Throwable $exception) {
                    $failed++;
                    $this->error('Import failed: '.$resource->catalog_key.' ('.class_basename($exception).'). Existing copy preserved.');
                }
            }
        }
        if (! $this->option('laws-only')) {
            foreach (array_keys(config('library_content.books', [])) as $key) {
                if ($keys !== [] && ! in_array($key, $keys, true)) {
                    continue;
                }
                $selected++;
                $resource = LibraryResource::query()->where('catalog_key', $key)->first();
                if ($resource?->local_path && Storage::disk('local')->exists($resource->local_path) && ! $this->option('refresh')) {
                    $this->line('Already local: '.$key);

                    continue;
                }
                if ($resource && $resource->status !== 'published') {
                    $this->warn('Skipped unpublished book: '.$key);

                    continue;
                }
                try {
                    $importer->importBook($key);
                    $this->info('Imported: '.$key);
                } catch (Throwable $exception) {
                    $failed++;
                    $this->error('Import failed: '.$key.' ('.class_basename($exception).'). Existing copy preserved.');
                }
            }
        }
        $this->line('Selected: '.$selected.'; failures: '.$failed);

        return $failed === 0 && $selected > 0 ? self::SUCCESS : self::FAILURE;
    }
}
