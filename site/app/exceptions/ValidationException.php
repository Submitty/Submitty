<?php

namespace app\exceptions;

/**
 * Class ValidationException
 * @package app\exceptions
 *
 * When one or more properties fail validation and these errors must be
 *  individually addressable
 */
class ValidationException extends BaseException {
    /**
     * ValidationException constructor.
     *
     * @param string      $message
     * @param string[]    $details An array of error messages indexed by class property name
     * @param int         $code
     * @param \Exception  $previous
     */
    public function __construct($message, $details, $code = 0, $previous = null) {
        parent::__construct($message, $details , $code, $previous);
    }
}