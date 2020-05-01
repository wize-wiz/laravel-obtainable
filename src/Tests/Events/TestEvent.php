<?php

namespace WizeWiz\Obtainable\Tests\Events;

class TestEvent {

    public $data;

    public function __construct($data) {
        $this->data = $data;
    }

}