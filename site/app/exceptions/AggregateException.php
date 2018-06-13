<?php

namespace app\exceptions;

/**
 * Class AggregateException
 * @package app\exceptions
 *
 * When multiple separate errors must be communicated through one exception
 */
class AggregateException extends BaseException {
    /**
     * AggregateException constructor.
     *
     * @param string      $message
     * @param string[]    $details
     * @param int         $code
     * @param \Exception  $previous
     */
    public function __construct($message, $details, $code = 0, $previous = null) {
        parent::__construct("Aggregate Exception: " . $message, $details , $code, $previous);
    }
}