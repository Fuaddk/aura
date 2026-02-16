<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'source_url',
        'source_title',
        'content',
        'embedding',
        'category',
        'chunk_index',
        'token_count',
        'content_hash',
        'scraped_at',
    ];

    protected $casts = [
        'embedding' => 'array',
        'scraped_at' => 'datetime',
    ];
}
