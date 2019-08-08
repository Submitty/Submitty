<?php

namespace app\authentication;

use app\libraries\AuthenticationManager;
use app\libraries\Core;

/**
 * Interface IAuthentication
 *
 * Authentication interface that all authentication modules need to implement. Any concrete class
 * that implements this class, its __construct method can take it the current instance of the
 * Core library class.
 */
abstract class AbstractAuthentication {
    protected $core;
    protected $user_id = null;
    protected $password = null;

    public function __construct(Core $core, AuthenticationManager $manager) {
        $this->core = $core;
    }

    public function setUserId(string $user_id): void {
        $this->user_id = $user_id;
    }

    public function setPassword(string $password): void {
        $this->password = $password;
    }

    /**
     * Given a username and password, attempt to authenticate through some method
     * return a true or false depending on whether or not the user was able to
     * be authenticated.
     *
     * @return bool
     */
    abstract public function authenticate(): bool;
}
