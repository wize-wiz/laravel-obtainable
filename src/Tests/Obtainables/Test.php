<?php

namespace WizeWiz\Obtainable\Tests\Obtainables;

use WizeWiz\Obtainable\Obtainer;

class Test extends Obtainer {

    public $prefix = 'test';
    public $ttl = 3; // all tests will only last 6 seconds.

    public $key_map = [
        'key-test' => 'key-testing',
        'key-test-args' => 'key-testing-args:$user:$sort',
        'simple-test-mapped' => 'st:mapped',
        'simple-test-mapped-args' => 'st:mapped'
    ];

    public $ttl_map = [
        'simple-test-mapped' => 9,
        'simple-test-mapped-args' => 6
    ];

    public $casts = [
        'type-test' => 'integer'
    ];

    public function simpleTest() {
        return function() {
            return 'testing';
        };
    }

    public function simpleTestMapped() {
        return function() {
            return 'testing-mapped';
        };
    }

    public function simpleTestMappedArgs() {
        return function($options) {
            return 'testing-mapped-args-' . $options['user'];
        };
    }

    public function testA() {
        return function() {
            return 'A';
        };
    }

    public function testB() {
        return function() {
            return 'B';
        };
    }

    public function testC() {
        return function() {
            return 'C';
        };
    }

    public function testD() {
        return function($options) {
            return $options;
        };
    }

    public function typeTest() {
        return function() {
            // should be converted to integer
            return 1;
        };
    }

}