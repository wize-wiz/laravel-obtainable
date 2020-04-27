<?php

namespace WizeWiz\Obtainable;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

abstract class Obtainer {

    const OBTAINER_PREFIX = 'obt';

    public $prefix = null;
    public $ttl = 3600;
    public $silent = false;
    public $use_cache = true;

    protected $key_map = [];
    protected $ttl_map = [];
    protected $casts = [];

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call($name, $arguments) {
        if(method_exists($this, $name)) {
            $result = call_user_func_array([$this, $name], $arguments);
            return call_user_func_array([$this, 'obtain'], $result);
        }
        throw new \Exception('unknown obtainable method ' . $name);
    }

    /**
     * Create a obtainable instance given by class (model).
     *
     * @param string $class
     * @return mixed
     */
    public static function create(string $class) {
        // find obtainable
        $obt_class = 'App\Obtainables\\'.last(explode('\\', $class));
        if(!class_exists($obt_class)) {
            throw new Exception("obtainable class {$obt_class} could not be found.");
        }
        // create obtainable object, get method, check mapped key and filter key.
        return new $obt_class;
    }

    /**
     * Flush key.
     *
     * @param string $key
     * @return mixed
     */
    public function flush(array $keys) {
        if(empty($keys)) {
            return $this->flushAll();
        }
        $results = [];
        foreach($keys as $key) {
            $results[] = Cache::tags($this->getTag($key))->flush();
        }
        return array_sum($results) === count($keys);
    }

    /**
     * @return bool
     */
    public function flushAll() {
        return Cache::tags($this->getTagPrefix())->flush();
    }

    /**
     * Use cache.
     * @param bool $use_cache
     * @return $this
     */
    public function useCache($use_cache = true) {
        $this->use_cache = $use_cache;
        return $this;
    }

    /**
     * Obtain key/value.
     *
     * @param array $callable
     * @param string $key
     * @param string $cache_key
     * @param bool $use_cache
     * @return \Illuminate\Contracts\Cache\Repository|mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function obtain(array $callable, string $key, string $cache_key, bool $use_cache = true) {
        if($use_cache === false) {
            return $this->executeCallable($callable);
        }
        $Cache = Cache::tags($this->getTags($key));
        if($Cache->has($cache_key)) {
            return $this->processResults($key, $Cache->get($cache_key));
        }
        $result = $this->executeCallable($callable);
        $Cache->put($cache_key, $result, $this->getTtl($key));
        return $this->processResults($key, $result);
    }

    /**
     * Get prefix for tag, this is also the main tag for the obtainable instance.
     *
     * @return string
     */
    protected function getTagPrefix() {
        return static::OBTAINER_PREFIX.":{$this->prefix}";
    }

    /**
     * Get tag version from given key.
     *
     * @param string $key
     * @return array
     */
    public function getTags(string $key) : array {
        $prefix = $this->getTagPrefix();
        return [
            "{$prefix}",
            $this->getTag($key, $prefix)
        ];
    }

    /**
     * @param $key
     * @return string
     */
    public function getTag($key, $prefix = null) {
        return ($prefix ?: $this->getTagPrefix()) . ":{$key}";
    }

    /**
     * Process the results.
     * @param string $key
     * @param $results
     */
    protected function processResults(string $key, $results) {
        if(!isset($this->casts[$key])) {
            return $results;
        }
        switch(($cast = $this->casts[$key])) {
            case 'integer':
            case 'int':
            case 'string':
            case 'array':
            case 'object':
            case 'null':
            case 'boolean':
            case 'bool':
                $casted = $results;
                if(settype($casted, $cast)) {
                    return $casted;
                }
                break;
            case 'datetime':
                if(is_numeric($results)) {
                    return Carbon::createFromTimestamp($results);
                }
                break;
        }
        return $results;
    }

    /**
     * Execute obtainable callable array.
     *
     * @param array $callable
     * @return mixed
     * @throws \Exception
     */
    protected function executeCallable(array $callable) {
        list($method, $args) = $callable;
        $closure = $this->{$method}();
        if(is_callable($closure)) {
            return $closure($args);
        }
        throw new \Exception(static::class . '@' . " {$method} should return a callable.");
    }

    /**
     * Filter obtainable key to generated the possible cached key.
     *
     * @param string $key
     * @param array $ids
     * @return string
     */
    public function filterObtainableKey(string $key, array $ids = []) {
        $ids_keys = array_keys($ids);
        array_walk_recursive($ids_keys, function(&$item) {
            $item = "\${$item}";
        });
        return strtr($key, array_combine($ids_keys, $ids));
    }

    /**
     * If key is mapped.
     *
     * @param $key
     * @return bool
     */
    public function keyIsMapped($key) : bool {
        return array_key_exists($key, $this->key_map);
    }

    /**
     * Generate a cached keyed version.
     *
     * @param $key
     * @return string
     */
    public function keyMap($key) : string {
        return (!empty($this->prefix) ? ($this->prefix . ':') : ''). $this->key_map[$key];
    }

    /**
     * Get TTL.
     *
     * @param $key
     * @return int
     */
    public function getTtl($key) : int {
        return array_key_exists($key, $this->ttl_map) ?
            // if specified,
            $this->ttl_map[$key] :
            // default.
            $this->ttl;
    }
}