<?php

namespace app\models;

use app\database\Database;
use app\exceptions\ConfigException;
use app\exceptions\FileNotFoundException;

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
        $config = array();
        $config_variables = array('hss_log_path', 'log_exceptions', 'base_url', 'hss_path',
            'database_host', 'database_user', 'database_password');
        if (!file_exists(__DIR__.'/../../config/master.php')) {
            throw new FileNotFoundException("Missing master config file");
        }
        require_once(__DIR__.'/../../config/master.php');
        foreach ($config_variables as $var) {
            if (!isset($config[$var])) {
                throw new ConfigException("Missing master config setting: {$var}");
            }
            static::$$var = $config[$var];
        }

        if (isset($config['debug'])) {
            static::$debug = $config['debug'];
        }

        if (isset($config['database_type'])) {
            static::$database_type = $config['database_type'];
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

        static::$hss_course_path = implode("/", array(static::$hss_path, "courses",
            static::$semester, static::$course));
        $course_config = implode("/", array(__DIR__, '..', '..', 'config', static::$semester, static::$course.'.php'));
        if (!is_dir(static::$hss_course_path) || !is_file($course_config)) {
            throw new ConfigException("Invalid course: ".static::$course, true);
        }

        static::$hss_course_path = implode("/", array(static::$hss_path, "courses",
            static::$semester, static::$course));

        $course_config = implode("/", array(__DIR__, '..', '..', 'config', static::$semester, static::$course.'.php'));
        if (!file_exists($course_config)) {
            throw new FileNotFoundException("Missing course config file");
        }
        $config = array();
        require_once($course_config);
        if (!isset($config['database_name'])) {
            throw new ConfigException("Missing course config: database_name");
        }
        static::$database_name = $config['database_name'];

        Database::connect(static::$database_host, static::$database_user, static::$database_password,
                          static::$database_name, static::$database_type);

        static::$database_configs = Database::queries()->loadConfig();
        foreach(static::$database_configs as $row) {
            static::$$row['config_name'] = static::processConfigValue($row['config_value'], $row['config_type']);
        }
    }

    public static function processConfigValue($value, $type) {
        switch ($type) {
            case 1:
                $value = intval($value);
                break;
            case 2:
                $value = floatval($value);
                break;
            case 3:
                $value = (strtolower($value) == "true" || intval($value) == 1);
                break;
            case 4:
                // no action needed, already a string
                break;
            default:
                throw new ConfigException("'{$type}' is not a valid config type.");
        }
        return $value;
    }

    public static function buildUrl($parts) {
        return static::$site_url."&".implode("&", array_map(function($key) use ($parts) {
            return strval($key)."=".strval($parts[$key]);
        }, array_keys($parts)));
    }
}