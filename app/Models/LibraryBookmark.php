<?php

namespace App\Models;

use Database\Factories\LibraryBookmarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryBookmark extends Model
{
    /** @use HasFactory<LibraryBookmarkFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'library_resource_id', 'state', 'notes'];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(LibraryResource::class, 'library_resource_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
