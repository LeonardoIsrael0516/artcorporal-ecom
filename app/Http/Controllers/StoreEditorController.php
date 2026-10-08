<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\StorePage;
use App\Services\Storefront\StoreThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StoreEditorController extends Controller
{
    public function __construct(protected StoreThemeService $themeService) {}

    protected function tenantId(Request $request): ?int
    {
        $user = $request->user();

        return $user->tenant_id ?? $user->id;
    }

    public function edit(Request $request): Response
    {
        $tenantId = $this->tenantId($request);
        $theme = $this->themeService->getTheme($tenantId);
        $categories = Category::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('StoreEditor/Index', [
            'theme' => $theme,
            'sectionTypes' => $this->themeService->sectionTypes(),
            'categories' => $categories,
            'pageTitle' => 'Editor da Loja',
            'layoutFullWidth' => true,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validate([
            'theme' => ['required', 'array'],
        ]);
        $this->themeService->saveTheme($data['theme'], $tenantId);

        return back()->with('success', 'Loja publicada.');
    }

    public function upload(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:8192'],
        ]);
        $path = $request->file('file')->store('storefront', 'public');

        return response()->json([
            'path' => $path,
            'url' => asset('storage/'.$path),
        ]);
    }

    public function shipping(Request $request): Response
    {
        $tenantId = $this->tenantId($request);

        return Inertia::render('StoreEditor/Shipping', [
            'settings' => $this->themeService->getShippingSettings($tenantId),
            'pageTitle' => 'Frete',
        ]);
    }

    public function updateShipping(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);
        $this->themeService->saveShippingSettings($data['settings'], $tenantId);

        return back()->with('success', 'Configurações de frete salvas.');
    }

    public function payment(Request $request): Response
    {
        $tenantId = $this->tenantId($request);
        $settings = $this->themeService->getPaymentSettings($tenantId);
        $connectedSlugs = \App\Models\GatewayCredential::forTenant($tenantId)
            ->where('is_connected', true)
            ->pluck('gateway_slug')
            ->all();
        $byMethod = [
            'pix' => [],
            'card' => [],
            'boleto' => [],
            'apple_pay' => [],
            'google_pay' => [],
            'paypal' => [],
        ];
        foreach (\App\Gateways\GatewayRegistry::all() as $gateway) {
            $slug = $gateway['slug'] ?? '';
            if (! in_array($slug, $connectedSlugs, true)) {
                continue;
            }
            $item = ['slug' => $slug, 'name' => $gateway['name'] ?? $slug];
            foreach (array_keys($byMethod) as $method) {
                if (in_array($method, $gateway['methods'] ?? [], true)) {
                    $byMethod[$method][] = $item;
                }
            }
        }

        return Inertia::render('StoreEditor/Payment', [
            'settings' => $settings,
            'gateways_by_method' => $byMethod,
            'pageTitle' => 'Pagamento',
        ]);
    }

    public function updatePayment(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.payment_gateways' => ['required', 'array'],
            'settings.payment_gateways.pix' => ['nullable', 'string', 'max:64'],
            'settings.payment_gateways.card' => ['nullable', 'string', 'max:64'],
            'settings.payment_gateways.boleto' => ['nullable', 'string', 'max:64'],
            'settings.payment_gateways.apple_pay' => ['nullable', 'string', 'max:64'],
            'settings.payment_gateways.google_pay' => ['nullable', 'string', 'max:64'],
            'settings.payment_gateways.paypal' => ['nullable', 'string', 'max:64'],
            'settings.payment_gateways.paypal_display_as' => ['nullable', 'string', 'in:paypal,card'],
            'settings.payment_gateways.paypal_show_wallet' => ['nullable', 'boolean'],
        ]);
        $this->themeService->savePaymentSettings($data['settings'], $tenantId);

        return back()->with('success', 'Métodos de pagamento da loja salvos.');
    }

    public function pagesIndex(Request $request): Response
    {
        $tenantId = $this->tenantId($request);
        $pages = StorePage::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('title')
            ->get();

        return Inertia::render('StoreEditor/Pages', [
            'pages' => $pages,
            'pageTitle' => 'Páginas',
        ]);
    }

    public function storePage(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        StorePage::create([
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'slug' => $data['slug'] ?? null,
            'body' => $data['body'] ?? '',
            'is_published' => (bool) ($data['is_published'] ?? true),
        ]);

        return back()->with('success', 'Página criada.');
    }

    public function updatePage(Request $request, StorePage $page): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        abort_unless((int) $page->tenant_id === (int) $tenantId || $request->user()->isAdmin(), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $page->update([
            'title' => $data['title'],
            'slug' => $data['slug'] ?: $page->slug,
            'body' => $data['body'] ?? '',
            'is_published' => (bool) ($data['is_published'] ?? $page->is_published),
        ]);

        return back()->with('success', 'Página atualizada.');
    }
}
