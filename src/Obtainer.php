<?php

namespace WizeWiz\Obtainable;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

abstract class Obtainer {

    const OBTAINER_PREFIX = 'obt';
    const OBTAINER_TAG = 'ww:obt';

    public static $namespace;
    public static $models;

    public $prefix = null;
    public $ttl = 3600;
    public $silent = true;
    public $use_cache = true;

    protected $key_map = [];
    protected $ttl_map = [];
    protected $casts = [];

    public function __construct() {
        if(static::$namespace === null) {
            static::initialize();
        }
    }

    /**
     * Initializ static variables.
     */
    protected static function initialize() {
        $config = config('obtainable');
        static::$models = Str::endsWith($config['models'], '\\') ? str_replace('\\', '', $config['models']) : $config['models'];
        static::$namespace = Str::endsWith($config['namespace'], '\\') ? str_replace('\\', '', $config['namespace']) : $config['namespace'];
    }

    /**
     * Camel call of any key, e.g. `this-is-an-obtainable` will call thisIsAObtainable.
     *
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
        throw new \Exception('Unknown obtainable method ' . $name);
    }

    /**
     * Create a obtainable instance given by class (model).
     *
     * @param string $class
     * @return Obtainer
     */
    public static function create(string $class) {
        if(static::$namespace === null) {
            static::initialize();
        }
        // find obtainable
        $obt_class = static::$namespace.str_replace(static::$models, '', $class);
        if(!class_exists($obt_class)) {
            throw new \Exception("obtainable class {$obt_class} could not be found.");
        }
        // create obtainable object, get method, check mapped key and filter key.
        return new $obt_class;
    }

    /**
     * Purge all obtainable entries.
     */
    public static function purge() {
        return Cache::tags(self::OBTAINER_TAG)->flush();
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
     * Flush all obtainables.
     *
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
            // global obtainer tag
            static::OBTAINER_TAG,
            // obtainer specific tag
            "{$prefix}",
            // key specific tag
            $this->getTag($key, $prefix)
        ];
    }

    /**
     * Get the tag attached for a key.
     *
     * @param $key
     * @return string
     */
    public function getTag($key, $prefix = null) {
        return ($prefix ?: $this->getTagPrefix()) . ":{$key}";
    }

    /**
     * Process the results.
     *
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
        list($method, $args, $instance) = $callable;
        $closure = $this->{$method}();
        if(is_callable($closure)) {
            if(is_object($instance)) {
                $closure = $closure->bindTo($instance);
            }
            return $closure($args);
        }
        throw new \Exception(static::class . '@' . " {$method} should return a callable.");
    }

    /**
     * Filter obtainable key to generated the possible cached key.
     *
     * @param string $key
     * @param array $options
     * @return string
     */
    public function filterObtainableKey(string $key, array $options = []) {
        $options_keys = array_keys($options);
        array_walk_recursive($options_keys, function(&$item) {
            $item = "\${$item}";
        });
        return strtr($key, array_combine($options_keys, $options));
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
    public function keyMap($key, array $args) : string {
        $prefix = (!empty($this->prefix) ? ($this->prefix . ':') : '');
        $mapped_key = $this->keyIsMapped($key) ?
            $this->key_map($key) :
            $key;
        return $prefix . $mapped_key;
    }

    /**
     * Get TTL.
     *
     * @param $key
     * @return int Time to live in seconds.
     */
    public function getTtl($key) : int {
        return array_key_exists($key, $this->ttl_map) ?
            // if specified,
            $this->ttl_map[$key] :
            // default.
            $this->ttl;
    }
}