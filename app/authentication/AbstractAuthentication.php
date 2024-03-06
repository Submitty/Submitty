<?php

declare(strict_types=1);

namespace app\authentication;

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

    public function __construct(Core $core) {
        $this->core = $core;
    }

    /**
     * Given a username and password, attempt to authenticate through some method
     * return a true or false depending on whether or not the user was able to
     * be authenticated.
     *
     * @return bool
     */
    abstract public function authenticate();

    public function setUserId($user_id) {
        $this->user_id = trim(strtolower($user_id));
    }

    public function setPassword($password) {
        $this->password = $password;
    }

    public function getUserId() {
        return $this->user_id;
    }
}
