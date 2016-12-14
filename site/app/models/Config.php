<?php

namespace app\models;

use app\database\Database;
use app\exceptions\ConfigException;
use app\exceptions\FileNotFoundException;
use app\libraries\IniParser;
use app\libraries\Utils;

/**
 * Class Config
 *
 * This class handles and contains all of the variables necessary for running
 * the application. These variables are loaded from a combination of files and tables from
 * the database. We also allow for using this to write back to the variables within the database
 * (but not the variables in the files).
 */
class Config {

    /**
     * Variable to set the system to debug mode, which allows, among other things
     * easier access to user switching and to always output full exceptions. Never
     * turn on if running server in production environment.
     * @var bool
     */
    private $debug = false;

    private $semester;
    private $course;

    private $config_path;
    private $course_ini;

    /*** MASTER CONFIG ***/
    private $base_url;
    private $course_url = null;
    private $ta_base_url;
    private $cgi_url;
    private $site_url;
    private $authentication;
    private $timezone = "America/New_York";
    private $submitty_path;
    private $course_path;
    private $submitty_log_path;
    private $log_exceptions;

    /**
     * Database host for PDO. The user does not need to set this
     * explicitly in the config files, in which case we'll just default
     * to PostgreSQL.
     * @var string
     */
    private $database_type = "pgsql";

    /**
     * Database host for PDO
     * @var string
     */
    private $database_host;

    /**
     * Database user for PDO
     * @var string
     */
    private $database_user;

    /**
     * Database password for PDO
     * @var string
     */
    private $database_password;

    /*** COURSE SPECIFIC CONFIG ***/
    /**
     * Database name for PDO
     * @var string
     */
    private $database_name;

    /*** COURSE DATABASE CONFIG ***/

    private $course_name;
    private $course_home_url;
    private $default_hw_late_days;
    private $default_student_late_days;
    private $zero_rubric_grades;
    private $display_hidden;

    private $upload_message;
    private $grades_summary;
    
    private $keep_previous_files;
    private $display_iris_grades_summary;
    private $display_custom_message;

    /**
     * Config constructor.
     *
     * @param string $semester
     * @param string $course
     * @param string $master_ini_path
     */
    public function __construct($semester, $course, $master_ini_path) {
        $this->semester = $semester;
        $this->course = $course;
        $this->config_path = realpath(dirname($master_ini_path));

        // Load config details from the master config file
        $master = IniParser::readFile($master_ini_path);

        $this->setConfigValues($master, 'logging_details', array('submitty_log_path', 'log_exceptions'));
        $this->setConfigValues($master, 'site_details', array('base_url', 'cgi_url', 'ta_base_url', 'submitty_path', 'authentication'));
        $this->setConfigValues($master, 'database_details', array('database_host', 'database_user', 'database_password'));

        if (isset($master['site_details']['debug'])) {
            $this->debug = $master['site_details']['debug'] === true;
        }

        if (isset($master['site_details']['timezone'])) {
            $this->timezone = $master['site_details']['timezone'];
        }

        if (isset($master['database_details']['database_type'])) {
            $this->database_type = $master['database_details']['database_type'];
        }

        $this->base_url = rtrim($this->base_url, "/")."/";
        $this->cgi_url = rtrim($this->cgi_url, "/")."/";
        $this->ta_base_url = rtrim($this->ta_base_url, "/")."/";

        // Check that the paths from the config file are valid
        foreach(array('submitty_path', 'submitty_log_path') as $path) {
            if (!is_dir($this->$path)) {
                throw new ConfigException("Invalid path for setting: {$path}\n{$this->$path}");
            }
            $this->$path = rtrim($this->$path, "/");
        }

        if (!is_dir(implode(DIRECTORY_SEPARATOR, array($this->submitty_path, "courses", $this->semester)))) {
            throw new ConfigException("Invalid semester: ".$this->semester, true);
        }

        $this->course_path = implode(DIRECTORY_SEPARATOR, array($this->submitty_path, "courses", $this->semester, $this->course));
        if (!is_dir($this->course_path)) {
            throw new ConfigException("Invalid course: ".$this->course, true);
        }

        $this->course_ini = implode(DIRECTORY_SEPARATOR, array($this->course_path, "config", "config.ini"));
        
        $course = IniParser::readFile($this->course_ini);

        $this->setConfigValues($course, 'hidden_details', array('database_name'));
        $this->setConfigValues($course, 'course_details', array
                               ('course_name', 'course_home_url', 'default_hw_late_days',
                                'default_student_late_days', 'zero_rubric_grades', 'upload_message', 'keep_previous_files',
                                'display_iris_grades_summary', 'display_custom_message'));
        
        if (isset($course['hidden_details']['course_url'])) {
            $this->course_url = rtrim($course['hidden_details']['course_url'], "/")."/";
            $this->base_url = $this->course_url;
        }
        
        $this->upload_message = Utils::prepareHtmlString($this->upload_message);
        
        foreach (array('default_hw_late_days', 'default_student_late_days') as $key) {
            $this->$key = intval($this->$key);
        }

        foreach (array('zero_rubric_grades', 'keep_previous_files', 'display_iris_grades_summary', 'display_custom_message') as $key) {
            $this->$key = ($this->$key == true) ? true : false;
        }
    
        $this->site_url = $this->base_url."index.php?semester=".$this->semester."&course=".$this->course;
    }

