<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, Billable;

    protected $fillable = [
        'name',
        'display_name',
        'work_description',
        'preferences',
        'email',
        'is_admin',
        'password',
        'google_id',
        'organization_id',
        'phone',
        'consent_data_processing',
        'consent_ai_analysis',
        'subscription_plan',
        'wallet_balance',
        'ai_messages_used',
        'ai_messages_limit',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'consent_data_processing' => 'boolean',
            'consent_ai_analysis' => 'boolean',
            'wallet_balance' => 'decimal:2',
            'ai_messages_used' => 'integer',
            'ai_messages_limit' => 'integer',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_confirmed_at);
    }

    // ADD THESE METHODS:
    
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseModel::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}