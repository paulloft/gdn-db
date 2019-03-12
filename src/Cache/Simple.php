<?php

namespace Garden\Db\Cache;

use Garden\Db\Cache;

/**
 * Simple realization of cache interface
 * @package Garden\Db\Cache
 */
class Simple extends Cache
{
    private static $cachedValues = [];

    public static function get(string $key)
    {
        return self::$cachedValues[$key] ?? null;
    }

    public static function set(string $key, $value, $lifetime = null)
    {
        self::$cachedValues[$key] = $value;
    }
}