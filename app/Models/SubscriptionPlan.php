<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'price', 'tokens_limit',
        'features', 'feature_flags', 'stripe_price_id', 'color', 'is_popular', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'features'      => 'array',
        'feature_flags' => 'array',
        'is_popular'    => 'boolean',
        'is_active'     => 'boolean',
    ];
}
