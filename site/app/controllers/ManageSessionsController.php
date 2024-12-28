<?php

namespace app\controllers;

use app\libraries\response\WebResponse;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\entities\Session;
use app\repositories\SessionRepository;
use app\views\ManageSessionsView;

/**
 * Class ManageSessionsController
 * @package app\controllers
 */
class ManageSessionsController extends AbstractController {
    /**
     * Show manage sessions page
     *
     * @return WebResponse
     */
    #[Route("/manage_sessions", methods: ["GET"])]
    public function showSessionsPage(): WebResponse {
        /** @var SessionRepository $repo */
        $repo = $this->core->getSubmittyEntityManager()->getRepository(Session::class);
        $user_sessions = $repo->getAllByUser($this->core->getUser()->getId());
        return new WebResponse(
            ManageSessionsView::class,
            'showSessionsPage',
            $user_sessions
        );
    }

    /**
     * Terminate a session
     *
     * @return RedirectResponse
     */
    #[Route("/manage_sessions/logout", methods: ["POST"])]
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
        if (empty($session) || ($session->getUser()->getId() !== $this->core->getUser()->getId())) {
            $this->core->addErrorMessage("Session doesn't exist.");
            return new RedirectResponse($this->core->buildUrl(["manage_sessions"]));
        }
        if ($this->core->getCurrentSessionId() === $session_id) {
            return new RedirectResponse($this->core->buildUrl(["authentication", "logout"]));
        }
        $this->core->getSubmittyEntityManager()->remove($session);
        $this->core->getSubmittyEntityManager()->flush();
        $this->core->addSuccessMessage("Session terminated successfully.");
        return new RedirectResponse($this->core->buildUrl(["manage_sessions"]));
    }

    /**
     * Terminate all sessions except current
     *
     * @return RedirectResponse
     */
    #[Route("/manage_sessions/logout_all", methods: ["POST"])]
    public function logoutAllExceptCurrent(): RedirectResponse {
        /** @var SessionRepository $repo */
        $repo = $this->core->getSubmittyEntityManager()->getRepository(Session::class);
        $repo->removeUserSessionsExcept($this->core->getUser()->getId(), $this->core->getCurrentSessionId());
        $this->core->addSuccessMessage("All sessions other than the current one terminated successfully.");
        return new RedirectResponse($this->core->buildUrl(["manage_sessions"]));
    }

    /**
     * Update the enforce_single_session boolean
     *
     * @return RedirectResponse
     */
    #[Route("/manage_sessions/update_single_session", methods: ["POST"])]
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
