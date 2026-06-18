<?php

declare(strict_types=1);

namespace app\exceptions;

/**
 * Class BaseException
 *
 * Base Exception class that all other extensions within Submitty should extend from.
 * This allows us to send additional details to be logged within our ExceptionHandler
 * (@see ExceptionHandler). We should
 */
class BaseException extends \RuntimeException {
    protected $details;
    protected $log_exception = true;
    protected $show_exception_message = false;

    /**
     * BaseException constructor.
     *
     * @param string            $message
     * @param array             $details
     * @param int               $code
     * @param \Exception|null   $previous
     */
    public function __construct($message, $details = [], $code = 0, $previous = null) {
        if (!is_array($details)) {
            $this->details = ["extra_details" => $details];
        }
        else {
            $this->details = $details;
        }
        parent::__construct($message, $code, $previous);
    }

    public function getDetails() {
        return $this->details;
    }

    public function setDisplayMessage($bool) {
        $this->show_exception_message = $bool;
    }

    public function displayMessage() {
        return $this->show_exception_message;
    }

    public function logException() {
        return $this->log_exception;
    }
}
