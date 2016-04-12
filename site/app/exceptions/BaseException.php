<?php

namespace app\exceptions;

class BaseException extends \RuntimeException{
    protected $details;
    protected $log_exception = true;
    protected $show_exception_message = false;

    public function __construct($message, $details = array(), $code = 0, $previous = null) {
        if (!is_array($details)) {
            $this->details = array("extra_details" => $details);
        }
        else {
            $this->details = $details;
        }
        parent::__construct($message, $code, $previous);
    }

    public function getDetails() {
        return $this->details;
    }

    public function displayMessage() {
        return $this->show_exception_message;
    }

    public function logException() {
        return $this->log_exception;
    }
}