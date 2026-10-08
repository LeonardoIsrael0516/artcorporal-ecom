<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CommerceCart;
use App\Models\CustomerAddress;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\CommerceCartService;
use App\Services\Commerce\CommerceCheckoutBridge;
use App\Services\Shipping\ShippingQuoteService;
use App\Services\Storefront\StoreCatalogService;
use App\Services\Storefront\StoreThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StoreCartController extends Controller
{
    public function __construct(
        protected CommerceCartService $cartService,
        protected StoreThemeService $themeService,
        protected StoreCatalogService $catalog,
        protected ShippingQuoteService $shipping,
        protected CommerceCheckoutBridge $checkoutBridge,
    ) {}

    protected function tenantId(): int
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->firstOrFail();

        return (int) ($admin->tenant_id ?? $admin->id);
    }

    protected function shared(Request $request): array
    {
        $tenantId = $this->tenantId();
        $theme = $this->themeService->getTheme($tenantId);
        $cart = $this->cartService->getOrCreate($request, $tenantId);

        return [
            'storeTheme' => $theme,
            'menuCategories' => $this->catalog->serializeMenuTree($tenantId),
            'cartCount' => (int) $cart->lines->sum('quantity'),
            'isPreview' => false,
        ];
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>, subtotal: float, shipping_quote: mixed, shipping_address: mixed, customer: mixed}
     */
    protected function serializeCart(CommerceCart $cart): array
    {
        $lines = [];
        $subtotal = 0.0;
        foreach ($cart->lines as $line) {
            $product = Product::find($line->product_id);
            $amount = (float) $line->unit_amount * (int) $line->quantity;
            $subtotal += $amount;
            $lines[] = [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'name' => $product?->name ?? 'Produto',
                'quantity' => (int) $line->quantity,
                'unit_amount' => (float) $line->unit_amount,
                'amount' => $amount,
                'image_url' => $product?->galleryUrls()[0] ?? null,
                'slug' => $product?->slug,
            ];
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'shipping_quote' => $cart->shipping_quote,
            'shipping_address' => $cart->shipping_address,
            'customer' => is_array($cart->shipping_address) ? ($cart->shipping_address['customer'] ?? null) : null,
        ];
    }

    /**
     * Prefill data for logged-in store customer (aluno).
     *
     * @return array<string, mixed>|null
     */
    protected function customerPrefill(?User $user): ?array
    {
        if (! $user || $user->role !== User::ROLE_ALUNO) {
            return null;
        }

        $address = CustomerAddress::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        $nameParts = preg_split('/\s+/', trim((string) $user->name), 2);

        return [
            'email' => $user->email,
            'name' => $user->name,
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'phone' => $address?->phone,
            'address' => $address ? [
                'cep' => $address->cep,
                'street' => $address->street,
                'number' => $address->number,
                'complement' => $address->complement,
                'district' => $address->district,
                'city' => $address->city,
                'state' => $address->state,
                'recipient_name' => $address->recipient_name,
            ] : null,
        ];
    }

    public function show(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $cart = $this->cartService->getOrCreate($request, $tenantId)->load('lines');
        cookie()->queue($this->cartService->cartCookie($cart));

        return Inertia::render('Storefront/Cart', array_merge($this->shared($request), [
            'cart' => $this->serializeCart($cart),
            'customer' => $this->customerPrefill($request->user()),
        ]));
    }

    public function delivery(Request $request): Response|RedirectResponse
    {
        $tenantId = $this->tenantId();
        $cart = $this->cartService->getOrCreate($request, $tenantId)->load('lines');
        if ($cart->lines->isEmpty()) {
            return redirect('/carrinho');
        }

        cookie()->queue($this->cartService->cartCookie($cart));

        $serialized = $this->serializeCart($cart);
        $prefill = $this->customerPrefill($request->user());

        return Inertia::render('Storefront/Checkout/Delivery', array_merge($this->shared($request), [
            'cart' => $serialized,
            'customer' => $prefill,
            'saved_address' => $prefill['address'] ?? null,
        ]));
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);
        $tenantId = $this->tenantId();
        $product = Product::query()
            ->where('id', $data['product_id'])
            ->where('type', Product::TYPE_FISICO)
            ->where('is_active', true)
            ->firstOrFail();

        $qty = (int) ($data['quantity'] ?? 1);
        if (! $product->inStock($qty)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Estoque insuficiente.'], 422);
            }

            return back()->with('error', 'Estoque insuficiente.');
        }

        $cart = $this->cartService->getOrCreate($request, $tenantId);
        $this->cartService->addLine($cart, [
            'product_id' => $product->id,
            'quantity' => $qty,
        ]);

        if ($request->wantsJson()) {
            $cart->refresh()->load('lines');

            return response()->json([
                'ok' => true,
                'cart_count' => (int) $cart->lines->sum('quantity'),
            ])->cookie(CommerceCartService::COOKIE_NAME, $cart->session_token, 60 * 24 * 14);
        }

        return redirect('/carrinho')->cookie(CommerceCartService::COOKIE_NAME, $cart->session_token, 60 * 24 * 14);
    }

    public function updateLine(Request $request, int $lineId): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);
        $cart = $this->cartService->getOrCreate($request, $this->tenantId());
        $this->cartService->updateQuantity($cart, $lineId, (int) $data['quantity']);

        return back();
    }

    public function removeLine(Request $request, int $lineId): RedirectResponse
    {
        $cart = $this->cartService->getOrCreate($request, $this->tenantId());
        $this->cartService->removeLine($cart, $lineId);

        return back();
    }

    public function setShipping(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'quote' => ['required', 'array'],
            'quote.id' => ['required', 'string'],
            'quote.provider' => ['required', 'string'],
            'quote.name' => ['required', 'string'],
            'quote.price' => ['required', 'numeric', 'min:0'],
            'quote.days' => ['nullable', 'integer'],
            'cep' => ['nullable', 'string'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:32'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.district' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.state' => ['nullable', 'string', 'max:2'],
            'address.no_number' => ['nullable', 'boolean'],
        ]);
        $cart = $this->cartService->getOrCreate($request, $this->tenantId());
        $cart->shipping_quote = $data['quote'];
        $addr = is_array($cart->shipping_address) ? $cart->shipping_address : [];
        if (! empty($data['cep'])) {
            $addr['cep'] = preg_replace('/\D/', '', $data['cep']);
        }
        if (! empty($data['address']) && is_array($data['address'])) {
            foreach (['street', 'number', 'complement', 'district', 'city', 'state'] as $key) {
                if (array_key_exists($key, $data['address']) && $data['address'][$key] !== null) {
                    $addr[$key] = $data['address'][$key];
                }
            }
            if (! empty($data['address']['no_number'])) {
                $addr['number'] = 'S/N';
                $addr['no_number'] = true;
            }
        }
        $cart->shipping_address = $addr;
        $cart->save();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    /**
     * Save delivery step and create pending order → payment.
     */
    public function submitDelivery(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $cart = $this->cartService->getOrCreate($request, $tenantId)->load('lines');
        if ($cart->lines->isEmpty()) {
            return redirect('/carrinho')->with('error', 'Carrinho vazio.');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'cpf' => ['required', 'string', 'max:20'],
            'cep' => ['required', 'string', 'max:16'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:32'],
            'no_number' => ['nullable', 'boolean'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
            'quote' => ['required', 'array'],
            'quote.id' => ['required', 'string'],
            'quote.provider' => ['required', 'string'],
            'quote.name' => ['required', 'string'],
            'quote.price' => ['required', 'numeric', 'min:0'],
            'quote.days' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $noNumber = (bool) ($data['no_number'] ?? false);
        $number = $noNumber ? 'S/N' : trim((string) ($data['number'] ?? ''));
        if ($number === '') {
            throw ValidationException::withMessages(['number' => 'Informe o número ou marque "Sem número".']);
        }

        $cep = preg_replace('/\D/', '', $data['cep']);
        $cpf = preg_replace('/\D/', '', $data['cpf']);
        if (strlen($cep) !== 8) {
            throw ValidationException::withMessages(['cep' => 'CEP inválido.']);
        }
        if (strlen($cpf) !== 11 && strlen($cpf) !== 14) {
            throw ValidationException::withMessages(['cpf' => 'CPF ou CNPJ inválido.']);
        }

        $fullName = trim($data['first_name'].' '.$data['last_name']);
        $address = [
            'cep' => $cep,
            'street' => $data['street'],
            'number' => $number,
            'no_number' => $noNumber,
            'complement' => $data['complement'] ?? null,
            'district' => $data['district'],
            'city' => $data['city'],
            'state' => strtoupper($data['state']),
            'country' => 'BR',
            'recipient_name' => $fullName,
            'phone' => $data['phone'],
            'email' => $data['email'],
            'cpf' => $cpf,
            'notes' => $data['notes'] ?? null,
            'customer' => [
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => $fullName,
                'phone' => $data['phone'],
                'cpf' => $cpf,
            ],
        ];

        $cart->shipping_quote = $data['quote'];
        $cart->shipping_address = $address;
        $cart->save();

        if ($request->boolean('marketing_opt_in')) {
            NewsletterSubscriber::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'email' => strtolower($data['email']),
                ],
                ['subscribed_at' => now()]
            );
        }

        $result = $this->checkoutBridge->startFromCart($cart, [
            'email' => $data['email'],
            'name' => $fullName,
            'cpf' => $cpf,
            'phone' => $data['phone'],
            'login' => true,
            'save_address' => true,
        ]);

        return redirect()->to($result['checkout_url']);
    }

    public function checkout(Request $request): RedirectResponse
    {
        // Legacy endpoint — redirect guests to delivery step
        return redirect('/checkout/entrega');
    }
}
