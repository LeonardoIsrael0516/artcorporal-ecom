<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StorePage;
use App\Models\User;
use App\Services\Commerce\CommerceCartService;
use App\Services\Storefront\StoreCatalogService;
use App\Services\Storefront\StoreThemeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(
        protected StoreThemeService $themeService,
        protected StoreCatalogService $catalog,
        protected CommerceCartService $cartService,
    ) {}

    protected function storeTenantId(): ?int
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();

        return $admin?->tenant_id ?? $admin?->id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedStoreProps(Request $request): array
    {
        $tenantId = $this->storeTenantId();
        $theme = $this->themeService->getTheme($tenantId);
        $cartCount = 0;
        try {
            if ($tenantId) {
                $cart = $this->cartService->getOrCreate($request, (int) $tenantId);
                $cartCount = (int) $cart->lines->sum('quantity');
            }
        } catch (\Throwable) {
            $cartCount = 0;
        }

        return [
            'storeTheme' => $theme,
            'menuCategories' => $this->catalog->serializeMenuTree($tenantId),
            'hasWholesale' => $this->catalog->hasWholesaleProducts($tenantId),
            'cartCount' => $cartCount,
            'isPreview' => $request->boolean('preview'),
        ];
    }

    public function home(Request $request): Response
    {
        $tenantId = $this->storeTenantId();
        $theme = $this->themeService->getTheme($tenantId);
        $sections = $theme['sections'] ?? [];

        return Inertia::render('Storefront/Home', array_merge($this->sharedStoreProps($request), [
            'sections' => $sections,
            'sectionData' => $this->catalog->resolveSectionsData($sections, $tenantId),
        ]));
    }

    public function shop(Request $request): Response
    {
        $tenantId = $this->storeTenantId();
        $query = Product::query()
            ->where('type', Product::TYPE_FISICO)
            ->where('is_active', true)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        $this->catalog->applyRetailVisibility($query);

        $products = $query
            ->orderByDesc('created_at')
            ->paginate(24)
            ->through(fn (Product $p) => $this->catalog->productCard($p));

        return Inertia::render('Storefront/Category', array_merge($this->sharedStoreProps($request), [
            'category' => ['name' => 'Loja', 'slug' => 'loja', 'description' => null, 'is_wholesale' => false],
            'products' => $products,
            'breadcrumbs' => [['label' => 'Início', 'href' => '/'], ['label' => 'Loja', 'href' => '/loja']],
        ]));
    }

    public function wholesale(Request $request): Response
    {
        $tenantId = $this->storeTenantId();
        $products = Product::query()
            ->where('type', Product::TYPE_FISICO)
            ->where('is_active', true)
            ->where('wholesale_enabled', true)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('created_at')
            ->paginate(24)
            ->through(fn (Product $p) => $this->catalog->productCard($p));

        return Inertia::render('Storefront/Category', array_merge($this->sharedStoreProps($request), [
            'category' => [
                'name' => 'Atacado',
                'slug' => 'atacado',
                'description' => 'Compre em quantidade e aproveite preços especiais por faixa.',
                'is_wholesale' => true,
            ],
            'products' => $products,
            'breadcrumbs' => [
                ['label' => 'Início', 'href' => '/'],
                ['label' => 'Atacado', 'href' => '/atacado'],
            ],
        ]));
    }

    public function category(Request $request, string $slug): Response
    {
        $tenantId = $this->storeTenantId();
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();

        $query = Product::query()
            ->where('type', Product::TYPE_FISICO)
            ->where('is_active', true)
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        if (! $category->is_wholesale) {
            $this->catalog->applyRetailVisibility($query);
        }

        $products = $query
            ->orderByDesc('created_at')
            ->paginate(24)
            ->through(fn (Product $p) => $this->catalog->productCard($p));

        return Inertia::render('Storefront/Category', array_merge($this->sharedStoreProps($request), [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'is_wholesale' => (bool) $category->is_wholesale,
            ],
            'products' => $products,
            'breadcrumbs' => [
                ['label' => 'Início', 'href' => '/'],
                ['label' => $category->name, 'href' => '/categoria/'.$category->slug],
            ],
        ]));
    }

    public function product(Request $request, string $slug): Response
    {
        $tenantId = $this->storeTenantId();
        $product = Product::query()
            ->with('categories')
            ->where('slug', $slug)
            ->where('type', Product::TYPE_FISICO)
            ->where('is_active', true)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();

        $relatedQuery = Product::query()
            ->where('type', Product::TYPE_FISICO)
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($product->categories->isNotEmpty(), function ($q) use ($product) {
                $q->whereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $product->categories->pluck('id')));
            });
        $this->catalog->applyRetailVisibility($relatedQuery);
        $related = $relatedQuery
            ->limit(8)
            ->get()
            ->map(fn (Product $p) => $this->catalog->productCard($p));

        $cat = $product->categories->first();

        return Inertia::render('Storefront/Product', array_merge($this->sharedStoreProps($request), [
            'product' => $this->catalog->productCard($product),
            'related' => $related,
            'breadcrumbs' => array_values(array_filter([
                ['label' => 'Início', 'href' => '/'],
                $cat ? ['label' => $cat->name, 'href' => '/categoria/'.$cat->slug] : null,
                ['label' => $product->name, 'href' => '/produto/'.$product->slug],
            ])),
        ]));
    }

    public function search(Request $request): Response
    {
        $tenantId = $this->storeTenantId();
        $q = trim((string) $request->query('q', ''));
        $query = Product::query()
            ->where('type', Product::TYPE_FISICO)
            ->where('is_active', true)
            ->when($tenantId, fn ($q2) => $q2->where('tenant_id', $tenantId))
            ->when($q !== '', fn ($q2) => $q2->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            }));
        $this->catalog->applyRetailVisibility($query);

        $products = $query
            ->orderByDesc('created_at')
            ->paginate(24)
            ->appends(['q' => $q])
            ->through(fn (Product $p) => $this->catalog->productCard($p));

        return Inertia::render('Storefront/Search', array_merge($this->sharedStoreProps($request), [
            'q' => $q,
            'products' => $products,
        ]));
    }

    public function page(Request $request, string $slug): Response
    {
        $tenantId = $this->storeTenantId();
        $page = StorePage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();

        return Inertia::render('Storefront/Page', array_merge($this->sharedStoreProps($request), [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'body' => $page->body,
            ],
        ]));
    }
}
