<?php

namespace app\libraries;

use \Exception;

/**
 * Class ServerException
 *
 * Custom exception class that when thrown does not
 * print out stack trace to the user, just this exception message
 */
class ServerException extends Exception {

    /**
     * Construct the exception
     *
     * @param string $message: message to display to user about exception
     * @param Exception $previous: previous exceptions that have been thrown
     */
    public function __construct($message, Exception $previous = null) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Gets the string to output to the end user when viewing this exception
     *
     * @return string
     */
    public function __toString() {
        return "ServerException: {$this->message}\n";
    }
}