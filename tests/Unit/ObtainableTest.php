<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;
use WizeWiz\Obtainable\Obtainer;
use WizeWiz\Obtainable\Tests\TestEnvironment;
use WizeWiz\Obtainable\Tests\Models\Test;

class ObtainableTest extends TestCase {

    use WithoutMiddleware, TestEnvironment;

    protected function ignoreTest() {
        return false;
    }

    /**
     * Test key mapping and reverse check.
     *
     * @throws \WizeWiz\Obtainable\Exceptions\MissingRequiredMappedArguments
     */
    public function testKeyMap() {
        $t = new Test();

        $Obtainer = $t->getObtainer();

        // without id
        $key = $Obtainer->keyMap('key-test');
        $result = $Obtainer->reverseKeyMap($key);
        $this->assertEquals([
            // obtainable key
            'key-test',
            // arguments
            [],
            // mapped key
            'key-testing',
            // cache key
            'test:key-testing'
        ], $result);

        // with id
        $key = $Obtainer->keyMap('key-test', ['id' => 142]);
        $result = $Obtainer->reverseKeyMap($key);
        $this->assertEquals([
            'key-test',
            [
                'id' => 142
            ],
            'key-testing',
            'test:142:key-testing'
        ], $result);


        // without id but arguments
        $key = $Obtainer->keyMap('key-test', ['limit' => 10]);
        $result = $Obtainer->reverseKeyMap($key);
        $this->assertEquals([
            'key-test',
            [
                'limit' => 10
            ],
            'key-testing',
            'test:key-testing:limit=10'
        ], $result);

        // with id and arguments
        $key = $Obtainer->keyMap('key-test', ['id' => 12, 'limit' => 10]);
        $result = $Obtainer->reverseKeyMap($key);
        $this->assertEquals([
            'key-test',
            [
                'id' => 12,
                'limit' => 10
            ],
            'key-testing',
            'test:12:key-testing:limit=10'
        ], $result);

        // without id but arguments
        $key = $Obtainer->keyMap('key-test', ['limit' => 10, 'sort' => 'desc']);
        $result = $Obtainer->reverseKeyMap($key);
        $this->assertEquals([
            'key-test',
            [
                'limit' => 10,
                'sort' => 'desc',
            ],
            'key-testing',
            'test:key-testing:limit=10:sort=desc'
        ], $result);

        // with id and arguments
        $key = $Obtainer->keyMap('key-test-args', ['id' => 26, 'user' => 12, 'sort' => 'desc']);
        $result = $Obtainer->reverseKeyMap($key);
        $this->assertEquals([
            'key-test-args',
            [
                'id' => 26,
                'user' => 12,
                'sort' => 'desc',
            ],
            'key-testing-args:$user:$sort',
            'test:26:key-testing-args:12:desc'
        ], $result);

        // with id and many arguments
        $key = $Obtainer->keyMap('key-test-args', ['id' => 26, 'user' => 12, 'sort' => 'desc', 'limit' => 10, 'active' => true]);
        $result = $Obtainer->reverseKeyMap($key);
        $this->assertEquals([
            'key-test-args',
            [
                'id' => 26,
                'user' => 12,
                'sort' => 'desc',
                'active' => 1,
                'limit' => 10
            ],
            'key-testing-args:$user:$sort',
            'test:26:key-testing-args:12:desc:active=1:limit=10'
        ], $result);
    }

