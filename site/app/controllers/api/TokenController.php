<?php

namespace app\controllers\api;

use app\controllers\AbstractController;
use app\libraries\response\MultiResponse;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TokenController
 *
 * Controller to deal with the submitty token API.
 */
class TokenController extends AbstractController {
    /**
     * @return MultiResponse
     */
    #[Route("/api/token", methods: ["POST"])]
    public function getToken() {
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $msg = 'Cannot leave user id or password blank';
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        $token = $this->core->authenticateJwt();
        if ($token) {
            return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse(['token' => $token]));
        }
        else {
            $msg = "Could not login using that user id or password";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
    }

    /**
     *
     * @return MultiResponse
     */
    #[Route("/api/token/invalidate", methods: ["POST"])]
    public function invalidateToken() {
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $msg = 'Cannot leave user id or password blank';
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        $success = $this->core->invalidateJwt();
        if ($success) {
            return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
        }
        else {
            $msg = "Could not login using that user id or password";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
    }
}
