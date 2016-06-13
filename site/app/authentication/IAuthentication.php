<?php

namespace app\authentication;

/**
 * Interface IAuthentication
 *
 * Authentication interface that all authentication modules need to implement. 
 */
interface IAuthentication {
    /**
     * Given a username and password, attempt to authenticate through some method
     * return a true or false depending on whether or not the user was able to
     * be authenticated.
     *
     * @param $username
     * @param $password
     *
     * @return bool
     */
    public function authenticate($username, $password);
}