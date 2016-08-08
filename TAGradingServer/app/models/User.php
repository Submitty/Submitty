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
    * What privledge level is this user?  
    * @var integer
    */
    public static $user_group;
    
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
    public static function loadUser($user_id) {
        Database::query("SELECT * FROM users WHERE user_id=?",array($user_id));
        User::$user_details = Database::row();
        if (User::$user_details == array()) {
            ExceptionHandler::$debug = true;
            ExceptionHandler::throwException("User", new \InvalidArgumentException("Cannot load user '{$user_id}'"));
        } // @codeCoverageIgnore
        
        User::$user_id = User::$user_details['user_id'];
        User::$user_group = User::$user_details['user_group'];
        User::$is_developer = User::$user_details['user_group'] == 0;
        User::$is_administrator = User::$user_details['user_group'] == 1 || User::$is_developer;
    }
}