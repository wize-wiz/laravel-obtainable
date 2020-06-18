<?php

namespace WizeWiz\Obtainable;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WizeWiz\Obtainable\Exceptions\MissingRequiredMappedArguments;
use WizeWiz\Obtainable\Exceptions\ObtainableClassNotFound;
use WizeWiz\Obtainable\Exceptions\UnknownObtainableMethod;

abstract class Obtainer {

    const KEY_SEPARATOR = ':';

    const OBTAINER_PREFIX = 'obt';
    const OBTAINER_TAG = 'ww'.self::KEY_SEPARATOR.self::OBTAINER_PREFIX;

    public static $namespace;
    public static $models;

    public static $enable_class_cache = false;

    public $prefix = null;
    public $ttl = 3600;
    public $silent = true;
    public $use_cache = true;

    public $key_map = [];
    public $ttl_map = [];
    public $casts = [];

    protected $listeners = [];

    /**
     * Initializ static variables.
     */
    protected static function initialize() {
        $config = config('obtainable');
        foreach(['models', 'namespace'] as $key) {
            static::${$key} = Str::endsWith($config[$key], '\\') ?
                str_replace('\\', '', $config[$key]) :
                $config[$key];
        }
    }

    /**
     * Camel call of any key, e.g. `this-is-an-obtainable` will call thisIsAObtainable.
     *
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws UnknownObtainableMethod
     */
    public function __call($name, $arguments) {
        if(method_exists($this, $name)) {
            $result = call_user_func_array([$this, $name], $arguments);
            return call_user_func_array([$this, 'obtain'], $result);
        }
        throw new UnknownObtainableMethod($name);
    }

    /**
     * Gues the key
     *
     * @param $key
     * @return array
     */
    public function guessKeys($key, array $options) : array {
        $mapped_key = $this->keyMap($key, $options);
        $cache_key = $this->filterObtainableKey($mapped_key, $options);
        return [
            $mapped_key,
            $cache_key
        ];
    }

    /**
     * Create a obtainable instance given by class (model).
     *
     * @param string $class
     * @return Obtainer
     * @throws ObtainableClassNotFound
     */
    public static function create(string $class) {
        if(static::$namespace === null) {
            static::initialize();
        }
        $tags = [static::OBTAINER_TAG, static::OBTAINER_TAG.static::KEY_SEPARATOR.'create'];
        if(static::$enable_class_cache && ($Cache = Cache::tags($tags))->has($class)) {
            return $Cache->get($class);
        }
        // find obtainable
        $obt_class = static::$namespace.str_replace(static::$models, '', $class);
        if(!class_exists($obt_class)) {
            throw new ObtainableClassNotFound($obt_class);
        }
        $instance = new $obt_class;
        if(isset($Cache)) {
            Cache::tags($tags)->put($class, $instance, 3600);
        }
        // create obtainable object, get method, check mapped key and filter key.
        return $instance;
    }

    /**
     * Purge all obtainable entries.
     */
    public static function purge() {
        return
            Cache
                ::tags(self::OBTAINER_TAG)
                ->flush();
    }

    /**
     * Flush all obtainables.
     *
     * @return bool
     */
    public function flushAll() {
        return
            Cache
                ::tags($this->getTagPrefix())
                ->flush();
    }

