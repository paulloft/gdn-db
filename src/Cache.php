<?php

namespace Garden\Db;

abstract class Cache
{
    public static $_instance;

    public static function instance(...$params)
    {
        $calledClass = get_called_class();
        if (self::$_instance !== null) {
            self::$_instance = new $calledClass(...$params);
        }

        return self::$_instance;
    }

    /**
     * @param string $key
     * @return mixed
     */
    abstract public static function get(string $key);

    /**
     * @param string $key
     * @param $value
     * @param int $lifetime
     * @return mixed
     */
    abstract public static function set(string $key, $value, $lifetime = null);
}