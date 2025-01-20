<?php

declare(strict_types=1);

namespace app\exceptions;

class DockerLogParseException extends BaseException {
    /** Create a new exception
     * @param array<string> $extra_details - additional message to be added with the exception
     */
    public function __construct(string $message, array $extra_details = []) {
        parent::__construct($message, $extra_details);
    }
}
