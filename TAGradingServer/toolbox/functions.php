<?php

// Display all errors on initial startup in case we have an early failure in autoloader, or DB setup, etc.
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/*
The user's umask is ignored for the user running php, so we need
to set it from inside of php to make sure the group read & execute
permissions aren't lost for newly created files & directories. We do this
here as every working file must include functions.php to actuall work.
*/
umask (0027);
date_default_timezone_set(\app\models\Config::timezone);


use \lib\AutoLoader;
use \lib\Database;
use \lib\ExceptionHandler;
use \lib\IniParser;
use \lib\Logger;
use \models\User;

// get our sweet autoloader!
include __DIR__ . "/../lib/AutoLoader.php";
AutoLoader::registerDirectory(__DIR__."/../lib", true, "lib");
AutoLoader::registerDirectory(__DIR__."/../models", true, "models");

$start_time = microtime_float();

////////////////////////////////////////////////////////////////////////////////////////////////////////
// INCLUDES
////////////////////////////////////////////////////////////////////////////////////////////////////////

$_GET['course'] = isset($_GET['course']) ? str_replace("/", "_", $_GET['course']) : "";
$_GET['semester'] = isset($_GET['semester']) ? str_replace("/", "_", $_GET['semester']) : "";

$old_method = false;
if (is_file(__DIR__."/../../site/config/master.ini")) {
    $old_method = true;
    $a = IniParser::readFile(__DIR__."/../../site/config/master.ini");
}
else {
    $a = IniParser::readFile(__DIR__."/../../../config/master.ini");
}

$base_url = $a['site_details']['base_url'];
$ta_base_url = $a['site_details']['ta_base_url'];
define("__CGI_URL__", $a['site_details']['cgi_url']);
define("__SUBMISSION_GRACE_PERIOD_SECONDS__", 5 * 60);
define("__OUTPUT_MAX_LENGTH__", 100000);
define("__DATABASE_HOST__", $a['database_details']['database_host']);
define("__DATABASE_USER__", $a['database_details']['database_user']);
define("__DATABASE_PASSWORD__", $a['database_details']['database_password']);

define("__SUBMISSION_SERVER__", $a['site_details']['submitty_path']."/courses/".$_GET['semester']."/".$_GET['course']);

define("__DEBUG__", $a['site_details']['debug']);

define("__LOG_PATH__", $a['logging_details']['submitty_log_path']);
define("__LOG_EXCEPTIONS__", $a['logging_details']['log_exceptions']);

define('__TMP_XLSX_PATH__', '/tmp/_SUBMITTY_xlsx');
define('__TMP_CSV_PATH__',  '/tmp/_SUBMITTY_csv');

