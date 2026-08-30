<?php

declare(strict_types=1);

namespace App\Models\Aeon;

use Illuminate\Database\Eloquent\Model;

class Embedding extends Model
{
    protected $table = 'aeon_embeddings';

    protected $fillable = [
        'source_type',
        'source_ref',
        'title',
        'chunk_text',
        'vector',
        'dims',
        'checksum',
    ];

    protected $casts = [
        'vector' => 'array',
        'dims' => 'integer',
    ];
}
