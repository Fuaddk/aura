<?php

namespace App\Models;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        $flush = fn (Task $task) => HandleInertiaRequests::flushTaskCache($task->user_id);

        static::created($flush);
        static::updated($flush);
        static::deleted($flush);
    }

    protected $fillable = [
        'case_id',
        'user_id',
        'title',
        'description',
        'task_type',
        'priority',
        'due_date',
        'estimated_duration_minutes',
        'status',
        'completed_at',
        'ai_generated',
        'ai_reasoning',
        'ai_confidence_score',
        'depends_on_task_id',
        'metadata',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'ai_generated' => 'boolean',
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

    public function dependsOn()
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }
}