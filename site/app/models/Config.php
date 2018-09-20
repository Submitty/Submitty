<?php

namespace app\models;

use app\controllers\admin\WrapperController;
use app\exceptions\ConfigException;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\IniParser;
use app\libraries\Utils;

/**
 * Class Config
 *
 * This class handles and contains all of the variables necessary for running
 * the application. These variables are loaded from a combination of files and tables from
 * the database. We also allow for using this to write back to the variables within the database
 * (but not the variables in the files).
 *
 * @method string getSemester()
 * @method string getCourse()
 * @method string getBaseUrl()
 * @method string getVcsUrl()
 * @method string getCgiUrl()
 * @method string getSiteUrl()
 * @method string getSubmittyPath()
 * @method string getCoursePath()
 * @method string getDatabaseDriver()
 * @method array getSubmittyDatabaseParams()
 * @method array getCourseDatabaseParams()
 * @method string getCourseName()
 * @method string getCourseHomeUrl()
 * @method integer getDefaultHwLateDays()
 * @method integer getDefaultStudentLateDays()
 * @method string getConfigPath()
 * @method string getAuthentication()
 * @method \DateTimeZone getTimezone()
 * @method string getUploadMessage()
 * @method array getHiddenDetails()
 * @method string getCourseIniPath()
 * @method bool isCourseLoaded()
 * @method string getInstitutionName()
 * @method string getInstitutionHomepage()
 * @method string getUsernameChangeText()
 * @method bool isForumEnabled()
 * @method bool isRegradeEnabled()
 * @method string getRegradeMessage()
 * @method string getVcsBaseUrl()
 * @method string getCourseEmail()
 * @method string getVcsUser()
 * @method string getVcsType()
 * @method string getPrivateRepository()
 * @method string getRoomSeatingGradeableId()
 * @method array getCourseIni()
 */

class Config extends AbstractModel {

    /**
     * Variable to set the system to debug mode, which allows, among other things
     * easier access to user switching and to always output full exceptions. Never
     * turn on if running server in production environment.
     * @property
     * @var bool
     */
    protected $debug = false;

    /** @property @var string contains the semester to use, generally from the $_REQUEST['semester'] global */
    protected $semester;
    /** @property @var string contains the course to use, generally from the $_REQUEST['course'] global */
    protected $course;

    /** @property @var string path on the filesystem that points to the course data directory */
    protected $config_path;
    /** @property @var string path to the ini file that contains all the course specific settings */
    protected $course_ini_path;

    /** @property @var array */
    protected $course_ini;

    /**
    * Indicates whether a course config has been successfully loaded.
    * @var bool
    * @property
    */
    protected $course_loaded = false;

    /*** MASTER CONFIG ***/
    /** @property @var string */
    protected $base_url;
    /** @property @var string */
    protected $vcs_url;
    /** @property @var string */
    protected $cgi_url;
    /** @property @var string */
    protected $site_url;
    /** @property @var string */
    protected $authentication;
    /** @property @var string */
    protected $timezone = 'America/New_York';
    /** @property @var string */
    protected $submitty_path;
    /** @property @var string */
    protected $course_path;
    /** @property @var string */
    protected $submitty_log_path;
    /** @property @var bool */
    protected $log_exceptions;

    /** @property @var string */
    protected $database_driver = "pgsql";

    /**
     * The name of the institution that deployed Submitty. Added to the breadcrumb bar if non-empty.
     * @var string
     * @property
     */
    protected $institution_name = "";

    /**
     * The url of the institution's homepage. Linked to from the breadcrumb created with institution_name.
     * @var string
     * @property
     */
    protected $institution_homepage = "";

    /**
     * The text to be shown to a user when they attempt to change their username.
     * @var string
     * @property
     */
    protected $username_change_text = "";

    /** @property @var array */
    protected $submitty_database_params = array();

    /** @property @var array */
    protected $course_database_params = array();

    /** @property @var array */
    protected $wrapper_files = array();

    /** @property @var string */
    protected $course_name;
    /** @property @var string */
    protected $course_home_url;
    /** @property @var int */
    protected $default_hw_late_days;
    /** @property @var int */
    protected $default_student_late_days;
    /** @property @var bool */
    protected $zero_rubric_grades;

