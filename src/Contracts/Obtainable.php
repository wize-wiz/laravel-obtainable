<?php

namespace WizeWiz\Obtainable\Contracts;

interface Obtainable {

    public function obtain(string $key, array $ids = [], $use_cache = true);
    public static function obtainable(string $key, array $ids = [], $use_cache = true);
    public static function flushObtainable($keys);
    public static function flushObtainables();

}