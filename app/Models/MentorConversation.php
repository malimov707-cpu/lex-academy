<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorConversation extends Model
{
    protected $fillable = ['mentor_assignment_id'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MentorAssignment::class, 'mentor_assignment_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_id');
    }
}
