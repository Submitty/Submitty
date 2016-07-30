<?php

namespace app\authentication;

/**
 * Interface IAuthentication
 *
 * Authentication interface that all authentication modules need to implement. Any concrete class
 * that implements this class, its __construct method can take it the current instance of the
 * Core library class.
 */
interface IAuthentication {
    /**
     * Given a username and password, attempt to authenticate through some method
     * return a true or false depending on whether or not the user was able to
     * be authenticated.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function authenticate($username, $password);
}