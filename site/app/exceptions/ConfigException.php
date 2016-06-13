<?php

namespace app\exceptions;

class ConfigException extends BaseException {

    /**
     * ConfigException constructor
     *
     * Exceptions that come from the Config model should go through this class
     * @see \app\models\Config
     *
     * @param string $message
     */
    public function __construct($message) {
        parent::__construct($message);
    }
}