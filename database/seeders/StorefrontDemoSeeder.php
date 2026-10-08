<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StorePage;
use App\Models\User;
use App\Services\Storefront\StoreThemeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StorefrontDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();
        if (! $admin) {
            return;
        }
        $tenantId = $admin->tenant_id ?? $admin->id;
        if (! $admin->tenant_id) {
            $admin->update(['tenant_id' => $admin->id]);
            $tenantId = $admin->id;
        }

        app(StoreThemeService::class)->saveTheme(
            app(StoreThemeService::class)->defaultTheme(),
            $tenantId
        );

        $cats = [
            ['name' => 'Septo', 'featured' => true],
            ['name' => 'Orelha', 'featured' => true],
            ['name' => 'Umbigo', 'featured' => true],
            ['name' => 'Mamilo', 'featured' => true],
            ['name' => 'Nariz', 'featured' => true],
            ['name' => 'Brincos', 'featured' => true],
        ];
        $categoryModels = [];
        foreach ($cats as $i => $c) {
            $categoryModels[] = Category::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => Str::slug($c['name'])],
                [
                    'name' => $c['name'],
                    'position' => $i,
                    'is_active' => true,
                    'is_featured_circle' => $c['featured'],
                    'show_in_menu' => true,
                ]
            );
        }

        $products = [
            ['name' => 'Piercing Titânio G23', 'price' => 39.9, 'pix' => 5],
            ['name' => 'Argola Segmentada', 'price' => 29.9, 'pix' => 5],
            ['name' => 'Labret Ponto de Luz', 'price' => 49.9, 'pix' => 5],
            ['name' => 'Ferradura Septo', 'price' => 34.9, 'pix' => 5],
            ['name' => 'Bioflex Umbigo', 'price' => 24.9, 'pix' => 5],
            ['name' => 'Brinco Trio Prata', 'price' => 69.9, 'pix' => 5],
            ['name' => 'Prova Piercing', 'price' => 24.9, 'pix' => 5],
            ['name' => 'Piercing Conch Cravejado', 'price' => 39.9, 'pix' => 5],
        ];

        foreach ($products as $i => $p) {
            $product = Product::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => Str::slug($p['name'])],
                [
                    'name' => $p['name'],
                    'type' => Product::TYPE_FISICO,
                    'billing_type' => Product::BILLING_ONE_TIME,
                    'price' => $p['price'],
                    'pix_discount_percent' => $p['pix'],
                    'currency' => 'BRL',
                    'is_active' => true,
                    'sku' => 'SKU-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'stock' => 50,
                    'track_stock' => true,
                    'weight_g' => 20,
                    'height_cm' => 2,
                    'width_cm' => 8,
                    'length_cm' => 8,
                    'description' => 'Produto de demonstração da loja.',
                    'technical_description' => "- Material: Titânio / Aço\n- Hipoalergênico",
                    'attention_notes' => 'Produto destinado a uso em perfuração profissional.',
                    'checkout_config' => Product::defaultCheckoutConfig(),
                ]
            );
            $cat = $categoryModels[$i % count($categoryModels)];
            $product->categories()->syncWithoutDetaching([$cat->id]);
        }

        $pages = [
            ['title' => 'Quem Somos', 'slug' => 'quem-somos', 'body' => '<p>Sobre a nossa loja.</p>'],
            ['title' => 'Privacidade', 'slug' => 'privacidade', 'body' => '<p>Política de privacidade.</p>'],
            ['title' => 'Termos', 'slug' => 'termos', 'body' => '<p>Termos de uso.</p>'],
            ['title' => 'Trocas', 'slug' => 'trocas', 'body' => '<p>Política de trocas e devoluções.</p>'],
            ['title' => 'Frete', 'slug' => 'frete', 'body' => '<p>Informações sobre prazos e envio.</p>'],
            ['title' => 'Contato', 'slug' => 'contato', 'body' => '<p>Fale conosco pelo WhatsApp.</p>'],
        ];
        foreach ($pages as $page) {
            StorePage::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'body' => $page['body'],
                    'is_published' => true,
                ]
            );
        }
    }
}
