<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    protected $fillable = [
        'case_id',
        'user_id',
        'role',
        'content',
        'retrieved_chunks',
        'metadata',
        'model_used',
        'tokens_used',
        'response_time_ms',
    ];

    protected $casts = [
        'retrieved_chunks' => 'array',
        'metadata' => 'array',
    ];

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}