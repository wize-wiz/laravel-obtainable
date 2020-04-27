<?php

namespace WizeWiz\Obtainable\Concerns;

use WizeWiz\Obtainable\Obtainer;
use Illuminate\Support\Str;

trait IsObtainable {

    public function obtain(string $key, array $ids = [], $use_cache = true) {
        $ids['id'] = $this->id;
        return static::obtainable($key, $ids, $use_cache);
    }

    public static function obtainable(string $key, array $ids = [], $use_cache = true) {
        $obtainable = Obtainer::create(static::class);
        // find obtainable
        $method = Str::camel($key);
        $mapped_key = $obtainable->keyIsMapped($key) ? $obtainable->keyMap($key) : $key;
        $cache_key = $obtainable->filterObtainableKey($mapped_key, $ids);
        return $obtainable->obtain([$method, $ids], $key, $cache_key, $use_cache);
    }

    /**
     * Flush obtainable by given keys.
     *
     * @param string|array $key
     * @throws \Exception
     */
    public static function flushObtainable($keys) {
        if(!is_array($keys)) {
            $keys = [$keys];
        }
        // find obtainable
        $obtainable = Obtainer::create(static::class);
        return $obtainable->flush($keys);
    }

    /**
     * Flush all by obtainers main tag.
     *
     * @return mixed
     */
    public static function flushObtainables() {
        // find obtainable
        $obtainable = Obtainer::create(static::class);
        return $obtainable->flushAll();
    }

    /**
     * Purge all obtainables from cache.
     *
     * @throws \Exception
     */
    public static function purgeObtainables() {
        if (property_exists(static::class, 'obtainables') === false) {
            return;
        }
        // we'll just call cache once ;)
        $Cache = cache();
        foreach (static::$obtainables as $obtainable) {
            $Cache->forget($obtainable);
        }
    }
}