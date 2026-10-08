<?php

namespace App\Services\Storefront;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreCatalogService
{
    public function tenantId(?int $fallback = null): ?int
    {
        return $fallback;
    }

    /**
     * @return Collection<int, Product>
     */
    public function physicalProducts(?int $tenantId = null, ?callable $tap = null)
    {
        $q = Product::query()
            ->where('type', Product::TYPE_FISICO)
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        if ($tap) {
            $tap($q);
        }

        return $q->orderByDesc('created_at')->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function productCard(Product $product): array
    {
        $price = (float) $product->price;
        $pix = $product->pixPrice();
        $installments = max(1, (int) floor($price > 0 ? min(3, $price / 10) : 1));
        if ($price < 20) {
            $installments = 1;
        } elseif ($price < 50) {
            $installments = 2;
        } else {
            $installments = 3;
        }
        $per = $installments > 0 ? round($price / $installments, 2) : $price;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $price,
            'compare_at_price' => $product->compare_at_price !== null ? (float) $product->compare_at_price : null,
            'pix_price' => $pix,
            'pix_discount_percent' => $product->pix_discount_percent !== null ? (float) $product->pix_discount_percent : null,
            'image_url' => $product->galleryUrls()[0] ?? null,
            'gallery' => $product->galleryUrls(),
            'installment_text' => $installments > 1
                ? "{$installments}x de R$ ".number_format($per, 2, ',', '.').' sem juros'
                : null,
            'stock' => (int) $product->stock,
            'track_stock' => (bool) $product->track_stock,
            'in_stock' => $product->inStock(),
            'description' => $product->description,
            'technical_description' => $product->technical_description,
            'attention_notes' => $product->attention_notes,
            'sku' => $product->sku,
            'categories' => $product->relationLoaded('categories')
                ? $product->categories->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resolveShelfProducts(array $props, ?int $tenantId = null): array
    {
        $source = $props['source'] ?? 'newest';
        $limit = max(1, min(24, (int) ($props['limit'] ?? 8)));

        $query = Product::query()
            ->where('type', Product::TYPE_FISICO)
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        if ($source === 'manual' && ! empty($props['product_ids']) && is_array($props['product_ids'])) {
            $ids = array_values(array_filter($props['product_ids']));
            if ($ids === []) {
                return [];
            }
            $products = $query->whereIn('id', $ids)->get()->sortBy(fn ($p) => array_search($p->id, $ids, true));

            return $products->take($limit)->map(fn ($p) => $this->productCard($p))->values()->all();
        }

        if ($source === 'category' && ! empty($props['category_id'])) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', (int) $props['category_id']));
            $products = $query->orderByDesc('created_at')->limit($limit)->get();

            return $products->map(fn ($p) => $this->productCard($p))->values()->all();
        }

        if ($source === 'bestsellers') {
            $ids = OrderItem::query()
                ->select('product_id', DB::raw('COUNT(*) as qty'))
                ->whereNotNull('product_id')
                ->groupBy('product_id')
                ->orderByDesc('qty')
                ->limit($limit)
                ->pluck('product_id')
                ->all();
            if ($ids !== []) {
                $products = $query->whereIn('id', $ids)->get()->sortBy(fn ($p) => array_search($p->id, $ids, true));

                return $products->map(fn ($p) => $this->productCard($p))->values()->all();
            }
        }

        $products = $query->orderByDesc('created_at')->limit($limit)->get();

        return $products->map(fn ($p) => $this->productCard($p))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resolveCategoryCircles(array $props, ?int $tenantId = null): array
    {
        $mode = $props['mode'] ?? 'featured';
        $q = Category::query()
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        if ($mode === 'manual' && ! empty($props['category_ids'])) {
            $ids = array_map('intval', $props['category_ids']);
            $cats = $q->whereIn('id', $ids)->get()->sortBy(fn ($c) => array_search($c->id, $ids, true));
        } else {
            $cats = $q->where('is_featured_circle', true)->orderBy('position')->limit(18)->get();
        }

        return $cats->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'image_url' => $c->imageUrl(),
        ])->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    public function resolveSectionsData(array $sections, ?int $tenantId = null): array
    {
        $out = [];
        foreach ($sections as $section) {
            if (! ($section['enabled'] ?? true)) {
                continue;
            }
            $id = (string) ($section['id'] ?? '');
            $type = $section['type'] ?? '';
            $props = $section['props'] ?? [];
            if ($type === 'product_shelf') {
                $out[$id] = ['products' => $this->resolveShelfProducts($props, $tenantId)];
            } elseif ($type === 'category_circles') {
                $out[$id] = ['categories' => $this->resolveCategoryCircles($props, $tenantId)];
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeMenuTree(?int $tenantId = null): array
    {
        $tree = Category::menuTree($tenantId);

        $map = function ($nodes) use (&$map) {
            return $nodes->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'children' => $map($c->children ?? collect()),
            ])->values()->all();
        };

        return $map($tree);
    }
}
