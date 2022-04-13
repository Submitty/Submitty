<?php

namespace app\controllers;

use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\libraries\TokenManager;
use app\views\ApiTokenView;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ApiTokenController
 * @package app\controllers
 *
 * @AccessControl(level="FACULTY")
 */
class ApiTokenController extends AbstractController {
    /**
     * @Route("/api_token", methods={"GET"})
     */
    public function showApiTokenPage(): WebResponse {
        return new WebResponse(ApiTokenView::class, "showApiToken");
    }

    /**
     * @Route("/api_token", methods={"POST"})
     */
    public function fetchApiToken(): WebResponse {
        $user_id = $this->core->getUser()->getId();
        $this->core->getQueries()->refreshUserApiKey($user_id);
        $token = TokenManager::generateApiToken(
            $this->core->getQueries()->getSubmittyUserApiKey($user_id)
        );
        return new WebResponse(ApiTokenView::class, "showApiToken", $token->toString());
    }

    /**
     * @Route("/api_token/invalidate", methods={"POST"})
     */
    public function invalidateApiToken(): RedirectResponse {
        $user_id = $this->core->getUser()->getId();
        $this->core->getQueries()->refreshUserApiKey($user_id);
        $this->core->addSuccessMessage("Token invalidated");
        return new RedirectResponse($this->core->buildUrl(['api_token']));
    }
}
