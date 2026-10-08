<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = ['tenant_id', 'key', 'value'];

    public static function getJson(string $key, mixed $default = null, ?int $tenantId = null): mixed
    {
        $query = static::query()->where('key', $key);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        $row = $query->first();
        if (! $row || $row->value === null || $row->value === '') {
            return $default;
        }
        $decoded = json_decode($row->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
    }

    public static function setJson(string $key, mixed $value, ?int $tenantId = null): void
    {
        static::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
    }
}