    /** @property @var string */
    protected $upload_message;
    /** @property @var bool */
    protected $keep_previous_files;
    /** @property @var bool */
    protected $display_rainbow_grades_summary;
    /** @property @var bool */
    protected $display_custom_message;
    /** @property @var string*/
    protected $course_email;
    /** @property @var string */
    protected $vcs_base_url;
    /** @property @var string */
    protected $vcs_type;
    /** @property @var string */
    protected $private_repository;
    /** @property @var array */
    protected $hidden_details;
    /** @property @var bool */
    protected $forum_enabled;
    /** @property @var bool */
    protected $regrade_enabled;
    /** @property @var string */
    protected $regrade_message;
    /** @property @var string|null */
    protected $room_seating_gradeable_id;

    /**
     * Config constructor.
     *
     * @param Core   $core
     * @param $semester
     * @param $course
     */
    public function __construct(Core $core, $semester, $course) {
        parent::__construct($core);

        $this->semester = $semester;
        $this->course = $course;
    }

    public function loadMasterConfigs($config_path) {
        if (!is_dir($config_path)) {
            throw new ConfigException("Could not find config directory: ". $config_path, true);
        }
        $this->config_path = $config_path;
        // Load config details from the master config file
        $database_json = FileUtils::readJsonFile(FileUtils::joinPaths($this->config_path, 'database.json'));

        if (!$database_json) {
            throw new ConfigException("Could not find database config: {$this->config_path}/database.json");
        }

        $this->submitty_database_params = [
            'dbname' => 'submitty',
            'host' => $database_json['database_host'],
            'username' => $database_json['database_user'],
            'password' => $database_json['database_password']
        ];

        if (isset($database_json['driver'])) {
            $this->database_driver = $database_json['driver'];
        }

        $this->authentication = $database_json['authentication_method'];
        $this->debug = $database_json['debugging_enabled'] === true;

        $submitty_json = FileUtils::readJsonFile(FileUtils::joinPaths($this->config_path, 'submitty.json'));
        if (!$submitty_json) {
            throw new ConfigException("Could not find submitty config: {$this->config_path}/submitty.json");
        }

        $this->submitty_log_path = $submitty_json['site_log_path'];
        $this->log_exceptions = true;

        $this->base_url = $submitty_json['submission_url'];
        $this->submitty_path = $submitty_json['submitty_data_dir'];

        if (isset($submitty_json['timezone'])) {
            $this->timezone = $submitty_json['timezone'];
            if (!in_array($this->timezone, \DateTimeZone::listIdentifiers())) {
                throw new ConfigException("Invalid Timezone identifier: {$this->timezone}");
            }
        }

        if (isset($submitty_json['institution_name'])) {
            $this->institution_name = $submitty_json['institution_name'];
        }

        if (isset($submitty_json['institution_homepage'])) {
            $this->institution_homepage = $submitty_json['institution_homepage'];
        }

        if (isset($submitty_json['username_change_text'])) {
            $this->username_change_text = $submitty_json['username_change_text'];
        }

        $this->timezone = new \DateTimeZone($this->timezone);

        $this->base_url = rtrim($this->base_url, "/")."/";
        $this->cgi_url = $this->base_url."cgi-bin/";

        if (empty($submitty_json['vcs_url'])) {
            $this->vcs_url = $this->base_url . '{$vcs_type}/';
        }
        else {
            $this->vcs_url = rtrim($submitty_json['vcs_url'], '/').'/';
        }

        // Check that the paths from the config file are valid
        foreach(array('submitty_path', 'submitty_log_path') as $path) {
            if (!is_dir($this->$path)) {
                throw new ConfigException("Invalid path for setting {$path}: {$this->$path}");
            }
            $this->$path = rtrim($this->$path, "/");
        }

        foreach(array('site_errors', 'access') as $path) {
            if (!is_dir(FileUtils::joinPaths($this->submitty_log_path, $path))) {
                throw new ConfigException("Missing log folder: {$path}");
            }
        }
        $this->site_url = $this->base_url."index.php?";

        if (!empty($this->semester) && !empty($this->course)) {
            $this->course_path = FileUtils::joinPaths($this->submitty_path, "courses", $this->semester, $this->course);
        }
    }

