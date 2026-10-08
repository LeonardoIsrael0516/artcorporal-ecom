<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceCart extends Model
{
    protected $fillable = [
        'tenant_id',
        'session_token',
        'customer_email',
        'metadata',
        'shipping_quote',
        'shipping_address',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'shipping_quote' => 'array',
            'shipping_address' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CommerceCartLine::class)->orderBy('position');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
