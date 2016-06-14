<?php

namespace app\libraries;

/**
 * Class SessionManager
 *
 * TODO: Actually hook this up to some tables
 * Handles dealing with session information given a session id or
 * a user id. The session allows for a user to remain logged in
 * as well as contains their CSRF token that was generated when
 * they logged in.
 */
class SessionManager {
    /**
     * @var Core
     */
    private $core;

    /**
     * @var string
     */
    private $csrf_token = null;

    /**
     * SessionManager constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function getSession($session_id) {
        $this->core->getQueries()->removeExpiredSessions();
        $session = $this->core->getQueries()->getSession($session_id);
        if (empty($session)) {
            return false;
        }

        $this->core->getQueries()->updateSessionExpiration($session_id);
        $this->csrf_token = $session['csrf_token'];

        return true;
    }

    public function newSession($user_id) {
        $session = $this->core->getQueries()->newSession($user_id);
        setcookie('session_id', $session['session_id']);
        $this->csrf_token = $session['csrf_token'];
        return $session['session_id'];
    }

    /**
     * @return string
     */
    public function getCsrfToken() {
        return $this->csrf_token;
    }

}