<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'is_secret'];

    protected $casts = [
        'is_secret' => 'boolean',
    ];

    public static function getValue(string $key): ?string
    {
        return static::where('key', $key)->value('value');
    }

    public static function set(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
