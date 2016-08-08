<?php

namespace app\libraries;

class Logger {

    /**
     * Log levels for the logger
     */
    const DEBUG = 0;
    const INFO = 1;
    const WARN = 2;
    const ERROR = 3;
    const FATAL = 4;

    private static $log_path = null;

    /**
     * Don't allow usage of this class outside a static context
     */
    private function __construct() { }
    private function __clone() { }


    public static function setLogPath($path) {
        static::$log_path = $path;
    }

    /**
     * Log a debug message to the logger
     *
     * @param string $message
     */
    public static function debug($message="") {
        Logger::log(Logger::DEBUG, $message);
    }

    /**
     * Log an info message to the logger
     *
     * @param string $message
     */
    public static function info($message="") {
        Logger::log(Logger::INFO, $message);
    }

    /**
     * Log a warning message to the logger
     *
     * @param string $message
     */
    public static function warn($message="") {
        Logger::log(Logger::WARN, $message);
    }

    /**
     * Log an error message to the logger
     *
     * @param string $message
     */
    public static function error($message="") {
        Logger::log(Logger::ERROR, $message);
    }

    /**
     * Log a fatal error message to the logger
     *
     * @param string $message
     */
    public static function fatal($message="") {
        Logger::log(Logger::FATAL, $message);
    }

    /**
     * Writes a message to a logfile (named for current date)
     * assuming that we've defined the $log_path variable
     *
     * We log a message as well as the calling URI if available.
     * It's saved to $log_path/yyyymmdd.txt (yyyy is year, mm is month
     * dd is day) with each log seperated by bunch of "=-".
     *
     * @param int $level: message level
     *     0. Debug
     *     1. Info
     *     2. Warn
     *     3. Error
     *     4. Fatal Error
     * @param $message: message to log to the file
     */
    private static function log($level=0, $message="") {
        if (static::$log_path === null) {
            return;
        }
        
        date_default_timezone_set("America/New_York");

        FileUtils::createDir(static::$log_path);

        $date = getdate(time());
        $filename = $date['year']. Utils::pad($date['mon']) . Utils::pad($date['mday']);

        $log_message = Utils::pad($date['mday']) ."/". Utils::pad($date['mon']) ."/".$date['year'];
        $log_message .= " ";
        $log_message .= Utils::pad($date['hours']) .":". Utils::pad($date['minutes']) .":";
        $log_message .= Utils::pad($date['seconds']);
        $log_message .= " - ";
        switch($level) {
            case 0:
                $log_message .= "DEBUG";
                break;
            case 1:
                $log_message .= "INFO";
                break;
            case 2:
                $log_message .= "WARN";
                break;
            case 3:
                $log_message .= "ERROR";
                break;
            case 4:
                $log_message .= "FATAL ERROR";
                break;
        }

        $log_message .= "\n".$message."\n";
        if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
            $log_message .= 'URL: http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://';
            $log_message .= "{$_SERVER['HTTP_HOST']}/{$_SERVER['REQUEST_URI']}\n";
        }
        $log_message .= str_repeat("=-", 30)."="."\n";

        // Appends to the file using a locking mechanism, and supressing any potential error from this
        if (file_put_contents(static::$log_path."/".$filename.".txt", $log_message, FILE_APPEND | LOCK_EX) === false) {
            print "failure to log error";
        }
    }

    public static function getLogPath() {
        return self::$log_path;
    }
}