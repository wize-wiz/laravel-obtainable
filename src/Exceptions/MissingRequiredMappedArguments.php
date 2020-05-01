<?php

namespace WizeWiz\Obtainable\Exceptions;

use Throwable;

class MissingRequiredMappedArguments extends \Exception {
    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct(
            'Key requires more arguments, missing: ' . $message,
            $code,
            $previous
        );
    }
}