<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMemory extends Model
{
    protected $fillable = [
        'user_id',
        'content',
        'embedding',
        'category',
        'content_hash',
        'case_id',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}
