<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'user_id',
        'task_id',
        'filename',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'storage_path',
        'document_type',
        'document_category',
        'processing_status',
        'extracted_text',
        'ai_summary',
        'ai_key_points',
        'ai_entities',
        'encrypted',
        'encryption_key_id',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'ai_key_points' => 'array',
        'ai_entities' => 'array',
        'encrypted' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}