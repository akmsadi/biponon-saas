<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A site-wide setting. Every page reads them, so all values are cached together and the cache is
 * dropped whenever one changes.
 *
 * @property string $key
 * @property mixed $value
 */
class PlatformSetting extends Model
{
    /**
     * Values used until a super-admin saves the key.
     *
     * @var array{demo_mode: bool}
     */
    public const DEFAULTS = [
        // Demo mode shows how the app is built at the bottom of every page, for showing it to clients.
        'demo_mode' => false,
    ];

    private const CACHE_KEY = 'platform_settings';

    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public static function get(string $key): mixed
    {
        $saved = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => self::query()->pluck('value', 'key')->all(),
        );

        return array_key_exists($key, $saved) ? $saved[$key] : (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    public static function demoMode(): bool
    {
        return (bool) self::get('demo_mode');
    }
}
