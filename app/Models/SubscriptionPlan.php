<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'price', 'tokens_limit',
        'features', 'stripe_price_id', 'color', 'is_popular', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'features'   => 'array',
        'is_popular' => 'boolean',
        'is_active'  => 'boolean',
    ];
}
