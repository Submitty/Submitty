<?php

namespace app\libraries;

use app\entities\Session;
use app\repositories\SessionRepository;
use DateInterval;
use DateTime;

/**
 * Class SessionManager
 *
 * Handles dealing with session information given a session id or
 * a user id. The session allows for a user to remain logged in
 * as well as contains their CSRF token that was generated when
 * they logged in.
 */
class SessionManager {
    const SESSION_EXPIRATION = "2 weeks";

    /**
     * @var Core
     */
    private $core;

    /**
     * @var Session|null
     */
    private $session = null;

    /**
     * SessionManager constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        $this->core = $core;
    }

    /**
     * Given a session id, grab the associated row from the database returning false if
     * no such row exists or returning true if the row does exist. If the row exists, additionally
     * update when it'll expire by 24 hours
     *
     * @return bool|string
     */
    public function getSession(string $session_id) {
        $em = $this->core->getSubmittyEntityManager();
        /** @var SessionRepository $repo */
        $repo =  $em->getRepository(Session::class);
        $this->session = $repo->getActiveSessionById($session_id);
        if (empty($this->session)) {
            return false;
        }

        return $this->session->getUser()->getId();
    }

    /**
     * Sessions should only be updated once per day to reduce load on the server.
     * Updates the session if needed and returns if the update was performed.
     */
    public function checkAndUpdateSession(): bool {
        $day_before_expiration = (new DateTime())->add(DateInterval::createFromDateString(self::SESSION_EXPIRATION))->sub(DateInterval::createFromDateString('1 day'));
        $needs_update = $this->session->getSessionExpires() < $day_before_expiration;

        if ($needs_update) {
            $this->session->updateSessionExpiration($this->core->getDateTimeNow());
            $this->core->getSubmittyEntityManager()->flush();
        }

        return $needs_update;
    }

    /**
     * Create a new session for the user
     * @param array<string, string> $user_agent
     */
    public function newSession(string $user_id, array $user_agent): string {
        if (empty($this->session)) {
            $this->session = new Session(
                Utils::generateRandomString(),
                $user_id,
                Utils::generateRandomString(),
                $this->core->getDateTimeNow()->add(\DateInterval::createFromDateString(SessionManager::SESSION_EXPIRATION)),
                $this->core->getDateTimeNow(),
                $user_agent
            );
            $em = $this->core->getSubmittyEntityManager();
            $em->persist($this->session);
            $em->flush();
        }
        return $this->session->getSessionId();
    }

    /**
     * Get the session id of the currently loaded session otherwise return false
     *
     * @return string|bool
     */
    public function getCurrentSessionId() {
        if (isset($this->session)) {
            return $this->session->getSessionId();
        }
        return false;
    }

    /**
     * Deletes the session currently loaded within the SessionManager.
     * Returns true if there was an active session to be removed, else return false.
     *
     * @return bool
     */
    public function removeCurrentSession(): bool {
        if (isset($this->session)) {
            $em = $this->core->getSubmittyEntityManager();
            $session = $em->getReference(Session::class, $this->session->getSessionId());
            $em->remove($session);
            $em->flush();
            $this->session = null;
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
        if (isset($this->session)) {
            return $this->session->getCsrfToken();
        }
        return false;
    }
}
