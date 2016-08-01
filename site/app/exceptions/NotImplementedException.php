<?php

namespace app\exceptions;

class NotImplementedException extends BaseException {
    public function __construct($message = "Function not implemented yet.", $code = 0, $previous = null) {
        parent::__construct($message, array(), $code, $previous);
    }
}