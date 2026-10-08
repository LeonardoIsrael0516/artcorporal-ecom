<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StorePage extends Model
{
    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'body',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StorePage $page): void {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title) ?: 'pagina';
            }
        });
    }
}
