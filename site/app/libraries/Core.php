<?php

namespace app\libraries;

use app\authentication\IAuthentication;
use app\exceptions\DatabaseException;
use app\libraries\database\DatabaseQueriesPostgresql;
use app\libraries\database\IDatabaseQueries;
use app\models\Config;
use app\models\User;

/**
 * Class Core
 *
 * This is the core of the application that contains references to the other main
 * libraries (such as Database, Session, etc.) that the application relies on.
 */
class Core {
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Database
     */
    private $database;

    /**
     * @var IAuthentication
     */
    private $authentication;

    /**
     * @var SessionManager
     */
    private $session_manager;

    /**
     * @var IDatabaseQueries
     */
    private $database_queries;

    /**
     * @var User
     */
    private $user = null;

    /**
     * Core constructor.
     *
     */
    public function __construct() {
        $this->output = new Output($this);
        // initialize our alert queue if it doesn't exist
        if(!isset($_SESSION['messages'])) {
            $_SESSION['messages'] = array();
        }
    
        // initialize our alert types if one of them doesn't exist
        foreach (array('error', 'notice', 'success') as $key) {
            if(!isset($_SESSION['messages'][$key])) {
                $_SESSION['messages'][$key] = array();
            }
        }
    
        // we cast each of our controller markers to lower to normalize our controller switches
        // and prevent any unexpected page failures for users in entering a capitalized controller
        foreach (array('component', 'page', 'action') as $key) {
            $_REQUEST[$key] = (isset($_REQUEST[$key])) ? strtolower($_REQUEST[$key]) : "";
        }
    }
    
    public function loadConfig($semester, $course) {
        $this->config = new Config($semester, $course);

        $this->database = new Database($this->config->getDatabaseHost(), $this->config->getDatabaseUser(),
            $this->config->getDatabasePassword(), $this->config->getDatabaseName(), $this->config->getDatabaseType());

        $auth_class = "\\app\\authentication\\".$this->config->getAuthentication();
        if (!in_array('app\authentication\IAuthentication', class_implements($auth_class))) {
            throw new \Exception("Invalid module specified for Authentication. All modules should implement the IAuthentication interface.");
        }
        $this->authentication = new $auth_class($this);
        $this->session_manager = new SessionManager($this);

        switch ($this->config->getDatabaseType()) {
            case 'pgsql':
                $this->database_queries = new DatabaseQueriesPostgresql($this->database);
                break;
            default:
                throw new DatabaseException("Unrecognized database type");
        }
    }

    /**
     * Deconstructor for the Core. Cleans up any messages from the server as well as disconnects
     * the database, running any open transactions that were left.
     */
    public function __destruct() {
        if ($this->database !== null) {
            $this->getDatabase()->disconnect();
        }
    }

    /**
     * @return Config
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * @return Database
     */
    public function getDatabase() {
        return $this->database;
    }

    /**
     * @return IDatabaseQueries
     */
    public function getQueries() {
        return $this->database_queries;
    }

    /**
     * @param string $user_id
     */
    public function loadUser($user_id) {
        // attempt to load rcs as both student and user
        $this->user = new User($user_id, $this->database_queries);
    }

    /**
     * Returns the user that the client is logged in as. Will return null if there is no user
     * to be logged in as.
     *
     * @return User
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * Is a user loaded into the Core to be used for the client to be logged in as
     *
     * @return bool
     */
    public function userLoaded() {
        return $this->user !== null;
    }

    /**
     * @return string
     */
    public function getCsrfToken() {
        return $this->session_manager->getCsrfToken();
    }

    /**
     * @return IAuthentication
     */
    public function getAuthentication() {
        return $this->authentication;
    }

    /**
     * @param $session_id
     *
     * @return bool
     */
    public function getSession($session_id) {
        $user_id = $this->session_manager->getSession($session_id);
        if ($user_id === false) {
            return false;
        }

        $this->loadUser($user_id);
        return true;
    }

    /**
     * Remove the currently loaded session within the session manager
     */
    public function removeCurrentSession() {
        $this->session_manager->removeCurrentSession();
    }

    /**
     * @param $user_id
     * @param $password
     *
     * @return bool
     */
    public function authenticate($user_id, $password) {
        $auth = false;
        if ($this->authentication->authenticate($user_id, $password)) {
            $auth = true;
            $session_id = $this->session_manager->newSession($user_id);
            // We set the cookie to expire 10 years into the future effectly making it last forever
            if (setcookie('session_id', $session_id, time() + (10 * 365 * 24 * 60 * 60), "/") === false) {
                return false;
            }
        }
        return $auth;
    }

    /**
     * Checks the inputted $csrf_token against the one that is loaded from the session table for the particular
     * signed in user.
     *
     * @param string $csrf_token
     *
     * @return bool
     */
    public function checkCsrfToken($csrf_token) {
        return $this->getCsrfToken() === $csrf_token;
    }

    /**
     * Given some number of URL parameters (parts), build a URL for the site using those parts
     *
     * @param array $parts
     *
     * @return string
     */
    public function buildUrl($parts=array()) {
        return $this->config->getSiteUrl().((count($parts) > 0) ? "&".http_build_query($parts) : "");
    }

    /**
     * @param     $url
     * @param int $status_code
     */
    public function redirect($url, $status_code = 302) {
        header('Location: ' . $url, true, $status_code);
        die();
    }

    /**
     * Returns all the different parts of the url used for choosing the appropriate controller
     * and method of that controller to run
     *
     * @return array
     */
    public function getControllerTypes() {
        return array('component', 'page', 'action');
    }

    /**
     * Returns a string that contains the course code as well as the course name only if the course name is not
     * blank, placing a colon between the two (if both are displayed)
     *
     * @return string
     */
    public function getFullCourseName() {
        $course_name = strtoupper($this->getConfig()->getCourse());
        if ($this->getConfig()->getCourseName() !== "") {
            $course_name .= ": ".htmlentities($this->getConfig()->getCourseName());
        }
        return $course_name;
    }
    
    /**
     * @return Output
     */
    public function getOutput() {
        return $this->output;
    }
}