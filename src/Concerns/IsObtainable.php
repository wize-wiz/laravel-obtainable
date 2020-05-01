<?php

namespace WizeWiz\Obtainable\Concerns;

use Illuminate\Support\Collection;
use WizeWiz\Obtainable\Obtainer;
use Illuminate\Support\Str;

trait IsObtainable {

    public static $OBTAINER_INSTANCE;

    /**
     * Return an obtainer instance.
     *
     * @return Obtainer
     */
    public static function getObtainer() {
        return static::$OBTAINER_INSTANCE === null ?
            (static::$OBTAINER_INSTANCE = Obtainer::create(static::class)) :
            static::$OBTAINER_INSTANCE;
    }

    /**
     * Obtain data.
     *
     * @param string $key
     * @param array $args
     * @param bool $use_cache
     * @return \Illuminate\Contracts\Cache\Repository|mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function obtain(string $key, array $args = [], $use_cache = true) {
        $args['id'] = $this->id;
        return static::obtainable($key, $args, $use_cache, $this);
    }

    /**
     * Obtain all cached keys related to given $key
     *
     * @param string $key
     * @return Collection
     */
    public function obtainKeys(string $key = '') : Collection {
        $obtainable = static::getObtainer();
        return $obtainable->obtainKeys($key);
    }

    /**
     * Obtain data statically.
     *
     * @param string $key
     * @param array $options
     * @param bool $use_cache
     * @param null $obtainable_instance
     * @return \Illuminate\Contracts\Cache\Repository|mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function obtainable(string $key, array $options = [], $use_cache = true, $obtainable_instance = null) {
        $obtainable = static::getObtainer();
        // find obtainable
        $method = Str::camel($key);
        // @todo: this should move to Obtainable.
        $mapped_key = $obtainable->keyMap($key, $options);
        $cache_key = $obtainable->filterObtainableKey($mapped_key, $options);
        // ---
        // @todo: this call should be simpler.
        return $obtainable->obtain([$method, $options, $obtainable_instance ?: static::class], $key, $cache_key, $use_cache);
    }

    /**
     * Remove specific keys from cache.
     *
     * @param $keys
     * @param array $args
     * @return bool|mixed|null
     * @throws \Exception
     */
    public function flushObtained($keys, array $args = []) {
        $args['id'] = $this->id;
        return static::flushObtainable($keys, $args);
    }

    /**
     * Flush obtainable by given keys.
     *
     * @param string|array $key
     * @throws \Exception
     */
    public static function flushObtainable($keys, array $args = []) {
        if(!is_array($keys)) {
            $keys = [$keys];
        }
        // find obtainable
        $obtainable = Obtainer::create(static::class);
        return $obtainable->flush($keys, $args);
    }

    /**
     * Flush all by obtainers main tag.
     *
     * @return mixed
     */
    public static function flushObtainables() {
        // find obtainable
        $obtainable = static::getObtainer();
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