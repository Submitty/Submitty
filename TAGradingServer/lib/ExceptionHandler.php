<?php

namespace lib;

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
     * Should we log exceptions?
     *
     * @var bool
     */
    public static $logExceptions = false;

    /**
     * Are we in debug mode and should show actual exceptions instead of the generic one?
     *
     * @var bool
     */
    public static $debug = false;

    /**
     * This is a static class so it should never be instaniated or copied anywhere
     */
    private function __construct() { }
    private function __clone() { }

    /**
     * Takes in an exception, logs the details of it (if $logExceptions is true),
     * then either throws the exception if we're in debug mode or throws a generic
     * ServerException burying the original one
     *
     * @param string $class
     * @param \Exception $exception
     * @param array $extra
     *
     * @throws \Exception|ServerException
     */
    public static function throwException($class, $exception, $extra = array()) {
        $exceptionName = get_class($exception);
        $message = "{$class} threw {$exceptionName}\n";
        $trace_string = array();
        foreach ($exception->getTrace() as $elem => $frame) {
            if (is_a($exception, '\app\exceptions\AuthenticationException') && $frame['function'] == "authenticate") {
                if (isset($frame['args'][1])) {
                    $frame['args'][1] = "****";
                }
            }
            $trace_string[] = sprintf( "#%s %s(%s): %s",
                $elem,
                isset($frame['file']) ? $frame['file'] : 'unknown file',
                isset($frame['line']) ? $frame['line'] : 'unknown line',
                (isset($frame['class']))  ? $frame['class'].$frame['type'].$frame['function'] : $frame['function']);
        }
        $trace_string = implode("\n", $trace_string);

        $message .= "Message:\n{$exception->getMessage()}\nStrack Trace:\n{$trace_string}\n";
        if (count($extra) > 0) {
            $message .= "Extra Details:\n";
            foreach ($extra as $key => $value) {
                $message .= "\t" . $key . ":";
                if (is_array($value)) {
                    $message .= "\n";
                    foreach ($value as $kk => $vv) {
                        $message .= "\t\t" . $vv . "\n";
                    }
                } else {
                    $message .= " " . $value . "\n";
                }
            }
        }
        if (ExceptionHandler::$logExceptions) {
            Logger::fatal($message);
        }

        if (ExceptionHandler::$debug) {
            throw $exception;
        }
        else {
            throw new ServerException("An unexpected exception was encountered. Please report this to an administrator.");
        }
    }
}