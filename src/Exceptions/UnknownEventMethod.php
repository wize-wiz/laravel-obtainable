<?php

namespace WizeWiz\Obtainable\Exceptions;

use Throwable;

class UnknownEventMethod extends \Exception {
    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct(
            'Unknown event method for ' . $message,
            $code,
            $previous
        );
    }
}