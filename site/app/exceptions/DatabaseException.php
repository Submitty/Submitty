<?php

declare(strict_types=1);

namespace app\exceptions;

class DatabaseException extends BaseException {
    public function __construct($message, $query = null, $parameters = [], $code = 0, $previous = null) {
        $extra = [];
        if ($query !== null) {
            $extra = ["query" => $query, "parameters" => $parameters];
        }
        parent::__construct($message, $extra, $code, $previous);
    }
}