    private function setConfigValues($config, $section, $keys) {
        if (!isset($config[$section]) || !is_array($config[$section])) {
            throw new ConfigException("Missing config section {$section} in ini file");
        }

        foreach ($keys as $key) {
            if (!isset($config[$section][$key])) {
                throw new ConfigException("Missing config setting {$section}.{$key} in configuration ini file");
            }
            $this->$key = $config[$section][$key];
        }
    }

    /**
     * @return boolean
     */
    public function isDebug() {
        return $this->debug;
    }

    /**
     * @return string
     */
    public function getSemester() {
        return $this->semester;
    }

    /**
     * @return string
     */
    public function getCourse() {
        return $this->course;
    }

    /**
     * @return string
     */
    public function getBaseUrl() {
        return $this->base_url;
    }
    
    /**
     * @return string
     */
    public function getTABaseUrl() {
        return $this->ta_base_url;
    }
    
    /**
     * @return string
     */
    public function getCgiUrl() {
        return $this->cgi_url;
    }

    /**
     * @return string
     */
    public function getSiteUrl() {
        return $this->site_url;
    }

    /**
     * @return string
     */
    public function getSubmittyPath() {
        return $this->submitty_path;
    }

    /**
     * @return string
     */
    public function getCoursePath() {
        return $this->course_path;
    }

    /**
     * @return string
     */
    public function getLogPath() {
        return $this->submitty_log_path;
    }

    /**
     * @return bool
     */
    public function getLogExceptions() {
        return $this->log_exceptions;
    }

    /**
     * @return string
     */
    public function getDatabaseType() {
        return $this->database_type;
    }

    /**
     * @return string
     */
    public function getDatabaseHost() {
        return $this->database_host;
    }

    /**
     * @return string
     */
    public function getDatabaseUser() {
        return $this->database_user;
    }

    /**
     * @return string
     */
    public function getDatabasePassword() {
        return $this->database_password;
    }

    /**
     * @return string
     */
    public function getDatabaseName() {
        return $this->database_name;
    }

    /**
     * @return string
     */
    public function getCourseName() {
        return $this->course_name;
    }
    /**
     * @return string
     */
    public function getCourseHomeUrl(){
        return $this->course_home_url;
    }

    /**
     * @return integer
     */
    public function getDefaultHwLateDays() {
        return $this->default_hw_late_days;
    }

    /**
     * @return integer
     */
    public function getDefaultStudentLateDays() {
        return $this->default_student_late_days;
    }

    /**
     * @return bool
     */
    public function shouldZeroRubricGrades() {
        return $this->zero_rubric_grades;
    }
    
    public function shouldDisplayHidden() {
        return $this->display_hidden;
    }

    public function getConfigPath() {
        return $this->config_path;
    }

    /**
     * @return string
     */
    public function getAuthentication() {
        return $this->authentication;
    }

    /**
     * @return string
     */
    public function getTimezone() {
        return $this->timezone;
    }
    
    public function getUploadMessage() {
        return $this->upload_message;
    }
    
    public function displayCustomMessage() {
        return $this->display_custom_message;
    }

    public function keepPreviousFiles() {
        return $this->keep_previous_files;
    }

    public function displayIrisGradesSummary() {
        return $this->display_iris_grades_summary;
    }

    public function showGradeSummary() {
        return $this->grades_summary;
    }
    
    public function getCourseIniPath() {
        return $this->course_ini;
    }
    
    public function getCourseUrl() {
        return $this->course_url;
    }
}