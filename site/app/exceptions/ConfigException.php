<?php

namespace app\exceptions;

class ConfigException extends BaseException {

    public function __construct($message, $show_message = false) {
        $this->show_exception_message = $show_message;
        $this->log_exception = !$show_message;
        parent::__construct($message);
    }
}