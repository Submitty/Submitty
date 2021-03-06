<?php

declare(strict_types=1);

namespace app\exceptions;

class NotebookException extends BaseException {

    public function __construct($message, $code = 0, $previous = null) {
        parent::__construct($message, [], $code, $previous);
    }
}
