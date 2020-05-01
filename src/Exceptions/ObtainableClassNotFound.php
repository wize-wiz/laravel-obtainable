<?php

namespace WizeWiz\Obtainable\Exceptions;

use Throwable;

class ObtainableClassNotFound extends \Exception {
    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct(
            'Obtainable class does not exist: ' . $message,
            $code,
            $previous
        );
    }
}