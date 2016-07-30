<?php

namespace app\libraries;

use app\exceptions\BaseException;

/**
 * Class ExceptionHandler
 *
 * Use this when want to throw an exception, but not necessarily
 * show the exact exception to the user (e.g. throwing database exceptions).
 */
class ExceptionHandler {

    /**
     * Should we log any of the exceptions that the application hit?
     * @var bool
     */
    private static $log_exceptions = false;

    /**
     * Should we display the actual exception to the user or just a generic message?
     * This should always be initially true in case we hit an exception in our initial
     * setup routines
     *
     * @var bool
     */
    private static $display_exceptions = true;

    /**
     * This is a static class so it should never be instaniated or copied anywhere
     */
    private function __construct() { }
    private function __clone() { }

    /**
     * @param bool $boolean True/False to control whether we log/not log exceptions
     */
    public static function setLogExceptions($boolean) {
        static::$log_exceptions = $boolean;
    }

    /**
     * @param bool $boolean True/False to control whether we show/not show exceptions
     */
    public static function setDisplayExceptions($boolean) {
        static::$display_exceptions = $boolean;
    }

    /**
     * Takes in a Throwable (Exception or Error), and then we will log the details
     * of the message (assuming the appropriate flag is set in Config) and
     * then either return the exception message if throwable is of type BaseException
     * and the flag is set for it, otherwise just return a very generic message to
     * the user
     *
     * @param \Exception $exception
     * @return string    A string that either contains a generic message or the actual
     *                   exception message depending on the value of $display_exceptions
     */
    public static function throwException(\Exception $exception) {
        $display_message = false;
        $is_base_exception = false;
        $log_exception = static::$log_exceptions;
        if (is_a($exception, '\app\exceptions\BaseException')) {
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

        $message = "{$exception_name} (Code: {$exception->getCode()}) thrown in {$file} (Line {$exception_line}) by:\n";
        $message .= "{$line_code}\n\nMessage:\n{$exception->getMessage()}\n\nStrack Trace:\n";
        $message .= "{$exception->getTraceAsString()}\n";

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

        if (static::$display_exceptions || $display_message) {
            return $message;
        }
        else {
            return <<<HTML
An exception was thrown. Please contact an administrator about what<br />
you were doing that caused this exception.

HTML;
        }
    }
}