$config = __SUBMISSION_SERVER__."/config/config.ini";
if (!file_exists($config)) {
    die(\lib\ErrorPage::get_error_page("Fatal Error: The config for the specified semester '${_GET['semester']}' and 
    specified course '{$_GET['course']}' does not exist"));
}

$a = IniParser::readFile($config);
define("__COURSE_CODE__", $_GET['course']);
define("__COURSE_SEMESTER__", $_GET['semester']);
define("__DATABASE_NAME__", $a['hidden_details']['database_name']);
if (isset($a['hidden_details']['course_url'])) {
    define("__SUBMISSION_URL__", $a['hidden_details']['course_url']);
}
else {
    define("__SUBMISSION_URL__", $base_url);
}
if (isset($a['hidden_details']['ta_base_url'])) {
    define("__BASE_URL__", $a['hidden_details']['ta_base_url']);
}
else {
    define("__BASE_URL__", $ta_base_url);
}
define("__COURSE_NAME__", $a['course_details']['course_name']);
define("__CALCULATE_DIFF__", true);
define("__DEFAULT_HW_LATE_DAYS__", $a['course_details']['default_hw_late_days']);
define("__DEFAULT_TOTAL_LATE_DAYS__", $a['course_details']['default_student_late_days']);
define("__USE_AUTOGRADER__", true);
define("__ZERO_RUBRIC_GRADES__", $a['course_details']['zero_rubric_grades']);

$DEBUG = (defined('__DEBUG__')) ? (__DEBUG__): false;
ExceptionHandler::$debug = $DEBUG;
ExceptionHandler::$logExceptions = __LOG_EXCEPTIONS__;
Logger::$log_path = __LOG_PATH__;

if($DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

$db = Database::getInstance();
$db->connect(__DATABASE_HOST__, __DATABASE_USER__, __DATABASE_PASSWORD__, __DATABASE_NAME__);

$COURSE_NAME = __COURSE_NAME__;
$BASE_URL = rtrim(__BASE_URL__, "/");
$SUBMISSION_URL = rtrim(__SUBMISSION_URL__, "/");

header("Content-Type: text/html; charset=UTF-8");

$user_id = 0;
$suggested_username = null;
if ($DEBUG && isset($_GET['useUser'])) {
    $suggested_username = $_GET['userUser'];
}
else {
    $key = 'submitty_session_id';
    if (isset($_COOKIE[$key])) {
        $cookie = json_decode($_COOKIE[$key], true);
        $db->query("SELECT * FROM sessions WHERE session_id=?", array($cookie['session_id']));
        $row = $db->row();
        if (isset($row['user_id'])) {
            $suggested_username = $row['user_id'];
            if ($cookie['expire_time'] > 0) {
                $cookie['expire_time'] = time() + (7 * 24 * 60 * 60);
                $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] != 'off';
                setcookie($key, json_encode($cookie), $cookie['expire_time'], "/", "", $secure);
            }
        }
        else {
            setcookie($key, "", time() - 3600);
        }
    }
    if (isset($_SERVER['PHP_AUTH_USER'])) {
        $suggested_username = $_SERVER['PHP_AUTH_USER'];
    }
    else if (isset($_SERVER['REMOTE_USER'])) {
        $suggested_username = $_SERVER['PHP_AUTH_USER'];
    }
}

if ($suggested_username === null) {
    if ($old_method) {
        // if not already authenticated do it
        header('WWW-Authenticate: Basic realm=HWServer');
        header('HTTP/1.0 401 Unauthorized');
        exit;
    }
    else {
        header("Location: ".$SUBMISSION_URL."/index.php?semester={$_GET['semester']}&course={$_GET['course']}");
        exit();
    }
}

$params = array($suggested_username);
try {
    User::loadUser($suggested_username);
}
catch (InvalidArgumentException $e) {
    die(\lib\ErrorPage::get_error_page("Unrecognized user: {$suggested_username}. Please contact an administrator to get an account."));
}

if (User::$user_group == 4) {
    die(\lib\ErrorPage::get_error_page("Not a valid grading user. Please contact an administrator if this is a mistake."));
}
$user_info = User::$user_details;
$user_logged_in = isset($user_info['user_id']);
$user_is_administrator = User::$is_administrator;
$user_id = $user_info['user_id'];

$DEVELOPER = User::$is_developer;

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
}

////////////////////////////////////////////////////////////////////////////////////////////////////////
// GENERAL
////////////////////////////////////////////////////////////////////////////////////////////////////////


function echo_error($error) {
    echo $error, "<br/>";
    echo "<br/>";
}

function generateNumbers($max = 64) {
    return generateRandomString("0123456789", $max);
}

function generateSalt($max = 64) {
    return generateRandomString("abcdef0123456789", $max);
}

function generateRandomString($alphabet, $max = 64) {
    $retVal = "";

    for($i = 0; $i < $max; $i++)
    {
        $retVal .= $alphabet{mt_rand(0, (strlen($alphabet) - 1))};
    }

    return $retVal;
}

function strip_url_get_variables($url) {
    $retVal = explode("?", $url);
    return $retVal[0];
}

function url_location() {
    $location = $_SERVER["PHP_SELF"];
    if (!strstr($location,'.php')) {
        $location .= 'index.php';
    }
    $paths = explode("/", $location);
    $return = array();
    foreach($paths as $path) {
        if ($path != "" && !strstr(__BASE_URL__, $path)) {
            $return[] = $path;
        }
    }

    return substr(implode("/", $return), 0, -4);

}

function url_sans_get() {
    $retVal = explode("?", $_SERVER["REQUEST_URI"]);
    return $retVal[0];
}

function url_add_get($new_get_value) {
    $retVal = $_SERVER["REQUEST_URI"];

    if(strstr($retVal, "?")) {
        $retVal .= "&" . $new_get_value;
    }
    else {
        $retVal .= "?" . $new_get_value;
    }

    return $retVal;
}

function format_money($number, $fractional=true) {
    if($fractional) {
        $number = sprintf('%.2f', $number);
    }
    while(true) {
        $replaced = preg_replace('/(-?\d+)(\d\d\d)/', '$1,$2', $number);
        if($replaced != $number) {
            $number = $replaced;
        }
        else {
            break;
        }
    }

    return $number;
}

function digit_to_ordinal($number) {
    $number = intval($number);
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');

    if(($number %100) >= 11 && ($number%100) <= 13) {
        $abbreviation = $number. 'th';
    }
    else {
        $abbreviation = $number. $ends[$number % 10];
    }

    return $abbreviation;
}


////////////////////////////////////////////////////////////////////////////////////////////////////////
// UTILITIES
////////////////////////////////////////////////////////////////////////////////////////////////////////

function getContentType($filename){
    switch (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
        // pdf
        case 'pdf':
            $content_type = "application/pdf";
            break;
        // images
        case 'png':
            $content_type = "image/png";
            break;
        case 'jpg':
        case 'jpeg':
            $content_type = "image/jpeg";
            break;
        case 'gif':
            $content_type = "image/gif";
            break;
        case 'bmp':
            $content_type = "image/bmp";
            break;
        // text
        case 'c':
            $content_type = 'text/x-csrc';
            break;
        case 'cpp':
        case 'cxx':
        case 'h':
        case 'hpp':
        case 'hxx':
            $content_type = 'text/x-c++src';
            break;
        case 'java':
            $content_type = 'text/x-java';
            break;
        case 'py':
            $content_type = 'text/x-python';
            break;
        default:
            $content_type = 'text/x-sh';
            break;
    }
    return $content_type;
}

/**
 * @param $filename
 * @param $number
 *
 * @return string
 */
function sourceSettingsJS($filename, $number) {
    $type = getContentType($filename);
    $number = intval($number);
    return <<<HTML
<script>
    var editor{$number} = CodeMirror.fromTextArea(document.getElementById('code{$number}'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true
    });

    var lineCount = editor{$number}.lineCount();
    if (lineCount == 1) {
        editor{$number}.setSize("100%", (editor{$number}.defaultTextHeight() * 2) + "px");
    }
    else {
        editor{$number}.setSize("100%", "auto");
    }
    // editor{$number}.setSize("100%", (editor{$number}.defaultTextHeight() * (lineCount+1)) + "px");
    // editor{$number}.setSize("100%", "100%" + "px");
    // editor{$number}.setSize("100%", "auto");
    // editor{$number}.setOption("viewportMargin", "infinity");
    editor{$number}.setOption("theme", "eclipse");
    editor{$number}.setOption("mode", "{$type}");

    $("#myTab").find("a").click(function (e) {
        e.preventDefault();
        $(this).tab("show");
        setTimeout(function() { editor{$number}.refresh(); }, 1);
    });

</script>
HTML;
}


/**
 * Given a path to a directory, this function checks to see if the directory exists, and if it doesn't tries to create it.
 *
 * @param $dir
 *
 * @return bool
 */
function create_dir($dir) {
    if (!is_dir($dir)) {
        return mkdir($dir);
    }
    return true;
}

/**
 * @return float
 */
function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

/**
 * @param $text
 * @param $parse_bools
 *
 * @return array
 */
function pgArrayToPhp($text, $parse_bools=false) {
    return \lib\DatabaseUtils::fromPGToPHPArray($text, $parse_bools);
}

/**
 * @param $array
 *
 * @return string
 */
function phpToPgArray($array) {
    return \lib\DatabaseUtils::fromPHPToPGArray($array);
}

/**
 * @param $json
 *
 * @return mixed
 */
function removeTrailingCommas($json){
    $json = preg_replace('/,\s*([\]}])/m', '$1', $json);
    return $json;
}

/**
 * Load config settings from the database. Any configs in the database are then
 * defined as constants using __CONFIG_NAME__ paradigm.
 */
function load_config() {
    Database::query("SELECT * FROM config");
    foreach (Database::rows() as $config) {
        $config['config_value'] = process_config_value($config['config_value'], $config['config_type']);
        $name = "__".strtoupper($config['config_name'])."__";
        define($name, $config['config_value']);
    }
}

function process_config_value($value, $type) {
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
            throw new UnexpectedValueException("{$type} is not a valid config type.");
    }
    return $value;
}

function check_administrator() {
    if (!User::$is_administrator) {
        die("<br /><br /><br /><br />&nbsp;&nbsp;You must be an administrator to access this page.");
    }
}


//
// PROBABLY NOT THE RIGHT LOCATION FOR THIS FUNCTION
//
function getActiveVersionFromFile($g_id, $student_id) {
    $settings_file = __SUBMISSION_SERVER__."/submissions/".$g_id."/".$student_id."/user_assignment_settings.json";
    if (file_exists($settings_file)) {
        $settings_file_contents = file_get_contents($settings_file);
        $settings = json_decode($settings_file_contents, true);
        return $settings['active_version'];
    }
    return 0;
}

function getDisplayName($student_info) {
    $first_name = isset($student_info['user_firstname']) ? $student_info['user_firstname'] : "";
    $preferred_name = isset($student_info['user_preferred_firstname']) ? $student_info['user_preferred_firstname'] : "";
    if ($preferred_name !== null && $preferred_name !== "") {
        return $preferred_name;
    }
    else {
        return $first_name;
    }
}
