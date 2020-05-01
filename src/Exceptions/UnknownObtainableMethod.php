<?php

namespace WizeWiz\Obtainable\Exceptions;

use Throwable;

class UnknownObtainableMethod extends \Exception {
    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct(
            'Unknown obtainable method ' . $message,
            $code,
            $previous
        );
    }
}