<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ProduitCache
{
    private const VERSION_KEY = 'produits.cache.version';

    public static function version(): int
    {
        return (int) (Cache::get(self::VERSION_KEY) ?? 1);
    }

    public static function bump(): void
    {
        if (!Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 1);
        }
        Cache::increment(self::VERSION_KEY);
    }

    public static function keyIndex(?string $queryString): string
    {
        return 'produits.index.v' . self::version() . '.' . md5($queryString ?? '');
    }

    public static function keyShow(string $id): string
    {
        return 'produits.show.v' . self::version() . '.' . $id;
    }
}
