<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'email',
        'imap_host',
        'imap_port',
        'imap_password',
        'last_synced_at',
        'emails_found',
        'tasks_created',
        'is_active',
        'auto_sync',
    ];

    protected $casts = [
        'imap_password'  => 'encrypted',
        'last_synced_at' => 'datetime',
        'is_active'      => 'boolean',
        'auto_sync'      => 'boolean',
    ];

    protected $hidden = ['imap_password'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function emails()
    {
        return $this->hasMany(InboxEmail::class);
    }
}
