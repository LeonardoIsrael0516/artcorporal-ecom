<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ImportStorefrontProductsFromFolderCommand extends Command
{
    protected $signature = 'storefront:import-products-folder
                            {--path=produtos : Pasta relativa à raiz do projeto (subpastas = categorias)}
                            {--price=99.90 : Preço genérico em BRL}
                            {--dry-run : Lista o que seria importado sem gravar}
                            {--force : Atualiza imagem/nome mesmo se o produto já existir}';

    protected $description = 'Importa produtos físicos a partir de produtos/{categoria}/*.(heic|jpg|png|webp).';

    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['heic', 'jpg', 'jpeg', 'png', 'webp'];

    public function handle(): int
    {
        $relative = trim((string) $this->option('path'), '/\\');
        $root = base_path($relative);
        if (! is_dir($root)) {
            $this->error("Pasta não encontrada: {$root}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $price = round((float) $this->option('price'), 2);
        if ($price <= 0) {
            $this->error('Preço inválido.');

            return self::FAILURE;
        }

        $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();
        if (! $admin) {
            $this->error('Nenhum usuário admin encontrado. Crie o admin antes de importar.');

            return self::FAILURE;
        }
        $tenantId = (int) ($admin->tenant_id ?? $admin->id);
        if (! $admin->tenant_id) {
            $admin->update(['tenant_id' => $admin->id]);
            $tenantId = (int) $admin->id;
        }

        $needsHeic = $this->folderHasHeic($root);
        $heicTool = null;
        if ($needsHeic && ! $dryRun) {
            $heicTool = $this->resolveHeicConverter();
            if ($heicTool === null) {
                $this->error('Há arquivos HEIC, mas nenhuma ferramenta de conversão foi encontrada.');
                $this->line('Instale ImageMagick com suporte HEIC (comando `magick` no PATH) e tente de novo.');

                return self::FAILURE;
            }
            $this->info("Conversor HEIC: {$heicTool}");
        }

        $categoryDirs = collect(File::directories($root))
            ->sortBy(fn (string $dir) => mb_strtolower(basename($dir)))
            ->values();

        if ($categoryDirs->isEmpty()) {
            $this->warn('Nenhuma subpasta (categoria) encontrada.');

            return self::SUCCESS;
        }

        $createdCategories = 0;
        $createdProducts = 0;
        $updatedProducts = 0;
        $skippedProducts = 0;
        $failed = 0;
        $categoryPosition = 0;

        $storage = new StorageService($tenantId);
        $tempDir = storage_path('app/tmp/storefront-import');
        if (! $dryRun) {
            File::ensureDirectoryExists($tempDir);
        }

        foreach ($categoryDirs as $categoryDir) {
            $categoryName = basename($categoryDir);
            $categorySlug = Str::slug($categoryName);
            if ($categorySlug === '') {
                $this->warn("Ignorando pasta com nome inválido: {$categoryName}");
                continue;
            }

            $images = $this->collectImages($categoryDir);
            if ($images->isEmpty()) {
                $this->line("— {$categoryName}: sem imagens (categoria vazia)");
                if (! $dryRun) {
                    Category::firstOrCreate(
                        ['tenant_id' => $tenantId, 'slug' => $categorySlug],
                        [
                            'name' => $categoryName,
                            'position' => $categoryPosition,
                            'is_active' => true,
                            'is_featured_circle' => true,
                            'show_in_menu' => true,
                        ]
                    );
                }
                $categoryPosition++;
                continue;
            }

            $this->info("Categoria: {$categoryName} ({$images->count()} imagens)");

            if ($dryRun) {
                foreach ($images as $i => $imagePath) {
                    $stem = $this->imageStem($imagePath);
                    $this->line(sprintf(
                        '  [dry-run] %s → produto "%s %d" (slug: %s)',
                        basename($imagePath),
                        $categoryName,
                        $i + 1,
                        Str::slug($categoryName.'-'.$stem)
                    ));
                }
                $categoryPosition++;
                continue;
            }

            $category = Category::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $categorySlug],
                [
                    'name' => $categoryName,
                    'position' => $categoryPosition,
                    'is_active' => true,
                    'is_featured_circle' => true,
                    'show_in_menu' => true,
                ]
            );
            if ($category->wasRecentlyCreated) {
                $createdCategories++;
            } else {
                $category->update([
                    'name' => $categoryName,
                    'is_active' => true,
                    'show_in_menu' => true,
                    'is_featured_circle' => true,
                ]);
            }
            $categoryPosition++;

            $n = 0;
            foreach ($images as $imagePath) {
                $n++;
                $stem = $this->imageStem($imagePath);
                $productSlug = Str::slug($categoryName.'-'.$stem);
                if ($productSlug === '') {
                    $productSlug = Str::slug($categoryName.'-'.$n);
                }

                $existing = Product::query()
                    ->where('tenant_id', $tenantId)
                    ->where('slug', $productSlug)
                    ->first();

                if ($existing && ! $force) {
                    $existing->categories()->syncWithoutDetaching([$category->id]);
                    $skippedProducts++;
                    continue;
                }

                $uploadPath = null;
                $convertedTemp = null;
                try {
                    $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
                    if ($ext === 'heic') {
                        $convertedTemp = $tempDir.DIRECTORY_SEPARATOR.Str::uuid().'.jpg';
                        if (! $this->convertHeicToJpeg($imagePath, $convertedTemp, $heicTool)) {
                            $this->error("  Falha ao converter HEIC: ".basename($imagePath));
                            $failed++;
                            continue;
                        }
                        $uploadPath = $convertedTemp;
                    } else {
                        $uploadPath = $imagePath;
                    }

                    $mime = match (strtolower(pathinfo($uploadPath, PATHINFO_EXTENSION))) {
                        'png' => 'image/png',
                        'webp' => 'image/webp',
                        'jpg', 'jpeg' => 'image/jpeg',
                        default => 'image/jpeg',
                    };
                    $uploaded = new UploadedFile(
                        $uploadPath,
                        basename($uploadPath),
                        $mime,
                        null,
                        true
                    );
                    $storedPath = $storage->putFile('products', $uploaded);

                    $productName = $categoryName.' '.$n;
                    if ($existing && $force) {
                        $existing->update([
                            'name' => $productName,
                            'image' => $storedPath,
                            'price' => $price,
                            'type' => Product::TYPE_FISICO,
                            'billing_type' => Product::BILLING_ONE_TIME,
                            'is_active' => true,
                            'currency' => 'BRL',
                        ]);
                        $existing->categories()->syncWithoutDetaching([$category->id]);
                        $updatedProducts++;
                        $this->line("  atualizado: {$productName}");
                    } else {
                        $product = Product::create([
                            'tenant_id' => $tenantId,
                            'name' => $productName,
                            'slug' => $productSlug,
                            'type' => Product::TYPE_FISICO,
                            'billing_type' => Product::BILLING_ONE_TIME,
                            'image' => $storedPath,
                            'price' => $price,
                            'currency' => 'BRL',
                            'is_active' => true,
                            'stock' => 999,
                            'track_stock' => false,
                            'sku' => 'IMP-'.strtoupper(Str::substr(md5($productSlug), 0, 8)),
                            'weight_g' => 20,
                            'height_cm' => 2,
                            'width_cm' => 10,
                            'length_cm' => 10,
                            'checkout_config' => Product::defaultCheckoutConfig(),
                        ]);
                        $product->categories()->syncWithoutDetaching([$category->id]);
                        $createdProducts++;
                        $this->line("  criado: {$productName}");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error('  Erro em '.basename($imagePath).': '.$e->getMessage());
                } finally {
                    if ($convertedTemp && is_file($convertedTemp)) {
                        @unlink($convertedTemp);
                    }
                }
            }
        }

        if ($dryRun) {
            $this->info('Dry-run concluído (nada foi gravado).');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Categorias novas: {$createdCategories}");
        $this->info("Produtos criados: {$createdProducts}");
        $this->info("Produtos atualizados: {$updatedProducts}");
        $this->info("Produtos ignorados (já existiam): {$skippedProducts}");
        if ($failed > 0) {
            $this->warn("Falhas: {$failed}");
        }

        return $failed > 0 && $createdProducts === 0 && $updatedProducts === 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function folderHasHeic(string $root): bool
    {
        foreach (File::directories($root) as $dir) {
            foreach (File::files($dir) as $file) {
                if (strtolower($file->getExtension()) === 'heic') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function collectImages(string $categoryDir)
    {
        $files = collect(File::files($categoryDir))
            ->filter(function ($file) {
                $ext = strtolower($file->getExtension());
                if (! in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                    return false;
                }
                $base = $file->getFilenameWithoutExtension();
                // Preferir IMG_3337.HEIC em vez de IMG_3337(1).HEIC
                if (preg_match('/\(\d+\)$/', $base)) {
                    return false;
                }

                return true;
            })
            ->sortBy(fn ($file) => mb_strtolower($file->getFilename()))
            ->map(fn ($file) => $file->getPathname())
            ->values();

        return $files;
    }

    private function imageStem(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    private function resolveHeicConverter(): ?string
    {
        // Preferir `magick` (ImageMagick 7). Evitar `convert` no Windows (é o utilitário do sistema).
        foreach (['magick', 'heif-convert'] as $bin) {
            if ($this->binaryExists($bin)) {
                return $bin;
            }
        }

        if (PHP_OS_FAMILY !== 'Windows' && $this->binaryExists('convert') && $this->isImageMagickConvert()) {
            return 'convert';
        }

        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            try {
                $formats = array_map('strtoupper', \Imagick::queryFormats());
                if (in_array('HEIC', $formats, true) || in_array('HEIF', $formats, true)) {
                    return 'imagick';
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return null;
    }

    private function binaryExists(string $bin): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $result = Process::run(['where', $bin]);
        } else {
            $result = Process::run(['which', $bin]);
        }

        return $result->successful() && trim($result->output()) !== '';
    }

    private function isImageMagickConvert(): bool
    {
        $result = Process::run(['convert', '-version']);
        if (! $result->successful()) {
            return false;
        }

        return str_contains(mb_strtolower($result->output().$result->errorOutput()), 'imagemagick');
    }

    private function convertHeicToJpeg(string $source, string $dest, ?string $tool): bool
    {
        if ($tool === null) {
            return false;
        }

        try {
            if ($tool === 'imagick') {
                $img = new \Imagick($source);
                $img->setImageFormat('jpeg');
                $img->setImageCompressionQuality(85);
                $img->writeImage($dest);
                $img->clear();
                $img->destroy();

                return is_file($dest) && filesize($dest) > 0;
            }

            if ($tool === 'heif-convert') {
                $result = Process::timeout(120)->run([$tool, $source, $dest]);

                return $result->successful() && is_file($dest) && filesize($dest) > 0;
            }

            // ImageMagick: magick input.heic -quality 85 output.jpg
            // convert: convert input.heic -quality 85 output.jpg
            $cmd = $tool === 'magick'
                ? [$tool, $source, '-quality', '85', $dest]
                : [$tool, $source, '-quality', '85', $dest];
            $result = Process::timeout(120)->run($cmd);

            return $result->successful() && is_file($dest) && filesize($dest) > 0;
        } catch (\Throwable $e) {
            $this->warn('  Conversão HEIC falhou: '.$e->getMessage());

            return false;
        }
    }
}
