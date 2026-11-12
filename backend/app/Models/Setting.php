<?php

namespace App\Models;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /** İstek boyunca tekrar sorgulamamak için önbellek. */
    private static array $cache = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (! array_key_exists($key, self::$cache)) {
            self::$cache[$key] = static::query()->where('key', $key)->value('value');
        }

        $value = self::$cache[$key];

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function getInt(string $key, int $default): int
    {
        $value = static::get($key);

        return $value === null ? $default : (int) $value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache[$key] = $value;
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }
}
