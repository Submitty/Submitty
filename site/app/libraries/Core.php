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
    private $user;

    /**
     * Core constructor.
     *
     * @param $semester
     * @param $course
     *
     * @throws \Exception|DatabaseException
     */
    public function __construct($semester, $course) {
        $this->config = new Config($semester, $course);

        $this->database = new Database($this->config->getDatabaseHost(), $this->config->getDatabaseUser(),
            $this->config->getDatabasePassword(), $this->config->getDatabaseName(), $this->config->getDatabaseType());

        $auth_class = "\\app\\authentication\\".$this->config->getAuthentication();
        if (!in_array('IAuthentication', class_implements($auth_class))) {
            throw new \Exception("Invalid module specified for Authentication. All modules should implement the 
            IAuthentication interface.");
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

        // initialize our alert queue if it doesn't exist
        if (!isset($_SESSION['messages'])) {
            $_SESSION['messages'] = array();
        }

        // initialize our alert types if one of them doesn't exist
        foreach (array('errors', 'alerts', 'successes') as $key) {
            if (!isset($_SESSION['messages'][$key])) {
                $_SESSION['messages'][$key] = array();
            }
        }

        // we cast each of our controller markers to lower to normalize our controller switches
        // and prevent any unexpected page failures for users in entering a capitalized controller
        foreach (array('component', 'page', 'action') as $key) {
            $_REQUEST[$key] = (isset($_REQUEST[$key])) ? strtolower($_REQUEST[$key]) : "";
        }
    }

    /**
     * Deconstructor for the Core. Cleans up any messages from the server as well as disconnects
     * the database, running any open transactions that were left.
     */
    public function __destruct() {
        foreach (array('errors', 'alerts', 'successes') as $key) {
            $_SESSION['messages'][$key] = array();
        }
        $this->getDatabase()->disconnect();
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
     * @return User
     */
    public function getUser() {
        return $this->user;
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

    public function getSession($session_id) {
        $session = $this->session_manager->getSession($session_id);
        if ($session === false) {
            return false;
        }

        $this->loadUser($session['user_id']);
    }

    public function authenticate($user_id, $password) {
        $auth = false;

        if (isset($_COOKIE['session_id'])) {
            $auth = $this->session_manager->getSession($_COOKIE['session_id']);
        }

        if (!$auth) {
            if ($this->authentication->authenticate($user_id, $password)) {
                $auth = true;
                $session_id = $this->session_manager->newSession($user_id);
                setcookie('session_id', $session_id);
                $this->loadUser($user_id);
            }

        }

        return $auth;
    }

    /**
     * @param $csrf_token
     *
     * @return bool
     */
    public function checkCsrfToken($csrf_token) {
        return $this->getCsrfToken() != $csrf_token;
    }

    /**
     * @param $parts
     *
     * @return string
     */
    public function buildUrl($parts) {
        return $this->config->getSiteUrl()."&".http_build_query($parts);
    }

    /**
     * @param     $url
     * @param int $status_code
     */
    public function redirect($url, $status_code = 302) {
        header('Location: ' . $url, true, $status_code);
        die();
    }
}