<?php

namespace app\models;

use app\database\Database;
use app\exceptions\ConfigException;
use app\exceptions\FileNotFoundException;
use app\libraries\IniParser;

/**
 * Class Config
 *
 * This class handles and contains all of the variables necessary for running
 * the application. These variables are loaded from a combination of files and tables from
 * the database. We also allow for using this to write back to the variables within the database
 * (but not the variables in the files).
 *
 * @since 1.0.0
 */
class Config {

    /**
     * Variable to set the system to debug mode, which allows, among other things
     * easier access to user switching and to always output full exceptions. Never
     * turn on if running server in production environment.
     * @var bool
     */
    public static $debug = true;

    public static $semester;
    public static $course;

    /*** MASTER CONFIG ***/
    public static $base_url;
    public static $site_url;
    public static $hss_path;
    public static $hss_course_path;
    public static $hss_log_path;
    public static $log_exceptions;

    /**
     * Database host for PDO. The user does not need to set this
     * explicitly in the config files, in which case we'll just default
     * to PostgreSQL.
     * @var string
     */
    public static $database_type = "pgsql";

    /**
     * Database host for PDO
     * @var string
     */
    public static $database_host;

    /**
     * Database user for PDO
     * @var string
     */
    public static $database_user;

    /**
     * Database password for PDO
     * @var string
     */
    public static $database_password;

    /*** COURSE SPECIFIC CONFIG ***/
    /**
     * Database name for PDO
     * @var string
     */
    public static $database_name;

    /*** COURSE DATABASE CONFIG ***/

    public static $course_name;
    public static $default_hw_late_days;
    public static $default_student_late_days;
    public static $zero_rubric_grades;
    public static $generate_diff;
    public static $use_autograder;

    private static $database_configs = array();

    /**
     * Singleton static class
     */
    private function __construct() {}
    private function __clone() {}

    /**
     * @param $semester
     * @param $course
     */
    public static function loadCourse($semester, $course) {
        static::$semester = $semester;
        static::$course = $course;

        // Load config details from the master config file
        $master = IniParser::readFile(__DIR__.'/../../config/master.ini');

        static::setConfigValues($master, 'logging_details', array('hss_log_path', 'log_exceptions'));
        static::setConfigValues($master, 'site_details', array('base_url', 'hss_path'));
        static::setConfigValues($master, 'database_details', array('database_host', 'database_user', 'database_password'));

        if (isset($master['site_details']['debug'])) {
            static::$debug = $master['site_details']['debug'];
        }

        if (isset($master['database_details']['database_type'])) {
            static::$database_type = $master['database_details']['database_type'];
        }

        static::$base_url = rtrim(static::$base_url, "/")."/";
        static::$site_url = static::$base_url."index.php?semester=".static::$semester."&course=".static::$course;

        // Check that the paths from the config file are valid
        foreach(array('hss_path', 'hss_log_path') as $path) {
            if (!is_dir(static::$$path)) {
                throw new ConfigException("Invalid path for setting: {$path}");
            }
            static::$$path = rtrim(static::$$path, "/");
        }

        if (!is_dir(implode("/", array(static::$hss_path, "courses", static::$semester))) ||
            !is_dir(implode("/", array(__DIR__, "..", "..", "config", static::$semester)))) {
            throw new ConfigException("Invalid semester: ".static::$semester, true);
        }

        static::$hss_course_path = implode("/", array(static::$hss_path, "courses", static::$semester, static::$course));
        if (!is_dir(static::$hss_course_path)) {
            throw new ConfigException("Invalid course: ".static::$course, true);
        }

        $course_config = implode("/", array(__DIR__, '..', '..', 'config', static::$semester, static::$course.'.ini'));
        $course = IniParser::readFile($course_config);

        static::setConfigValues($course, 'database_details', array('database_name'));
        static::setConfigValues($course, 'course_details', array('course_name',
            'default_hw_late_days', 'default_student_late_days', 'use_autograder',
            'generate_diff', 'zero_rubric_grades'));

        foreach (array('default_hw_late_days', 'default_student_late_days') as $key) {
            static::$$key = intval(static::$$key);
        }

        foreach (array('use_autograder', 'generate_diff', 'zero_rubric_grades') as $key) {
            static::$$key = (static::$$key == true) ? true : false;
        }

        Database::connect(static::$database_host, static::$database_user, static::$database_password,
                          static::$database_name, static::$database_type);
    }

    private static function setConfigValues($config, $section, $keys) {
        if (!isset($config[$section]) || !is_array($config[$section])) {
            throw new ConfigException("Missing config section {$section} in master.ini");
        }

        foreach ($keys as $key) {
            if (!isset($config[$section][$key])) {
                throw new ConfigException("Missing config setting {$section}.{$key} in master.ini");
            }
            static::$$key = $config[$section][$key];
        }
    }

    public static function buildUrl($parts) {
        return static::$site_url."&".implode("&", array_map(function($key) use ($parts) {
            return strval($key)."=".strval($parts[$key]);
        }, array_keys($parts)));
    }
}