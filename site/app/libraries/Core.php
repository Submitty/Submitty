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

        if (!isset($_SESSION['messages'])) {
            $_SESSION['messages'] = array('errors' => array(), 'alerts' => array());
        }

        $this->csrf_token = $_SESSION['csrf_token'];
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
     */
    public function checkCsrfToken($csrf_token) {
        if ($this->csrf_token != $csrf_token) {
            Output::showError("Invalid CSRF token match. Try going back a page and trying again.");
        }
    }

    public function buildUrl($parts) {
        return $this->config->getSiteUrl()."&".implode("&", array_map(function($key) use ($parts) {
            return strval($key)."=".strval($parts[$key]);
        }, array_keys($parts)));
    }

    public function redirect($url, $status_code = 302) {
        header('Location: ' . $url, true, $status_code);
        die();
    }
}