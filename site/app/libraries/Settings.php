<?php

namespace app\libraries;

use \app\exceptions\FileNotFoundException;
use lib\ExceptionHandler;

class Settings {

    public static $debug = false;

    public static $base_url;
    public static $course_code;
    public static $submission_server;

    public static $database_host;
    public static $database_name;
    public static $database_user;
    public static $database_password;

    public static $log_path;
    public static $log_exceptions;

    public static $course_name;
    public static $calculate_diff;
    public static $default_late_days;
    public static $default_late_days_student;
    public static $use_autograder;
    public static $zero_rubric_grades;

    public static function loadFileConfig() {
        $config = array();
        if (!file_exists(__DIR__ . "/../../config/master.php")) {
            throw new FileNotFoundException("Master config could not be found!");
        }
        require_once(__DIR__ . "/../../config/master.php");

        if (isset($_GET['course'])) {
            // don't allow the user entered course to potentially point to a different directory via use of ../
            $_GET['course'] = str_replace("/","_",$_GET['course']);
            $config_file = __DIR__."/../../config/".$_GET['course'].".php";
            if (!file_exists($config_file)) {
                ExceptionHandler::throwException("Settings", new FileNotFoundException("Course config for \"{$_GET['course']}\" could not be found!"));
            }
            require_once($config_file);
        }
        else {
            ExceptionHandler::throwException("Settings", new \RuntimeException("You must have course=#### in the URL bar!"));
        }

        foreach($config as $key => $value) {
            Settings::${$key} = $value;
        }
    }

    public static function loadDBConfig() {
        Database::query("SELECT * FROM config");
        foreach (Database::rows() as $config) {
            $config['config_value'] = Settings::processConfigValue($config['config_value'], $config['config_type']);
            Settings::${$config['config_name']} = $config['config_value'];
        }
    }

    public static function processConfigValue($value, $type) {
        switch ($type) {
            // Integer
            case 1:
                $value = intval($value);
                break;
            // Float/Double
            case 2:
                $value = floatval($value);
                break;
            // Boolean
            case 3:
                $value = (strtolower($value) == "true" || intval($value) == 1);
                break;
            // String
            case 4:
                // no action needed, already a string
                break;
            default:
                ExceptionHandler::throwException("Settings", new \UnexpectedValueException("{$type} is not a valid config type."));
        }
        return $value;
    }
}