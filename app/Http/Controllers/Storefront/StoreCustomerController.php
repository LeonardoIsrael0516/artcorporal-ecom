<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Shipping\ShippingQuoteService;
use App\Services\Storefront\StoreCatalogService;
use App\Services\Storefront\StoreThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class StoreCustomerController extends Controller
{
    public function __construct(
        protected StoreThemeService $themeService,
        protected StoreCatalogService $catalog,
        protected ShippingQuoteService $shipping,
    ) {}

    protected function tenantId(): ?int
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();

        return $admin?->tenant_id ?? $admin?->id;
    }

    protected function shared(Request $request): array
    {
        $tenantId = $this->tenantId();

        return [
            'storeTheme' => $this->themeService->getTheme($tenantId),
            'menuCategories' => $this->catalog->serializeMenuTree($tenantId),
            'cartCount' => 0,
            'isPreview' => false,
        ];
    }

    public function showLogin(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/conta');
        }

        return Inertia::render('Storefront/Login', array_merge($this->shared($request), [
            'mode' => $request->query('modo') === 'cadastro' ? 'register' : 'login',
        ]));
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], (bool) ($data['remember'] ?? false))) {
            return back()->withErrors(['email' => 'E-mail ou senha incorretos.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended('/conta');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function account(Request $request): Response|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect('/conta/entrar');
        }
        $user = $request->user();
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'status' => $o->status,
                'amount' => (float) $o->amount,
                'shipping_service' => $o->shipping_service,
                'shipping_tracking' => $o->shipping_tracking,
                'fulfillment_status' => is_array($o->metadata) ? ($o->metadata['fulfillment_status'] ?? null) : null,
                'tracking_url' => is_array($o->metadata) ? ($o->metadata['shipping_tracking_url'] ?? null) : null,
                'created_at' => $o->created_at?->toIso8601String(),
            ]);

        $addresses = CustomerAddress::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->get();

        return Inertia::render('Storefront/Account', array_merge($this->shared($request), [
            'orders' => $orders,
            'addresses' => $addresses,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $tenantId = $this->tenantId();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_ALUNO,
            'tenant_id' => $tenantId,
        ]);
        Auth::login($user);

        return redirect('/conta');
    }

    public function newsletter(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        NewsletterSubscriber::firstOrCreate(
            [
                'tenant_id' => $this->tenantId(),
                'email' => strtolower($data['email']),
            ],
            ['subscribed_at' => now()]
        );

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Inscrição realizada!');
    }

    public function shippingQuote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cep' => ['required', 'string'],
            'product_id' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'string'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
        ]);

        $lines = [];
        if (! empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $lines[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ];
            }
        } elseif (! empty($data['product_id'])) {
            $lines[] = [
                'product_id' => $data['product_id'],
                'quantity' => (int) ($data['quantity'] ?? 1),
            ];
        }

        $quotes = $this->shipping->quote($data['cep'], $lines, $this->tenantId());

        return response()->json(['quotes' => $quotes]);
    }

    public function saveAddress(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:64'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'cep' => ['required', 'string', 'max:16'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:32'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        if (! empty($data['is_default'])) {
            CustomerAddress::where('user_id', $user->id)->update(['is_default' => false]);
        }
        CustomerAddress::create(array_merge($data, [
            'user_id' => $user->id,
            'cep' => preg_replace('/\D/', '', $data['cep']),
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]));

        return back()->with('success', 'Endereço salvo.');
    }
}