    /**
     * Test core features.
     *
     * @throws \WizeWiz\Obtainable\Exceptions\MissingRequiredMappedArguments
     */
    public function testCore() {
        $t = new Test();
        $key = 'simple-test-mapped';

        $prefix = $t->prefix;
        $Obtainer = $t->getObtainer();
        $prefix = $Obtainer->prefix;

        // ttl set for $key
        $mapped_ttl = 9;
        // expected mapped test key.
        $mapped_key = "st:mapped";
        // expected mapped key as cached key version.
        $cache_mapped_key = "{$prefix}:{$mapped_key}";
        // empty args
        $args = [];

        $tags = $Obtainer->getTags($key);
        $cache_key = $Obtainer->keyMap($key, $args);

        $this->assertEquals($mapped_key, $Obtainer->key_map[$key]);
        $this->assertEquals($cache_mapped_key, $cache_key);
        $this->assertEquals([
            Obtainer::OBTAINER_TAG,
            Obtainer::OBTAINER_PREFIX . ':' . $prefix,
            Obtainer::OBTAINER_PREFIX . ':' . $prefix . ':' .$key
        ], $tags);
        $this->assertEquals($mapped_ttl, $Obtainer->getTtl($key));

        $key = 'testing';
        $args = ['id' => $t->id];
        // test mapped (or cache) key generation.
        $this->assertEquals(
            // should be `test:582:testing`.
            $prefix.Obtainer::KEY_SEPARATOR.$args['id'].Obtainer::KEY_SEPARATOR.$key,
            $Obtainer->keyMap($key, $args)
        );
        // test mapped (or cache) key generation.
        $this->assertEquals(
            // should be `test:582:testing:user:12`.
            $prefix.Obtainer::KEY_SEPARATOR.$args['id'].Obtainer::KEY_SEPARATOR.$key.Obtainer::KEY_SEPARATOR.'user=12',
            $Obtainer->keyMap($key, array_merge($args, ['user' => 12]))
        );
        // test mapped (or cache) key generation
        $this->assertEquals(
        // $args should be key sorted ascending.
        // should be `test:582:testing:sort:desc:user:35`.
            $prefix. Obtainer::KEY_SEPARATOR.
            $args['id'].Obtainer::KEY_SEPARATOR.
            $key.
            Obtainer::KEY_SEPARATOR.'sort=desc'.
            Obtainer::KEY_SEPARATOR.'user=35',
            $Obtainer->keyMap($key, array_merge($args, ['user' => 35, 'sort' => 'desc']))
        );
    }

    public function testSimple() {
        $t = new Test();
        $key = 'simple-test';
        $expected_result = 'testing';
        $args = [
            'id' => $t->id
        ];

        $Obtainer = $t->getObtainer();

        // get tags for specific key
        $tags = $Obtainer->getTags($key);
        // get generated cache-key.
        $cache_key = $Obtainer->keyMap($key, $args);
        // get result for `simple-test`
        $result = $t->obtain($key); // no $args needed, model id should be appended automatically.
        $this->assertEquals($expected_result, $result);
        // check result is cached under given tags.
        $this->assertTrue(Cache::tags($tags)->has($cache_key));
        // cached result should only be available by given tags.
        $this->assertFalse(Cache::has($cache_key));
        // flush `simple-test
        $t->flushObtained($key);
        // should now be flushed
        $this->assertFalse(Cache::tags($tags)->has($cache_key));
    }

    public function testSimpleMapped() {
        $t = new Test();
        $key = 'simple-test-mapped';
        $expected_result = 'testing-mapped';
        $args = [
            'id' => $t->id
        ];

        $Obtainer = $t->getObtainer();

        // get tags for specific key
        $tags = $Obtainer->getTags($key);
        // get generated cache-key.
        $cache_key = $Obtainer->keyMap($key, $args);
        // get result for `simple-test-mapped`
        $result = $t->obtain($key);
        $this->assertEquals($expected_result, $result);
        // check result is cached under given tags.
        $this->assertTrue(Cache::tags($tags)->has($cache_key));
        // cached result should only be available by given tags.
        $this->assertFalse(Cache::has($cache_key));
        // flush `simple-test
        $t->flushObtainable($key);
        // should now be flushed
        $this->assertFalse(Cache::tags($tags)->has($cache_key));
    }

