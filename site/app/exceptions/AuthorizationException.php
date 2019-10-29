<?php declare(strict_types = 1);

namespace app\exceptions;

class AuthorizationException extends BaseException {

    /**
     * AuthorizationException constructor.
     *
     * @param string      $message
     * @param int         $code
     * @param \Exception  $previous
     */
    public function __construct(string $message, $code = 0, $previous = null) {
        parent::__construct($message, [], $code, $previous);
    }
}
