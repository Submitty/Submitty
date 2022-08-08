<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

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
     * @return MultiResponse
     */
    public function showSessionsPage() {
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'ManageSessions',
                'showSessionsPage'
            )
        );
    }

    /**
     * Terminate a session
     *
     * @Route("/manage_sessions/terminate", methods={"POST"})
     * @return MultiResponse
     */
    public function terminateSession() {
        if (isset($_POST["session_id"])) {
            $session_id = $_POST["session_id"];
        }
        else {
            return MultiResponse::JsonOnlyResponse(JsonResponse::getErrorResponse("Session id not provided."));
        }
        $fetched_session = $this->core->getQueries()->getSession($session_id);
        if (count($fetched_session) === 0) {
            return MultiResponse::JsonOnlyResponse(JsonResponse::getErrorResponse("Session doesn't exist."));
        }
        if ($fetched_session['user_id'] === $this->core->getUser()->getId()) {
            if ($this->core->getCurrentSessionId() === $session_id) {
                return MultiResponse::JsonOnlyResponse(JsonResponse::getErrorResponse("Logout instead of terminating the current session."));
            }
            $this->core->getQueries()->removeSessionById($session_id);
            return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse(["message" => "Session terminated successfully."]));
        }
        else {
            return MultiResponse::JsonOnlyResponse(JsonResponse::getErrorResponse("You don't have permission to terminate that session."));
        }
    }

    /**
     * Terminate all sessions except current
     *
     * @Route("/manage_sessions/terminate_all", methods={"POST"})
     * @return JsonResponse
     */
    public function terminateAllExceptCurrent() {
        $this->core->getQueries()->removeUserSessionsExcept($this->core->getCurrentSessionId());
        return JsonResponse::getSuccessResponse("Sessions other than current terminated successfully.");
    }

    /**
     * Update the enforce_secure_session boolean
     *
     * @Route("/manage_sessions/update_secure_session", methods={"POST"})
     * @return JsonResponse
     */
    public function updateSecureSession() {
        if (isset($_POST['secure_session'])) {
            $secure_session = $_POST['secure_session'] === "true";
        }
        else {
            return JsonResponse::getErrorResponse("enfore_secure_session boolean is missing.");
        }
        $this->core->getQueries()->updateSecureSessionSetting($this->core->getUser()->getId(), $secure_session);
        if ($secure_session) {
            $this->core->getQueries()->removeUserSessionsExcept($this->core->getCurrentSessionId());
        }
        return JsonResponse::getSuccessResponse(['secure_session' => $secure_session]);
    }
}