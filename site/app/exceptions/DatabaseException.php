<?php

namespace app\exceptions;

class DatabaseException extends BaseException{
    public function __construct($message, $query = null, $parameters = array(), $code = 0, $previous = null) {
        $extra = array();
        if ($query !== null) {
            $extra = array("query" => $query, "parameters" => $parameters);
        }
        parent::__construct($message, $extra);
    }
}