<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseModel extends Model
{
    use HasFactory;

    protected $table = 'cases';

    protected $fillable = [
        'user_id',
        'case_type',
        'status',
        'title',
        'situation_summary',
        'situation_structured',
        'separation_date',
        'filing_date',
        'expected_resolution_date',
        'has_children',
        'has_shared_property',
        'has_shared_debt',
        'complexity_score',
        'ai_context',
    ];

    protected $casts = [
        'situation_structured' => 'array',
        'ai_context' => 'array',
        'separation_date' => 'date',
        'filing_date' => 'date',
        'expected_resolution_date' => 'date',
        'has_children' => 'boolean',
        'has_shared_property' => 'boolean',
        'has_shared_debt' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'case_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'case_id');
    }
}