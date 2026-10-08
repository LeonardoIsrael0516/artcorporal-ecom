<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Branding nativo (logo, favicon, login) via Settings — aplica em config('getfy.*').
 */
class PlatformBranding
{
    /** @var list<string> */
    public const KEYS = [
        'app_name',
        'theme_primary',
        'app_logo',
        'app_logo_dark',
        'app_logo_icon',
        'app_logo_icon_dark',
        'login_hero_image',
        'favicon_url',
    ];

    public static function resolveTenantId(?Request $request = null): ?int
    {
        $user = $request?->user();
        if ($user?->tenant_id) {
            return (int) $user->tenant_id;
        }
        if ($user?->id && $user->canAccessPanel()) {
            return (int) ($user->tenant_id ?? $user->id);
        }

        $admin = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();

        return $admin?->tenant_id ?? $admin?->id;
    }

    /**
     * @return array<string, string>
     */
    public static function get(?int $tenantId = null): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $fromDb = Setting::get($key, null, $tenantId);
            if (is_string($fromDb) && trim($fromDb) !== '') {
                $out[$key] = trim($fromDb);
            } else {
                $cfg = config('getfy.'.$key);
                $out[$key] = is_string($cfg) ? $cfg : '';
            }
        }

        return $out;
    }

    public static function apply(?Request $request = null): void
    {
        $tenantId = self::resolveTenantId($request);
        if ($tenantId === null) {
            return;
        }

        foreach (self::KEYS as $key) {
            $value = Setting::get($key, null, $tenantId);
            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            config(["getfy.{$key}" => $value]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data, ?int $tenantId = null): void
    {
        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (! is_string($value) && $value !== null) {
                continue;
            }
            Setting::set($key, trim((string) ($value ?? '')), $tenantId);
        }
    }
}
