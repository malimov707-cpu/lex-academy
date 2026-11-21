<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimulationAsset extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['slug', 'name', 'kind', 'path', 'source_url', 'license', 'license_path', 'checksum', 'bytes', 'triangle_count', 'metadata', 'enabled'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'metadata' => 'array', 'bytes' => 'integer', 'triangle_count' => 'integer'];
    }
}
