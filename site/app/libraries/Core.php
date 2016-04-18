<?php

namespace app\libraries;

use app\exceptions\DatabaseException;
use app\libraries\database\DatabaseQueriesPostgresql;
use app\libraries\database\IDatabaseQueries;
use app\models\Config;
use app\models\User;

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
     * @var IDatabaseQueries
     */
    private $database_queries;

    /**
     * @var User
     */
    private $user;

    /**
     * @var string
     */
    private $csrf_token;

    /**
     * Core constructor.
     *
     * @param $semester
     * @param $course
     */
    public function __construct($semester, $course) {
        $this->config = new Config($semester, $course);

        $this->database = new Database($this->config->getDatabaseHost(), $this->config->getDatabaseUser(),
            $this->config->getDatabasePassword(), $this->config->getDatabaseName(), $this->config->getDatabaseType());

        switch ($this->config->getDatabaseType()) {
            case 'pgsql':
                $this->database_queries = new DatabaseQueriesPostgresql($this->database);
                break;
            default:
                throw new DatabaseException("Unrecognized database type");
        }

        // Generate a CSRF token for the user if we don't have one already
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16));
        }

        $this->csrf_token = $_SESSION['csrf_token'];

        if (!isset($_SESSION['messages'])) {
            $_SESSION['messages'] = array();
        }

        foreach (array('errors', 'alerts', 'successes') as $key) {
            if (!isset($_SESSION['messages'][$key])) {
                $_SESSION['messages'][$key] = array();
            }
        }

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
     * @param string $username
     */
    public function loadUser($username) {
        // attempt to load rcs as both student and user
        $this->user = new User($username, $this->database_queries);
        if (!$this->user->userLoaded()) {
            Output::showError("Unrecognized username '{$username}'");
        }
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
        return $this->csrf_token;
    }

    /**
     * @param $csrf_token
     * @return bool
     */
    public function checkCsrfToken($csrf_token) {
        return $this->csrf_token != $csrf_token;
    }

    public function buildUrl($parts) {
        return $this->config->getSiteUrl()."&".http_build_query($parts);
    }

    public function redirect($url, $status_code = 302) {
        header('Location: ' . $url, true, $status_code);
        die();
    }
}