    /**
     * Flush key.
     *
     * @param string $key
     * @return mixed
     */
    public function flush($keys, array $args = []) {
        if(empty($keys)) {
            return $this->flushAll();
        }
        if(!is_array($keys)) {
            $keys = [$keys];
        }
        $results = [];
        // if we have arguments, only delete specific keys.
        if(!empty($args)) {
            foreach ($keys as $key) {
                $results[] =
                    Cache
                        ::tags($this->getTags($key))
                        ->delete($this->keyMap($key, $args));
            }
        }
        // if we have no arguments, delete all entries belonging to the key's tag.
        else {
            foreach($keys as $key) {
                // remove everything related to the key.
                $results[] =
                    Cache
                        ::tags($this->getTag($key))
                        ->flush();
            }
        }
        return array_sum($results) === count($keys);
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
     * Obtain all keys related to model and/or key.
     * @todo: implement.
     * @param string $keys
     */
    public function obtainKeys(string $key = '') {
        $results = [];
        if(empty($key)) {
            // return all keys related to the model.
        }
//        $results = Cache()

        return collect($results);
    }

    /**
     * Get prefix for tag, this is also the main tag for the obtainable instance.
     *
     * @return string
     */
    protected function getTagPrefix() {
        return static::OBTAINER_PREFIX . static::KEY_SEPARATOR . $this->prefix;
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
            $prefix,
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
        return ($prefix ?: $this->getTagPrefix()) . static::KEY_SEPARATOR . $key;
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
        list($key, $args, $instance) = $callable;
        // get the method name
        $method = Str::camel($key);
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
     * @param array $args
     * @return string
     */
    public function filterObtainableKey(string $key, array $args = []) {
        $args_keys = array_keys($args);
        array_walk_recursive($args_keys, function(&$item) {
            $item = "\${$item}";
        });
        return strtr($key, array_combine($args_keys, $args));
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
    public function keyMap($key, array $args = []) : string {
        $prefix = (!empty($this->prefix) ? ($this->prefix . static::KEY_SEPARATOR) : '');
        $id = '';
        if(isset($args['id'])) {
            $id = $args['id'].static::KEY_SEPARATOR;
            unset($args['id']);
        }
        // do we have a mapped key?
        if($this->keyIsMapped($key)) {
            $key = $this->key_map[$key];
        }

        // we need to replace arg keys with values.
        if(strpos($key, '$', 0)) {
            $matches = [];
            $found = preg_match_all('/\$(.*?):/s', $key.static::KEY_SEPARATOR, $matches);
            if($found) {
                // here we replace all `$` variables with actual values.
                // the key value should match the appending `$`, e.g. `:$user` should have a key `['user' => 1']`.
                $_matches = array_fill_keys($matches[1], null);
                if(!empty(($missing_args = array_diff_key($_matches, $args)))) {
                    // if no key was found, we can assume a required argument is missing.
                    throw new MissingRequiredMappedArguments(implode(',', array_keys($missing_args)));
                }
                // regenerate the key
                $key = $this->filterObtainableKey($key, array_intersect_key($args, $_matches));
                // and filter out possible remaining optional arguments, e.g. `:limit=10`, etc.
                $args = array_diff_key($args, $_matches);
            }
        }
        // add everything else not replaced in the key.
        if(count($args) > 0) {
            // we'll sort variable array keys ascending so keys always generate the same, e.g. if an input was given
            // like `[g => 1, c => 'example', a => 10]`, the key always generates to `:a=10:c=example:g=1`
            ksort($args);
            $concated_keys = http_build_query($args, null, static::KEY_SEPARATOR);
            return $prefix . $id . $key . static::KEY_SEPARATOR . $concated_keys;
        }

        return $prefix . $id . $key;
    }

    /**
     * Reverse the cache key into a solid obtainable key + arguments. In some explicit minor cases, this might fail.
     *
     * @note: only for testing.
     * @param $cache_key
     */
    public function reverseKeyMap(string $cache_key) {
        $args = [];
        $options = [];
        $parts = explode(static::KEY_SEPARATOR, $cache_key);
        $parts_count = count($parts);
        // check integrity, there should always be a prefix and key present.
        if($parts_count < 2) {
            throw new \Exception('Invalid obtainable cache key.');
        }
        // check prefix.
        if(!$parts[0] === $this->prefix) {
            throw new \Exception("Prefix {$parts[0]} does not match {$this->prefix}");
        }

        // we can remove the prefix
        unset($parts[0]);
        $parts_count -= 1;

        $fn_find_key = function($partial) {
            foreach($this->key_map as $key => $mapped_key) {
                if($partial === $mapped_key) {
                    return [$key, $mapped_key];
                }
                if(Str::startsWith($mapped_key, $partial.Obtainer::KEY_SEPARATOR)) {
                    return [$key, $mapped_key];
                }
            }
            return false;
        };
        $fn_find_args = function(array $parts) {
            $args = [];
            foreach($parts as $part) {
                if(strpos($part, '=', 0) !== false) {
                    $e = explode('=', $part);
                    $args[$e[0]] = $e[1];
                }
            }
            return $args;
        };

        switch($parts_count) {
            // only `mapped key` remains
            // no arguments
            // no parameters
            case 1:
                $found = $fn_find_key($parts[1]);
                if($found === false) {
                    // could not reverse key
                    return false;
                }
                return [
                    $found[0],
                    [],
                    $found[1],
                    $cache_key
                ];
                break;
            // `mapped key` could be [1] or [2]
            // index [1] could be `id`
            // if index [1] is key, figure out what [2] is.
            case 2:
            default:
                $original_key = null;
                $args = [];
                $results = [];
                // mostly likely, [2] is the key and [1] is a numeric id.
                if(($found = $fn_find_key($parts[2])) !== false) {
                    $args = ['id' => $parts[1]];
                    $original_key = $found[0];
                    $mapped_key = $this->key_map[$original_key];
                    unset($parts[1]);
                    unset($parts[2]);

                    if(count($parts) === 0) {
                        return [
                            $original_key,
                            $args,
                            $mapped_key,
                            $cache_key
                        ];
                    }
                }
                // [1] is the key and part 2 is probably additional data.
                elseif(($found = $fn_find_key($parts[1])) !== false) {
                    unset($parts[1]);
                    $original_key = $found[0];
                    $args = $fn_find_args($parts);
                    $mapped_key = $this->key_map[$original_key];
                }

                // check if $cache_key has required parameters.
                if(($first_pos = strpos($mapped_key, '$', 0)) !== false) {
                    $e = explode(static::KEY_SEPARATOR, $mapped_key);
                    if($first_pos > 0) {
                        array_shift($e);
                    }

                    foreach($e as $argument) {
                        if(strpos($argument, '=', 0) !== false) {
                            $remaining_arg = explode('=', $argument);
                            $args[$remaining_arg[0]] = $remaining_arg[1];
                            continue;
                        }

                        if(strpos($argument, '$', 0) === 0) {
                            $args[str_replace('$', '', $argument)] = current($parts);
                            unset($parts[key($parts)]);
                            continue;
                        }

                        unset($parts[key($parts)]);
                    }
                }

                if(count($parts) > 0) {
                    foreach($parts as $part) {
                        if(strpos($part, '=', 0) !== false) {
                            $e = explode('=', $part);
                            $args[$e[0]] = $e[1];
                            unset($parts[key($parts)]);
                            continue;
                        }
                    }
                }

                return [
                    $original_key,
                    $args,
                    $mapped_key,
                    $cache_key
                ];

                break;
        }

        // unable to reverse key.
        return false;
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