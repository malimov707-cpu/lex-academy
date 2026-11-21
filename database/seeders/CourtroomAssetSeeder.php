<?php

namespace Database\Seeders;

use App\Models\SimulationAsset;
use Illuminate\Database\Seeder;
use RuntimeException;

class CourtroomAssetSeeder extends Seeder
{
    public function run(): void
    {
        $manifest = json_decode(file_get_contents(public_path('courtroom/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['assets'] as $asset) {
            $path = public_path($asset['path']);
            if (! is_file($path) || ! hash_equals($asset['checksum'], hash_file('sha256', $path))) {
                throw new RuntimeException('Courtroom asset checksum mismatch: '.$asset['slug']);
            }
            SimulationAsset::query()->firstOrCreate(['slug' => $asset['slug']], [...$asset, 'enabled' => true]);
        }
    }
}
