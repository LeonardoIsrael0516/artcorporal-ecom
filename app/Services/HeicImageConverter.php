<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HeicImageConverter
{
    /** @var list<string> */
    public const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'];

    /** @var list<string> */
    private array $tempFiles = [];

    /**
     * Validation rules that accept HEIC/HEIF (Laravel `image` rejects them).
     *
     * @return list<string>
     */
    public static function uploadRules(int $maxKb = 8192, bool $required = false): array
    {
        $ext = implode(',', self::ACCEPTED_EXTENSIONS);

        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:'.$maxKb,
            'extensions:'.$ext,
        ];
    }

    public function isHeic(UploadedFile|string $file): bool
    {
        if ($file instanceof UploadedFile) {
            $ext = strtolower((string) $file->getClientOriginalExtension());
            $mime = strtolower((string) ($file->getMimeType() ?: ''));
        } else {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $mime = '';
        }

        if (in_array($ext, ['heic', 'heif'], true)) {
            return true;
        }

        return str_contains($mime, 'heic') || str_contains($mime, 'heif');
    }

    public function available(): bool
    {
        return $this->resolveTool() !== null;
    }

    /**
     * If the upload is HEIC/HEIF, convert to JPEG UploadedFile; otherwise return as-is.
     *
     * @throws ValidationException
     */
    public function normalizeUploadedImage(UploadedFile $file): UploadedFile
    {
        if (! $this->isHeic($file)) {
            return $file;
        }

        $tool = $this->resolveTool();
        if ($tool === null) {
            throw ValidationException::withMessages([
                'image' => 'Arquivos HEIC não puderam ser convertidos. Instale ImageMagick com suporte HEIC no servidor.',
            ]);
        }

        $tempDir = storage_path('app/tmp/heic-upload');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $dest = $tempDir.DIRECTORY_SEPARATOR.Str::uuid().'.jpg';
        $source = $file->getRealPath() ?: $file->getPathname();

        if (! $this->convertPathToJpeg($source, $dest, $tool)) {
            throw ValidationException::withMessages([
                'image' => 'Falha ao converter imagem HEIC para JPG. Tente outro arquivo ou formato (JPG/PNG).',
            ]);
        }

        $this->tempFiles[] = $dest;

        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.jpg';

        return new UploadedFile($dest, $original, 'image/jpeg', null, true);
    }

    public function convertPathToJpeg(string $source, string $dest, ?string $tool = null): bool
    {
        $tool ??= $this->resolveTool();
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

            $cmd = [$tool, $source, '-quality', '85', $dest];
            $result = Process::timeout(120)->run($cmd);

            return $result->successful() && is_file($dest) && filesize($dest) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function resolveTool(): ?string
    {
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

    public function cleanup(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    public function __destruct()
    {
        $this->cleanup();
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
}
