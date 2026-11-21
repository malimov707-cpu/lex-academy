<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SimulationAssetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(), 'name' => 'Test avatar', 'kind' => 'avatar',
            'path' => 'courtroom/test-avatar.glb', 'source_url' => 'https://example.test/assets/avatar',
            'license' => 'MIT', 'license_path' => 'courtroom/LICENSE.txt',
            'checksum' => hash('sha256', 'fixture'), 'bytes' => 100, 'triangle_count' => 100,
            'metadata' => ['variant' => 'male'], 'enabled' => false,
        ];
    }
}
