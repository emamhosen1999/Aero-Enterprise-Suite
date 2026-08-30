<?php

declare(strict_types=1);

namespace App\Models\Aeon;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $table = 'aeon_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'context',
        'archived_at',
    ];

    protected $casts = [
        'context' => 'array',
        'archived_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id')->orderBy('id');
    }
}