    public function loadCourseIni($course_ini) {
        if (!file_exists($course_ini)) {
            throw new ConfigException("Could not find course config file: ".$course_ini, true);
        }
        $this->course_ini_path = $course_ini;
        $this->course_ini = IniParser::readFile($this->course_ini_path);

        if (!isset($this->course_ini['database_details']) || !is_array($this->course_ini['database_details'])) {
            throw new ConfigException("Missing config section 'database_details' in ini file");
        }

        $this->course_database_params = array_merge($this->submitty_database_params, $this->course_ini['database_details']);

        $array = [
            'course_name', 'course_home_url', 'default_hw_late_days', 'default_student_late_days',
            'zero_rubric_grades', 'upload_message', 'keep_previous_files', 'display_rainbow_grades_summary',
            'display_custom_message', 'room_seating_gradeable_id', 'course_email', 'vcs_base_url', 'vcs_type',
            'private_repository', 'forum_enabled', 'regrade_enabled', 'regrade_message'
        ];
        $this->setConfigValues($this->course_ini, 'course_details', $array);

        if (empty($this->vcs_base_url)) {
            $this->vcs_base_url = $this->vcs_url . $this->semester . '/' . $this->course;
        }

        $this->vcs_base_url = rtrim($this->vcs_base_url, "/")."/";

        if (isset($this->course_ini['hidden_details'])) {
            $this->hidden_details = $this->course_ini['hidden_details'];
            if (isset($this->course_ini['hidden_details']['course_url'])) {
                $this->base_url = rtrim($this->course_ini['hidden_details']['course_url'], "/")."/";
            }
        }

        $this->upload_message = Utils::prepareHtmlString($this->upload_message);

        foreach (array('default_hw_late_days', 'default_student_late_days') as $key) {
            $this->$key = intval($this->$key);
        }

        $array = array('zero_rubric_grades', 'keep_previous_files', 'display_rainbow_grades_summary',
            'display_custom_message', 'forum_enabled', 'regrade_enabled');
        foreach ($array as $key) {
            $this->$key = ($this->$key == true) ? true : false;
        }

        $this->site_url = $this->base_url."index.php?semester=".$this->semester."&course=".$this->course;

        $wrapper_files_path = FileUtils::joinPaths($this->getCoursePath(), 'site');
        foreach (WrapperController::WRAPPER_FILES as $file) {
            $path = FileUtils::joinPaths($wrapper_files_path, $file);
            if (file_exists($path)) {
                $this->wrapper_files[$file] = $path;
            }
        }

        $this->course_loaded = true;
    }


    private function setConfigValues($config, $section, $keys) {
        if (!isset($config[$section]) || !is_array($config[$section])) {
            throw new ConfigException("Missing config section '{$section}' in ini file");
        }

        foreach ($keys as $key) {
            if (!isset($config[$section][$key])) {
              throw new ConfigException("Missing config setting '{$section}.{$key}' in configuration ini file");
            }
            $this->$key = $config[$section][$key];
        }
    }

    public function getHomepageUrl()
    {
        return $this->base_url."index.php?";
    }

    /**
     * @return boolean
     */
    public function isDebug() {
        return $this->debug;
    }

    /**
     * @return bool
     */
    public function shouldLogExceptions() {
        return $this->log_exceptions;
    }

    /**
     * @return bool
     */
    public function shouldZeroRubricGrades() {
        return $this->zero_rubric_grades;
    }

    /**
     * @return bool
     */
    public function displayCustomMessage() {
        return $this->display_custom_message;
    }

    /**
     * @return bool
     */
    public function keepPreviousFiles() {
        return $this->keep_previous_files;
    }

    /**
     * @return bool
     */
    public function displayRainbowGradesSummary() {
        return $this->display_rainbow_grades_summary;
    }

    /**
     * @return bool
     */
    public function displayRoomSeating() {
        return $this->room_seating_gradeable_id !== "";
    }

    public function getLogPath() {
        return $this->submitty_log_path;
    }

    public function saveCourseIni($save) {
        IniParser::writeFile($this->course_ini_path, array_merge($this->course_ini, $save));
    }

    public function wrapperEnabled() {
        return $this->course_loaded
            && (count($this->wrapper_files) > 0);
    }

    public function getWrapperFiles() {
        //Return empty if not logged in because we can't access them
        return ($this->core->getUser() === null ? [] : $this->wrapper_files);
    }
}
