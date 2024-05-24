<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use app\entities\VcsAuthToken;
use app\libraries\response\RedirectResponse;
use app\libraries\response\ResponseInterface;
use app\libraries\response\WebResponse;
use app\libraries\TokenManager;
use app\libraries\Utils;
use app\repositories\VcsAuthTokenRepository;
use app\views\AuthTokenView;
use Symfony\Component\Routing\Annotation\Route;

class AuthTokenController extends AbstractController {
    #[Route("/authentication_tokens", methods: ["GET"])]
    public function vcsAuthTokens(): WebResponse {
        $em = $this->core->getSubmittyEntityManager();
        /** @var VcsAuthTokenRepository $repo */
        $repo = $em->getRepository(VcsAuthToken::class);
        $tokens = $repo->getAllByUser($this->core->getUser()->getId(), true);

        $token = null;
        $auth_token = null;
        $api_token = null;

        if (isset($_SESSION['new_auth_token']) && isset($_SESSION['new_auth_token_id'])) {
            $token = $_SESSION['new_auth_token'];
            $auth_token = $repo->find($_SESSION['new_auth_token_id']);
            unset($_SESSION['new_auth_token']);
            unset($_SESSION['new_auth_token_id']);
        }

        if (isset($_SESSION['new_api_token'])) {
            $api_token = $_SESSION['new_api_token'];
            unset($_SESSION['new_api_token']);
        }

        $is_faculty = $this->core->getUser()->accessFaculty();

        return new WebResponse(
            AuthTokenView::class,
            'showAuthTokenPage',
            $is_faculty,
            $tokens,
            $auth_token,
            $token,
            $api_token
        );
    }

    #[Route("/authentication_tokens/api", methods: ["POST"])]
    public function fetchApiToken(): RedirectResponse {
        $user_id = $this->core->getUser()->getId();
        $this->core->getQueries()->refreshUserApiKey($user_id);
        $token = TokenManager::generateApiToken(
            $this->core->getQueries()->getSubmittyUserApiKey($user_id)
        );
        $_SESSION['new_api_token'] = $token->toString();
        return new RedirectResponse($this->core->buildUrl(['authentication_tokens']));
    }

    #[Route("/authentication_tokens/api/invalidate", methods: ["POST"])]
    public function invalidateApiToken(): RedirectResponse {
        $user_id = $this->core->getUser()->getId();
        $this->core->getQueries()->refreshUserApiKey($user_id);
        $this->core->addSuccessMessage("API Token invalidated");
        return new RedirectResponse($this->core->buildUrl(['authentication_tokens']));
    }

    #[Route("/authentication_tokens/vcs", methods: ["POST"])]
    public function createVcsAuthToken(): ResponseInterface {
        if (!isset($_POST['name']) || !isset($_POST['expiration']) || $_POST['name'] === "") {
            $this->core->addErrorMessage("Name or expiration not provided");
            return new RedirectResponse($this->core->buildUrl(['authentication_tokens']));
        }
        $name = $_POST['name'];
        $expiration = intval($_POST['expiration']);
        $valid_expirations = [0, 1, 6, 12];
        if (!in_array($expiration, $valid_expirations)) {
            $this->core->addErrorMessage("Please pick a valid expiration time");
            return new RedirectResponse($this->core->buildUrl(['authentication_tokens']));
        }
        if ($expiration === 0) {
            $expiration = null;
        }
        else {
            $time_to_add = new \DateInterval("P{$expiration}M");
            $expiration = $this->core->getDateTimeNow()->add($time_to_add);
        }

        $token = Utils::generateRandomString(32);
        $hashed_token = password_hash($token, PASSWORD_DEFAULT);

        $auth_token = new VcsAuthToken(
            $this->core->getUser()->getId(),
            $hashed_token,
            $name,
            $expiration
        );
        $em = $this->core->getSubmittyEntityManager();
        $em->persist($auth_token);
        $em->flush();

        $this->core->addSuccessMessage("New token created successfully");

        $_SESSION['new_auth_token'] = $token;
        $_SESSION['new_auth_token_id'] = $auth_token->getId();

        return new RedirectResponse($this->core->buildUrl(['authentication_tokens']));
    }

    #[Route("/authentication_tokens/vcs/revoke", methods: ["POST"])]
    public function revokeVcsToken(): RedirectResponse {
        if (!isset($_POST['id'])) {
            $this->core->addErrorMessage("ID wasn't specified.");
            return new RedirectResponse($this->core->buildUrl(['authentication_tokens']));
        }
        $id = $_POST['id'];
        $em = $this->core->getSubmittyEntityManager();
        /** @var VcsAuthToken | null $token */
        $token = $em->getRepository(VcsAuthToken::class)->find($id);
        if ($token === null || $token->getUserId() !== $this->core->getUser()->getId()) {
            $this->core->addErrorMessage("Unknown token");
            return new RedirectResponse($this->core->buildUrl(['authentication_tokens']));
        }
        $em->remove($token);
        $em->flush();
        $this->core->addSuccessMessage("Token revoked");
        return new RedirectResponse($this->core->buildUrl(['authentication_tokens']));
    }
}
