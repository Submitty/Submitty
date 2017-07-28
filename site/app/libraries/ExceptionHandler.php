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
     * This is initially set to false to prevent leaking any data when loading the config,
     * but this does mean that by default any exceptions thrown when loading the config
     * will be swallowed.
     *
     * @var bool
     */
    private static $display_exceptions = false;

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
     * then either return the exception message if the flag is set to display exceptions
     * (either via field of BaseException or on private variable $display_exceptions)
     * otherwise just returning a very generic message to the user
     *
     * @param \Exception|\Throwable $exception
     * @return string    A string that either contains a generic message or the actual
     *                   exception message depending on the value of $display_exceptions
     */
    public static function handleException($exception) {
        $display_message = false;
        $is_base_exception = false;
        $log_exception = static::$log_exceptions;
        if (is_a($exception, '\app\exceptions\BaseException')) {
            /** @var BaseException $exception */
            $is_base_exception = true;
            $display_message = $exception->displayMessage();
            $log_exception = $exception->logException();
        }
        
        $trace_string = array();
        foreach ($exception->getTrace() as $elem => $frame) {
            $trace_string[] = sprintf( "#%s %s(%s): %s(%s)",
                             $elem,
                             isset($frame['file']) ? $frame['file'] : 'unknown file',
                             isset($frame['line']) ? $frame['line'] : 'unknown line',
                             (isset($frame['class']))  ? $frame['class'].$frame['type'].$frame['function'] : $frame['function'],
                             static::parseArgs(is_a($exception, '\app\exceptions\AuthenticationException') ? array() : $frame['args']));
        }
        $trace_string = implode("\n", $trace_string);

        $exception_name = get_class($exception);
        $file = $exception->getFile();
        $exception_line = $exception->getLine();
        $line = 1;
        $line_code = "";
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
        $message .= "{$trace_string}\n";

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

        if (static::$display_exceptions) {
            return $message;
        }
        else if ($display_message) {
            return $exception->getMessage();
        }
        else {
            return <<<HTML
An exception was thrown. Please contact an administrator about what you were doing that caused this exception.

HTML;
        }
    }
    
    /**
     * Parse the arguments from the stack trace into type appropriate representation for the stack trace. We
     * may want to look into expanding the $args when they're an Array, but for now, this is probably fine.
     * @codeCoverageIgnore
     *
     * @param mixed $args
     *
     * @return string
     */
    private static function parseArgs($args) {
        $return = "";
        if (isset($args)) {
            $return_args = array();
            foreach ($args as $arg) {
                if (is_string($arg)) {
                    $return_args[] = "'" . $arg . "'";
                }
                elseif (is_array($arg)) {
                    $return_args[] = "Array";
                }
                elseif (is_null($arg)) {
                    $return_args[] = 'NULL';
                }
                elseif (is_bool($arg)) {
                    $return_args[] = ($arg) ? "true" : "false";
                }
                elseif (is_object($arg)) {
                    $return_args[] = get_class($arg);
                }
                elseif (is_resource($arg)) {
                    $return_args[] = get_resource_type($arg);
                }
                else {
                    $return_args[] = $arg;
                }
            }
            $return = implode(", ", $return_args);
        }
        return $return;
    }
}
