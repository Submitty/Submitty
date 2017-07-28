<?php

namespace app\libraries;

/**
 * Class Logger
 *
 * Static class which we can use to log various parts of the system. Primiarly, we log either errors (to *_error)
 * or access (to *_access) logs which we can use to help debug certain issues people have with the system as well
 * as use for certain effects like monitoring usage for people suspected of cheating or the like. Primarily, we
 * always write to the *_error log for most of the methods here with only one method going to the *_access log.
 *
 * @package app\libraries
 */
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


    /**
     * Set the log path to be used by the logger, but only if the path is a valid one (otherwise ignore)
     * @param string $path
     */
    public static function setLogPath($path) {
        if (is_dir($path)) {
            static::$log_path = $path;
        }
    }

    /**
     * Log a debug message to the logger
     *
     * @param string $message
     */
    public static function debug($message="") {
        Logger::logError(Logger::DEBUG, $message);
    }

    /**
     * Log an info message to the logger
     *
     * @param string $message
     */
    public static function info($message="") {
        Logger::logError(Logger::INFO, $message);
    }

    /**
     * Log a warning message to the logger
     *
     * @param string $message
     */
    public static function warn($message="") {
        Logger::logError(Logger::WARN, $message);
    }

    /**
     * Log an error message to the logger
     *
     * @param string $message
     */
    public static function error($message="") {
        Logger::logError(Logger::ERROR, $message);
    }

    /**
     * Log a fatal error message to the logger
     *
     * @param string $message
     */
    public static function fatal($message="") {
        Logger::logError(Logger::FATAL, $message);
    }

    /**
     * Returns the path that the logger is configured to use. If the logger has not been setup yet, this
     * will return null.
     *
     * @return string
     */
    public static function getLogPath() {
        return self::$log_path;
    }

    /**
     * Writes a message to a logfile (named for current date) assuming that we've defined the $log_path variable
     *
     * We log a message as well as the calling URI if available. It's saved to $log_path/yyyymmdd.txt
     * (yyyy is year, mm is month dd is day) with each log entry seperated by bunch of "=-".
     *
     * @param int $level: message level
     *     0. Debug
     *     1. Info
     *     2. Warn
     *     3. Error
     *     4. Fatal Error
     * @param $message: message to log to the file
     */
    private static function logError($level=0, $message="") {
        if (static::$log_path === null) {
            return;
        }

        $filename = static::getFilename();
        $log_message = static::getTimestamp();
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
        @file_put_contents(FileUtils::joinPaths(static::$log_path, 'site_errors', "{$filename}.log"), $log_message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Internal method that constructs a filename for us to use based on the date so that we get a filename
     * that is YYYYMMDD which we use as a prefix to our _error and _access log files.
     *
     * @return string
     */
    private static function getFilename() {
        FileUtils::createDir(static::$log_path);
        $date = getdate(time());
        return $date['year']. Utils::pad($date['mon']) . Utils::pad($date['mday']);
    }

    /**
     * Internal method that gives us a timestamp that we use as the beginning of any entry into the log files. The
     * timestamp is in the form of HH:mm:SS DD/MM/YYYY.
     * @return string
     */
    private static function getTimestamp() {
        $date = getdate(time());
        $log_message = Utils::pad($date['hours']).":".Utils::pad($date['minutes']).":".Utils::pad($date['seconds']);
        $log_message .= " ";
        $log_message .= Utils::pad($date['mon'])."/".Utils::pad($date['mday'])."/".$date['year'];
        return $log_message;
    }

    /**
     * This writes a one line message to the _access log file which we use to monitor what pages a user goes
     * to (through a central point in the public/index.php file). This logs things in the fashion of:
     *
     * Timestamp | User ID | IP Adress | Action | User Agent
     *
     * where action is defined broadly as the page they're accessing and any other relevant information
     * (so gradeable id for when they're submitting).
     *
     * @param $user_id
     * @param $action
     */
    public static function logAccess($user_id, $action) {
        $filename = static::getFilename();
        $log_message[] = static::getTimestamp();
        $log_message[] = $user_id;
        $log_message[] = $_SERVER['REMOTE_ADDR'];
        $log_message[] = $action;
        //$log_message[] = $_SERVER['REQUEST_URI'];
        $log_message[] = $_SERVER['HTTP_USER_AGENT'];
        $log_message = implode(" | ", $log_message)."\n";
        @file_put_contents(FileUtils::joinPaths(static::$log_path, 'access', "{$filename}.log"), $log_message, FILE_APPEND | LOCK_EX);
    }
}
