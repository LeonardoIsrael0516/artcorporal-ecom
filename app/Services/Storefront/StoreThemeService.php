<?php

namespace App\Services\Storefront;

use App\Models\StoreSetting;
use Illuminate\Support\Str;

class StoreThemeService
{
    public const THEME_KEY = 'store_theme';

    public const SHIPPING_KEY = 'shipping_settings';

    public const PAYMENT_KEY = 'store_payment_settings';

    /**
     * @return array<string, mixed>
     */
    public function defaultTheme(): array
    {
        return [
            'brand' => [
                'name' => 'Art Corporal',
                'tagline' => 'Body Piercing',
                'logo' => '',
                'favicon' => '',
                'primary' => '#D4AF7F',
                'accent_light' => '#EAD6C6',
                'secondary' => '#0E2F24',
                'bg' => '#F3E8DC',
                'text' => '#0E2F24',
                'header_bg' => '#0E2F24',
                'header_text' => '#EAD6C6',
                'footer_bg' => '#0A241C',
                'font_heading' => "'Cormorant Garamond', Georgia, serif",
                'font_body' => "'Montserrat', system-ui, sans-serif",
            ],
            'announcement_bar' => [
                'enabled' => true,
                'text' => '{{free_shipping_min}} · Parcele em até 6x sem juros',
                'link' => '',
                'bg' => '#D4AF7F',
                'color' => '#0E2F24',
            ],
            'header' => [
                'mirror_categories' => true,
                'links' => [
                    ['label' => 'Início', 'href' => '/'],
                    ['label' => 'Loja', 'href' => '/loja'],
                ],
            ],
            'sections' => [
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'hero_slider',
                    'enabled' => true,
                    'props' => [
                        'slides' => [
                            [
                                'image' => '',
                                'image_mobile' => '',
                                'title' => 'Novidades da loja',
                                'subtitle' => 'Qualidade e estilo para o seu piercing',
                                'cta' => 'Ver produtos',
                                'link' => '/loja',
                            ],
                        ],
                        'autoplay_ms' => 4500,
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'category_circles',
                    'enabled' => true,
                    'props' => [
                        'mode' => 'featured',
                        'category_ids' => [],
                        'title' => '',
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'product_shelf',
                    'enabled' => true,
                    'props' => [
                        'title' => 'Lançamentos',
                        'source' => 'newest',
                        'category_id' => null,
                        'product_ids' => [],
                        'limit' => 8,
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'trust_bar',
                    'enabled' => true,
                    'props' => [
                        'items' => [
                            ['icon' => 'shield', 'title' => 'Site 100% seguro', 'text' => 'Compra protegida'],
                            ['icon' => 'refresh', 'title' => 'Troca fácil e rápida', 'text' => 'Política clara'],
                            ['icon' => 'truck', 'title' => 'Frete calculado', 'text' => 'Melhor Envio e Frenet'],
                            ['icon' => 'whatsapp', 'title' => 'Atendimento WhatsApp', 'text' => 'Fale conosco'],
                        ],
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'product_shelf',
                    'enabled' => true,
                    'props' => [
                        'title' => 'Mais Vendidos',
                        'source' => 'bestsellers',
                        'category_id' => null,
                        'product_ids' => [],
                        'limit' => 8,
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'promo_split',
                    'enabled' => true,
                    'props' => [
                        'title' => 'Coleção em destaque',
                        'image' => '',
                        'bg' => '#10261D',
                        'color' => '#F3E7D3',
                        'bullets' => ['Antialérgico', 'Flexível', 'Confortável'],
                        'cta' => 'Ver coleção',
                        'link' => '/loja',
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'instagram_grid',
                    'enabled' => true,
                    'props' => [
                        'title' => 'Escolha o seu detalhe favorito',
                        'items' => [],
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'newsletter',
                    'enabled' => true,
                    'props' => [
                        'title' => 'Receba nossas novidades',
                        'subtitle' => 'Promoções e lançamentos no seu e-mail',
                    ],
                ],
            ],
            'footer' => [
                'columns' => [
                    [
                        'title' => 'Institucional',
                        'links' => [
                            ['label' => 'Quem Somos', 'href' => '/pagina/quem-somos'],
                            ['label' => 'Política de Privacidade', 'href' => '/pagina/privacidade'],
                            ['label' => 'Termos de Uso', 'href' => '/pagina/termos'],
                        ],
                    ],
                    [
                        'title' => 'Ajuda',
                        'links' => [
                            ['label' => 'Trocas e Devoluções', 'href' => '/pagina/trocas'],
                            ['label' => 'Prazos de Entrega', 'href' => '/pagina/frete'],
                            ['label' => 'Fale Conosco', 'href' => '/pagina/contato'],
                        ],
                    ],
                ],
                'whatsapp' => '',
                'show_newsletter' => true,
                'copyright' => '',
            ],
            'seo' => [
                'title' => 'Art Corporal | Body Piercing',
                'description' => 'Joias e piercings selecionados com acabamento impecável, materiais antialérgicos e envio para todo o Brasil.',
            ],
            'social' => [
                'instagram' => '',
                'facebook' => '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTheme(?int $tenantId = null): array
    {
        $stored = StoreSetting::getJson(self::THEME_KEY, null, $tenantId);
        $theme = is_array($stored)
            ? array_replace_recursive($this->defaultTheme(), $stored)
            : $this->defaultTheme();

        $shipping = $this->getShippingSettings($tenantId);
        $free = is_array($shipping['free_shipping'] ?? null) ? $shipping['free_shipping'] : [];
        $theme['free_shipping'] = [
            'enabled' => ! empty($free['enabled']),
            'min_amount' => max(0, (float) ($free['min_amount'] ?? 0)),
        ];

        // Tema antigo com valor fixo R$199 → passa a usar o placeholder editável em Frete.
        $announcementText = (string) ($theme['announcement_bar']['text'] ?? '');
        if ($announcementText === 'Frete grátis acima de R$199 · Parcele em até 6x sem juros') {
            $theme['announcement_bar']['text'] = '{{free_shipping_min}} · Parcele em até 6x sem juros';
        }

        return $theme;
    }

    /**
     * Inline CSS custom properties for the storefront (html/body), so the
     * palette is available even before Vue hydrates.
     */
    public function cssCustomProperties(?int $tenantId = null): string
    {
        $brand = $this->getTheme($tenantId)['brand'] ?? [];
        $gold = $this->safeCssColor($brand['primary'] ?? null, '#D4AF7F');
        $goldLight = $this->safeCssColor($brand['accent_light'] ?? null, '#EAD6C6');
        $secondary = $this->safeCssColor($brand['secondary'] ?? null, '#0E2F24');
        $bg = $this->safeCssColor($brand['bg'] ?? null, '#F3E8DC');
        $text = $this->safeCssColor($brand['text'] ?? null, '#0E2F24');
        $headerBg = $this->safeCssColor($brand['header_bg'] ?? null, '#0E2F24');
        $headerText = $this->safeCssColor($brand['header_text'] ?? null, '#EAD6C6');
        $footerBg = $this->safeCssColor($brand['footer_bg'] ?? null, '#0A241C');

        $heading = $this->safeCssFont($brand['font_heading'] ?? null, "'Cormorant Garamond', Georgia, serif");
        $body = $this->safeCssFont($brand['font_body'] ?? null, "'Montserrat', system-ui, sans-serif");

        return implode('', [
            "--sf-primary: {$gold};",
            "--sf-accent-light: {$goldLight};",
            "--sf-secondary: {$secondary};",
            "--sf-bg: {$bg};",
            "--sf-text: {$text};",
            "--sf-header-bg: {$headerBg};",
            "--sf-header-text: {$headerText};",
            "--sf-footer-bg: {$footerBg};",
            "--sf-gold-gradient: linear-gradient(120deg, {$gold} 0%, {$goldLight} 45%, {$gold} 100%);",
            "--sf-font-heading: {$heading};",
            "--sf-font-body: {$body};",
        ]);
    }

    private function safeCssColor(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';
        if (preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/', $value)) {
            return $value;
        }

        return $fallback;
    }

    private function safeCssFont(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value !== '' && ! preg_match('/[<>{}@]/', $value)) {
            return $value;
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    public function saveTheme(array $theme, ?int $tenantId = null): void
    {
        unset($theme['free_shipping']);
        StoreSetting::setJson(self::THEME_KEY, $theme, $tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultShippingSettings(): array
    {
        return [
            'origin_cep' => '',
            'origin_cnpj' => '',
            'fallback_weight_g' => 100,
            'fallback_height_cm' => 2,
            'fallback_width_cm' => 10,
            'fallback_length_cm' => 15,
            'markup_percent' => 0,
            'extra_days' => 0,
            'free_shipping' => [
                'enabled' => false,
                'min_amount' => 199,
            ],
            'melhor_envio' => [
                'enabled' => false,
                'sandbox' => true,
                'token' => '',
                'services' => [],
            ],
            'frenet' => [
                'enabled' => false,
                'token' => '',
                'seller_cep' => '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getShippingSettings(?int $tenantId = null): array
    {
        $stored = StoreSetting::getJson(self::SHIPPING_KEY, null, $tenantId);
        if (! is_array($stored)) {
            return $this->defaultShippingSettings();
        }

        return array_replace_recursive($this->defaultShippingSettings(), $stored);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function saveShippingSettings(array $settings, ?int $tenantId = null): void
    {
        StoreSetting::setJson(self::SHIPPING_KEY, $settings, $tenantId);
    }

    /**
     * Métodos de pagamento da loja (checkout do carrinho).
     *
     * @return array{payment_gateways: array<string, mixed>}
     */
    public function defaultPaymentSettings(): array
    {
        return [
            'payment_gateways' => [
                'pix' => '__default__',
                'card' => '__default__',
                'boleto' => '__default__',
                'apple_pay' => '',
                'google_pay' => '',
                'paypal' => '',
                'pix_redundancy' => [],
                'card_redundancy' => [],
                'boleto_redundancy' => [],
                'apple_pay_redundancy' => [],
                'google_pay_redundancy' => [],
                'paypal_redundancy' => [],
                'paypal_display_as' => 'paypal',
                'paypal_show_wallet' => false,
            ],
        ];
    }

    /**
     * @return array{payment_gateways: array<string, mixed>}
     */
    public function getPaymentSettings(?int $tenantId = null): array
    {
        $stored = StoreSetting::getJson(self::PAYMENT_KEY, null, $tenantId);
        if (! is_array($stored)) {
            return $this->defaultPaymentSettings();
        }

        $defaults = $this->defaultPaymentSettings();
        $pg = array_merge(
            $defaults['payment_gateways'],
            is_array($stored['payment_gateways'] ?? null) ? $stored['payment_gateways'] : []
        );

        return ['payment_gateways' => $pg];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function savePaymentSettings(array $settings, ?int $tenantId = null): void
    {
        $defaults = $this->defaultPaymentSettings();
        $pgIn = is_array($settings['payment_gateways'] ?? null) ? $settings['payment_gateways'] : [];
        $pg = array_merge($defaults['payment_gateways'], $pgIn);

        foreach (['pix', 'card', 'boleto', 'apple_pay', 'google_pay', 'paypal'] as $method) {
            $pg[$method] = trim((string) ($pg[$method] ?? ''));
            $redKey = $method.'_redundancy';
            $pg[$redKey] = array_values(array_filter(
                is_array($pg[$redKey] ?? null) ? $pg[$redKey] : [],
                fn ($s) => is_string($s) && $s !== ''
            ));
        }

        StoreSetting::setJson(self::PAYMENT_KEY, ['payment_gateways' => $pg], $tenantId);
    }

    /**
     * Config de gateways efetiva para o checkout da loja.
     *
     * @return array<string, mixed>
     */
    public function resolveStorePaymentGateways(?int $tenantId = null): array
    {
        return $this->getPaymentSettings($tenantId)['payment_gateways'] ?? [];
    }

    /**
     * @return list<array{type: string, label: string}>
     */
    public function sectionTypes(): array
    {
        return [
            ['type' => 'hero_slider', 'label' => 'Hero / Slider'],
            ['type' => 'category_circles', 'label' => 'Categorias circulares'],
            ['type' => 'product_shelf', 'label' => 'Prateleira de produtos'],
            ['type' => 'trust_bar', 'label' => 'Barra de confiança'],
            ['type' => 'promo_split', 'label' => 'Banner promocional'],
            ['type' => 'instagram_grid', 'label' => 'Grid Instagram'],
            ['type' => 'newsletter', 'label' => 'Newsletter'],
            ['type' => 'rich_text', 'label' => 'Texto rico'],
        ];
    }
}
