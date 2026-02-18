<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuraNotification extends Model
{
    protected $table = 'aura_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'is_read',
        'action_url',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
