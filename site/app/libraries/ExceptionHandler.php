<?php

namespace app\libraries;

use app\exceptions\BaseException;
use app\models\Config;

/**
 * Class ExceptionHandler
 *
 * Use this when want to throw an exception, but not necessarily
 * show the exact exception to the user (e.g. throwing database exceptions).
 *
 * @package lib
 */
class ExceptionHandler {

    /**
     * This is a static class so it should never be instaniated or copied anywhere
     */
    private function __construct() { }
    private function __clone() { }

    /**
     * Takes in a Throwable (Exception or Error), and then we will log the details
     * of the message (assuming the appropriate flag is set in Config) and
     * then either return the exception message if throwable is of type BaseException
     * and the flag is set for it, otherwise just return a very generic message to
     * the user
     *
     * @param \Throwable $exception
     *
     * @return string
     */
    public static function throwException($exception) {
        $display_message = false;
        $is_base_exception = false;
        $log_exception = Config::$log_exceptions;
        if (is_a($exception, 'BaseException')) {
            /** @var BaseException $exception */
            $is_base_exception = true;
            $display_message = $exception->displayMessage();
            $log_exception = $exception->logException();
        }

        $exception_name = get_class($exception);
        $file = $exception->getFile();
        $exception_line = $exception->getLine();
        $line = 1;
        $line_code = "";
        // TODO: Get the actual line
        $fh = fopen($file, 'r');
        while (($buffer = fgets($fh)) !== FALSE) {
            if ($line == $exception_line) {
                $line_code = $buffer;
                break;
            }
            $line++;
        }
        $message = "{$exception_name} thrown in {$file} ({$exception_line}) by:\n{$line_code}\n\n";
        $message .= "Message:\n{$exception->getMessage()}\nStrack Trace:\n{$exception->getTraceAsString()}\n";

        if ($is_base_exception) {
            /** @type BaseException $exception */
            $extra = $exception->getDetails();
            if(count($extra) > 0) {
                $message .= "Extra Details:\n";
                foreach ($extra as $key => $value) {
                    $message .= "\t" . $key . ":";
                    if(is_array($value)) {
                        $message .= "\n";
                        foreach ($value as $kk => $vv) {
                            $message .= "\t\t" . $vv . "\n";
                        }
                    } else {
                        $message .= " " . $value . "\n";
                    }
                }
            }
        }

        if ($log_exception) {
            Logger::fatal($message);
        }

        if (Config::$debug || $display_message) {
            return $message;
        }
        else {
            return "An exception was thrown. Please contact an administrator about what
            you were doing that caused this exception.";
        }
    }
}