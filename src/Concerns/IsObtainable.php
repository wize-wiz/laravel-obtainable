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
     * Return obtainer object.
     * @todo: possibility to use $model->obtain->allMessages() instead of obtain('all-messages'). Only condition is that
     *          all obtainable methods need to be protected in order for the magic __call to intercept the method calls.
     * @return Obtainer
     */
    public function getObtainAttribute() {
        return static::getObtainer();
    }

    /**
     * Obtain data.
     *
     * @param string $key
     * @param array $options
     * @param bool $use_cache
     * @return \Illuminate\Contracts\Cache\Repository|mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function obtain(string $key, array $options = [], $use_cache = true) {
        $options['id'] = $this->id;
        return static::obtainable($key, $options, $use_cache, $this);
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
        list($mapped_key, $cache_key) = $obtainable->guessKeys($key, $options);

        if( ($Cache = cache()->tags($obtainable->getTags($key)))->has($cache_key) ) {
            return $Cache->get($cache_key);
        }
        return $obtainable->obtain([$key, $options, $obtainable_instance ?: static::class], $key, $cache_key, $use_cache);
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