<?php declare(strict_types = 1);

namespace app\exceptions;

class FileNotFoundException extends IOException {
    public function __construct($message = null, $path = null, $code = 0, $previous = null) {
        if ($message === null) {
            if ($path === null) {
                $message = 'File could not be found';
            }
            else {
                $message = "File '{$path}' could not be found";
            }
        }
        parent::__construct($message, ['path' => $path], $code, $previous);
    }
}
