<?php

namespace App\Models;

use Database\Factories\LibraryResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryResource extends Model
{
    /** @use HasFactory<LibraryResourceFactory> */
    use HasFactory;

    protected $fillable = ['catalog_key', 'title', 'type', 'topic', 'description', 'body', 'url', 'publisher', 'language', 'year', 'access', 'status', 'checked_at', 'created_by', 'local_path', 'local_format', 'content_sha256', 'content_bytes', 'imported_at', 'import_source_url', 'license', 'license_url', 'attribution', 'contents'];

    protected function casts(): array
    {
        return ['checked_at' => 'date', 'imported_at' => 'datetime', 'contents' => 'array'];
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(LibraryBookmark::class);
    }

    /** @return array<string, string> */
    public static function types(): array
    {
        return ['constitution' => 'Konstitutsiya', 'code' => 'Kodeks', 'law' => 'Qonun', 'textbook' => 'Darslik va kitob', 'guide' => 'O‘quv qo‘llanma', 'portal' => 'Izlanish manbasi'];
    }
}
