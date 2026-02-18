<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxEmail extends Model
{
    protected $fillable = [
        'email_account_id',
        'user_id',
        'message_uid',
        'subject',
        'from_email',
        'from_name',
        'received_at',
        'snippet',
        'is_relevant',
        'analysis_result',
        'tasks_created',
    ];

    protected $casts = [
        'received_at'     => 'datetime',
        'is_relevant'     => 'boolean',
        'analysis_result' => 'array',
    ];

    public function emailAccount()
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
