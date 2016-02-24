<?php

// Display all errors on initial startup in case we have an early failure in autoloader, or DB setup, etc.
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
The user's umask is ignored for the user running php, so we need
to set it from inside of php to make sure the group read & execute
permissions aren't lost for newly created files & directories. We do this
here as every working file must include functions.php to actuall work.
*/
umask (0027);
date_default_timezone_set('America/New_York');

use \lib\AutoLoader;
use \lib\Database;
use \lib\ExceptionHandler;
use \lib\Logger;
use \app\models\User;

// get our sweet autoloader!
include __DIR__ . "/../lib/AutoLoader.php";
AutoLoader::registerDirectory(__DIR__."/../lib", true, "lib");
AutoLoader::registerDirectory(__DIR__."/../app", true, "app");

$start_time = microtime_float();

////////////////////////////////////////////////////////////////////////////////////////////////////////
// INCLUDES
////////////////////////////////////////////////////////////////////////////////////////////////////////

if (isset($_GET['course'])) {
    // don't allow the user entered course to potentially point to a different directory via use of ../
    $_GET['course'] = str_replace("/", "_", $_GET['course']);
    $config = __DIR__."/configs/".$_GET['course'].".php";
    if (!file_exists($config)) {
        die(\lib\ErrorPage::get_error_page("Fatal Error: The config for the specified course '{$_GET['course']}' does not exist"));
    }
}
else {
    die(\lib\ErrorPage::get_error_page("Fatal Error: You must have course=#### in the URL bar"));
}

require_once("configs/master.php");
require_once($config);

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
    error_reporting(E_ERROR);
}

$db = Database::getInstance();
$db->connect(__DATABASE_HOST__, __DATABASE_USER__, __DATABASE_PASSWORD__, __DATABASE_NAME__);

load_config();

$COURSE_NAME = __COURSE_NAME__;
$BASE_URL = rtrim(__BASE_URL__, "/");

header("Content-Type: text/html; charset=UTF-8");

$user_id = 0;
if ($DEBUG) {
    // TODO: we need to have a pseudo http login box just to always set $_SERVER["PHP_AUTH_USER"] to not default to me
    $suggested_username = (isset($_GET['useUser'])) ? $_GET['useUser'] : "pevelm";
}
else {
    $suggested_username = $_SERVER["PHP_AUTH_USER"];
}
$params = array($suggested_username);
try {
    User::loadUser($suggested_username);
}
catch (InvalidArgumentException $e) {
    die(\lib\ErrorPage::get_error_page("Unrecognized user: {$suggested_username}. Please contact an administrator to get an account."));
}
$user_info = User::$user_details;
$user_logged_in = isset($user_info['user_id']);
$user_is_administrator = User::$is_administrator;
$user_id = $user_info['user_id'];

$DEVELOPER = User::$is_developer;

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


/**
 * @param $filename
 * @param $number
 *
 * @return string
 */
function sourceSettingsJS($filename, $number) {
    switch(strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
        case 'c':
            $type = 'text/x-csrc';
            break;
        case 'cpp':
        case 'cxx':
        case 'h':
        case 'hpp':
        case 'hxx':
            $type = 'text/x-c++src';
            break;
        case 'java':
            $type = 'text/x-java';
            break;
        case 'py':
            $type = 'text/x-python';
            break;
        default:
            $type = 'text/x-sh';
            break;
    }

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
    if (lineCount == 0) {
        //lineCount += 1;
    }
    editor{$number}.setSize("100%", (editor{$number}.defaultTextHeight() * (lineCount+1)) + "px");
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

// TODO: Make sure this is working as expected. Searching '\r' in the database should return no rows whatsoever.
function clean_string($str) {
    $str = trim($str);
    $str = str_replace('\r\n', '\n', $str);
    $str = str_replace('\n', '\n', $str);
    $str = str_replace('\r', '\n', $str);
    $str = str_replace(PHP_EOL, '\n', $str);
    $str = str_replace("\x20\x0b", '\n', $str); # replace unicode character to prevent javascript errors.
    $str = str_replace("\x0d\x0a", '\n', $str); # replace unicode character to prevent javascript errors.

    return $str;
}

function clean_string_javascript($str) {
    $str = str_replace('"', '\"', $str);
    $str = str_replace('\\\"', '\"', $str);
    $str = str_replace("\r","",$str);
    $str = str_replace("\n","\"+\n\"",$str);

    return $str;
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
 *
 * @return array
 */
function pgArrayToPhp($text) {
    return \lib\DatabaseUtils::fromPGToPHPArray($text);
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