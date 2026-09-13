<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'price_monthly',
        'max_orders_per_month',
        'max_products',
        'max_users',
        'features',
        'is_active',
    ];

    protected $casts = [
        'price_monthly' => 'float',
        'max_orders_per_month' => 'integer',
        'max_products' => 'integer',
        'max_users' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
