<?php

namespace app\authentication;

use app\libraries\Core;

/**
 * Class DatabaseAuthentication
 *
 * Database module for Authentication that allows for checking a user account against information saved in the
 * database. The user password should be hashed via the password_hash function so we can then use password_verify to
 * check (and not worry about salt's and things).
 *
 * @link http://php.net/manual/en/function.password-hash.php
 * @link http://php.net/manual/en/function.password-verify.php
 */
class DatabaseAuthentication extends AbstractAuthentication {

    public function authenticate() {
        $user = $this->core->getQueries()->getUserById($this->user_id);
        if (empty($user)) {
            return false;
        }

        return password_verify($this->password, $user['user_password']);
    }
}