<?php

namespace Database\Seeders;

use App\Models\LibraryResource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LegalLibrarySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (File::json(database_path('seeders/legal-library.json')) as $resource) {
                LibraryResource::query()->firstOrCreate(['catalog_key' => $resource['catalog_key']], $resource);
            }
        });
    }
}
