<?php

namespace app\libraries;

/**
 * Class SessionManager
 *
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

    private $session = [];

    /**
     * SessionManager constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        $this->core = $core;
    }

    /**
     * Given a session id, grab the assiociated row from the database returning false if
     * no such row exists or returning true if the row does exist. If the row exists, additionally
     * update when it'll expire by 24 hours
     *
     * @return bool|string
     */
    public function getSession(string $session_id) {
        $this->core->getQueries()->removeExpiredSessions();
        $this->session = $this->core->getQueries()->getSession($session_id);
        if (empty($this->session)) {
            return false;
        }
        $this->core->getQueries()->updateSessionExpiration($session_id);

        return $this->session['user_id'];
    }

    /**
     * Create a new session for the user
     *
     * @return string
     */
    public function newSession(string $user_id): string {
        if (!isset($this->session['session_id'])) {
            $this->session['session_id'] = Utils::generateRandomString();
            $this->session['user_id'] = $user_id;
            $this->session['csrf_token'] = Utils::generateRandomString();
            $this->core->getQueries()->newSession(
                $this->session['session_id'],
                $this->session['user_id'],
                $this->session['csrf_token']
            );
        }
        return $this->session['session_id'];
    }

    /**
     * Deletes the session currently loaded within the SessionManager.
     * Returns true if there was an active session to be removed, else return false.
     *
     * @return bool
     */
    public function removeCurrentSession(): bool {
        if (isset($this->session['session_id'])) {
            $this->core->getQueries()->removeSessionById($this->session['session_id']);
            $this->session = [];
            return true;
        }
        return false;
    }

    /**
     * Gets the CSRF token that is loaded within the current session, if it exists,
     * otherwise return False
     *
     * @return bool|string
     */
    public function getCsrfToken() {
        if (isset($this->session['csrf_token'])) {
            return $this->session['csrf_token'];
        }
        return false;
    }
}
