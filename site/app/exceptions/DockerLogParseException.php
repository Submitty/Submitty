<?php

declare(strict_types=1);

namespace app\exceptions;

class DockerLogParseException extends BaseException {
    public function __construct(string $message, array $extra_details = []) DockerLogParseException {
        parent::__construct($message, $extra_details );
    }
}
