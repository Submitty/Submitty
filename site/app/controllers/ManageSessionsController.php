<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\entities\Session;
use app\repositories\SessionRepository;

/**
 * Class ManageSessionsController
 * @package app\controllers
 */
class ManageSessionsController extends AbstractController {
    /**
     * ManageSessionsController constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        parent::__construct($core);
    }
    /**
     * Show manage sessions page
     *
     * @Route("/manage_sessions", methods={"GET"})
     * @return WebResponse
     */
    public function showSessionsPage(): WebResponse {
        /** @var SessionRepository $repo */
        $repo = $this->core->getSubmittyEntityManager()->getRepository(Session::class);
        $user_sessions = $repo->getAllByUser($this->core->getUser()->getId());
        return new WebResponse(
            'ManageSessions',
            'showSessionsPage',
            $user_sessions
        );
    }

    /**
     * Terminate a session
     *
     * @Route("/manage_sessions/logout", methods={"POST"})
     * @return RedirectResponse
     */
    public function logoutFromSession(): RedirectResponse {
        if (isset($_POST["session_id"])) {
            $session_id = $_POST["session_id"];
        }
        else {
            $this->core->addErrorMessage("Session id not provided.");
            return new RedirectResponse($this->core->buildUrl(["manage_sessions"]));
        }
        $session = $this->core->getSubmittyEntityManager()->getRepository(Session::class)
            ->findOneBy(['session_id' => $session_id]);
        if (empty($session)) {
            $this->core->addErrorMessage("Session doesn't exist.");
            return new RedirectResponse($this->core->buildUrl(["manage_sessions"]));
        }
        if ($session->getUserId() === $this->core->getUser()->getId()) {
            if ($this->core->getCurrentSessionId() === $session_id) {
                return new RedirectResponse($this->core->buildUrl(["authentication", "logout"]));
            }
            $this->core->getSubmittyEntityManager()->remove($session);
            $this->core->getSubmittyEntityManager()->flush();
            $this->core->addSuccessMessage("Session terminated successfully.");
        }
        else {
            $this->core->addErrorMessage("You don't have permission to terminate that session.");
        }
        return new RedirectResponse($this->core->buildUrl(["manage_sessions"]));
    }

    /**
     * Terminate all sessions except current
     *
     * @Route("/manage_sessions/logout_all", methods={"POST"})
     * @return RedirectResponse
     */
    public function logoutAllExceptCurrent(): RedirectResponse {
        /** @var SessionRepository $repo */
        $repo = $this->core->getSubmittyEntityManager()->getRepository(Session::class);
        $repo->removeUserSessionsExcept($this->core->getUser()->getId(), $this->core->getCurrentSessionId());
        return new RedirectResponse($this->core->buildUrl(["manage_sessions"]));
    }

    /**
     * Update the enforce_single_session boolean
     *
     * @Route("/manage_sessions/update_single_session", methods={"POST"})
     * @return RedirectResponse
     */
    public function updateSingleSession(): RedirectResponse {
        if (isset($_POST['single_session'])) {
            $single_session = $_POST['single_session'] === "on";
        }
        else {
            $single_session = false;
        }
        $this->core->getQueries()->updateSingleSessionSetting($this->core->getUser()->getId(), $single_session);
        if ($single_session) {
            /** @var SessionRepository $repo */
            $repo = $this->core->getSubmittyEntityManager()->getRepository(Session::class);
            $repo->removeUserSessionsExcept($this->core->getUser()->getId(), $this->core->getCurrentSessionId());
        }
        $this->core->addSuccessMessage("Single session setting set to " . ($single_session ? 'true.' : 'false.'));
        return new RedirectResponse($this->core->buildUrl(["manage_sessions"]));
    }
}
