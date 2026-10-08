<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'image',
        'position',
        'is_active',
        'is_featured_circle',
        'show_in_menu',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured_circle' => 'boolean',
            'show_in_menu' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (empty($category->slug)) {
                $category->slug = static::uniqueSlug($category->name, $category->tenant_id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $tenantId = null): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $i = 1;
        while (static::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }
        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return asset('storage/'.$this->image);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Category>
     */
    public static function menuTree(?int $tenantId = null)
    {
        $all = static::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->where('show_in_menu', true)
            ->orderBy('position')
            ->get();

        return static::buildTree($all);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Category>  $all
     * @return \Illuminate\Support\Collection<int, Category>
     */
    public static function buildTree($all, ?int $parentId = null)
    {
        return $all
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (Category $cat) use ($all) {
                $cat->setRelation('children', static::buildTree($all, $cat->id));

                return $cat;
            });
    }
}
