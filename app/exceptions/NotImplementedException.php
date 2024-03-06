<?php

declare(strict_types=1);

namespace app\exceptions;

class NotImplementedException extends BaseException {
    public function __construct($message = "This is not implemented yet.", $code = 0, $previous = null) {
        parent::__construct($message, [], $code, $previous);
    }
}