    public function testSimpleMappedArgs() {
        $t = new Test();
        $key = 'simple-test-mapped-args';
        // psuedo value
        $args = ['user' => 3];
        $expected_result = 'testing-mapped-args-'.$args['user'];

        $Obtainer = $t->getObtainer();

        // get tags for specific key
        $tags = $Obtainer->getTags($key);
        // get generated cache-key.
        // this is a direct call to the obtainer class which does not append the model id automatically.
        $cache_key = $Obtainer->keyMap($key, array_merge($args, ['id' => $t->id]));
        // get result for `simple-test-mapped-args`
        $result = $t->obtain($key, $args);
        $this->assertEquals($expected_result, $result);
        // check result is cached under given tags.
        $this->assertTrue(Cache::tags($tags)->has($cache_key));
        // cached result should only be available by given tags.
        $this->assertFalse(Cache::has($cache_key));
        // flush `simple-test-mapped-args`
        $t->flushObtainable($key);
        // should now be flushed
        $this->assertFalse(Cache::tags($tags)->has($cache_key));
    }

    public function testFlush() {
        $t = new Test();
        $Obtainer = $t->getObtainer();

        $keys = ['test-a', 'test-b', 'test-c'];
        $cache_keys = [];
        $args = [
            'id' => $t->id
        ];
        $tags = $Obtainer->getTags($keys[0]);
        $expected_results = ['A', 'B', 'C'];
        $results = [];
        foreach($keys as $key) {
            // this is a direct call to the obtainer class which does not append the model id automatically.
            $cache_keys[] = $Obtainer->keyMap($key, $args);
            $results[] = $t->obtain($key);
        }
        $this->assertEquals($expected_results, $results);
        foreach($keys as $key_index => $key) {
            $this->assertTrue(Cache::tags($Obtainer->getTags($keys[$key_index]))->has($cache_keys[$key_index]));
        }

        // flush only key 0 and 1.
        $t->flushObtainable([$keys[0], $keys[1]]);
        $this->assertFalse(Cache::tags($Obtainer->getTags($keys[0]))->has($cache_keys[0]));
        $this->assertFalse(Cache::tags($Obtainer->getTags($keys[1]))->has($cache_keys[1]));
        // 2 should remain
        $this->assertTrue(Cache::tags($Obtainer->getTags($keys[2]))->has($cache_keys[2]));
        // add key 1 again
        $t->obtain($keys[1]);
        $this->assertTrue(Cache::tags($Obtainer->getTags($keys[1]))->has($cache_keys[1]));
        // now flush all
        $t->flushObtainables();
        foreach($keys as $key_index => $key) {
            $this->assertFalse(Cache::tags($Obtainer->getTags($keys[$key_index]))->has($cache_keys[$key_index]));
        }
    }

    public function testFlushArgs() {
        $t = new Test();
        $Obtainer = $t->getObtainer();

        $key = 'test-d';
        $args = [
            [/* empty */],
            ['user' => 1],
            ['user' => 12],
            ['user' => 23],
            ['user' => 23, 'sort' => 'desc']
        ];

        $tags = $Obtainer->getTags($key);

        $results = [];
        $cached_keys = [];
        foreach($args as $arg) {
            $cached_keys[] = $Obtainer->keyMap($key, array_merge($arg, ['id' => $t->id]));
            $results[] = $t->obtain($key, $arg);
        }
        $result = '[{"id":123},{"user":1,"id":123},{"user":12,"id":123},{"user":23,"id":123},{"user":23,"sort":"desc","id":123}]';

        $this->assertEquals($result, json_encode($results));
        $this->assertTrue(Cache::tags($tags)->has($cached_keys[2]));
        // now we will flush key with $args index 2.
        $t->flushObtained($key, $args[2]);
        $this->assertFalse(Cache::tags($tags)->has($cached_keys[2]));
        unset($args[2]);
        unset($cached_keys[2]);

        // keys 0,1,3,4 should remain.
        foreach($cached_keys as $index => $cached_key) {
            $this->assertTrue(Cache::tags($tags)->has($cached_key));
        }
    }
}