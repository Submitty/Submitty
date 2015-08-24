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
     * Should we log exceptions
     * @var bool
     */
    public static $logExceptions = false;

    /**
     * Are we in debug mode?
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
        $message .= "Message:\n{$exception->getMessage()}\nStrack Trace:\n{$exception->getTraceAsString()}\n";
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