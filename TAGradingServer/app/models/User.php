<?php

namespace app\models;

use \lib\Database;
use lib\ExceptionHandler;

/**
 * Class User
 * @package app\models
 */
class User {
    
    /**
     * @var int
     */
    public static $user_id = -1;
    
    /**
     * @var array
     */
    public static $user_details = array();

    /**
     * Is this user an administrator and should be allowed to manage the grading server
     * @var bool
     */
    public static $is_administrator = false;

    /**
     * Is this user a developer and should have access to in-progress features and pages?
     * @var bool
     */
    public static $is_developer = false;

    /**
     * This is a singleton class, so no instation or duplication
     */
    private function __construct() { }
    private function __clone() { }

    /**
     * @param string $user_rcs: This is a user's RCS/username on the database
     *
     * @throws \InvalidArgumentException|\lib\ServerException
     */
    public static function loadUser($user_rcs) {
        Database::query("SELECT * FROM users WHERE user_rcs=?",array($user_rcs));
        User::$user_details = Database::row();
        if (User::$user_details == array()) {
            ExceptionHandler::$debug = true;
            ExceptionHandler::throwException("User", new \InvalidArgumentException("Cannot load user '{$user_rcs}'"));
        } // @codeCoverageIgnore
        
        User::$user_id = User::$user_details['user_id'];
        User::$is_developer = User::$user_details['user_is_developer'] == 1;
        User::$is_administrator = User::$user_details['user_is_administrator'] == 1 || User::$is_developer;
    }
}