<?php

namespace app\authentication;

use app\libraries\Core;

/**
 * Class DatabaseAuthentication
 *
 * Database module for Authentication that allows for checking a user
 * account against information saved in the database. The user password
 * should be hashed via the password_hash function so we can then use
 * password_verify to check (and not worry about salt's and things)
 * @link http://php.net/manual/en/function.password-hash.php
 * @link http://php.net/manual/en/function.password-verify.php
 */
class DatabaseAuthentication implements IAuthentication {
    /** @var Core Core library for running the application */
    private $core;
    
    /**
     * DatabaseAuthentication constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function authenticate($user_id, $password) {
        $user = $this->core->getQueries()->getUserById($user_id);
        if (empty($user)) {
            return false;
        }

        return password_verify($password, $user['password']);
    }
}