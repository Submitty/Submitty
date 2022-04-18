<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use app\entities\GitAuthToken;
use app\libraries\response\RedirectResponse;
use app\libraries\response\ResponseInterface;
use app\libraries\response\WebResponse;
use app\libraries\Utils;
use app\repositories\GitAuthTokenRepository;
use app\views\GitAuthView;
use Symfony\Component\Routing\Annotation\Route;

class GitAuthController extends AbstractController {
    /**
     * @Route("/git_auth_tokens", methods={"GET"})
     */
    public function gitAuthTokens(): WebResponse {
        $em = $this->core->getSubmittyEntityManager();
        /** @var GitAuthTokenRepository $repo */
        $repo = $em->getRepository(GitAuthToken::class);
        $tokens = $repo->getAllByUser($this->core->getUser()->getId(), true);

        return new WebResponse(
            GitAuthView::class,
            'showGitAuthPage',
            $tokens
        );
    }

    /**
     * @Route("/git_auth_tokens", methods={"POST"})
     */
    public function createGitAuthToken(): ResponseInterface {
        if (!isset($_POST['name']) || !isset($_POST['expiration'])) {
            $this->core->addErrorMessage("Name or expiration not provided");
            return new RedirectResponse($this->core->buildUrl(['git_auth_tokens']));
        }
        $name = $_POST['name'];
        $expiration = intval($_POST['expiration']);
        $valid_expirations = [0, 1, 6, 12];
        if (!in_array($expiration, $valid_expirations)) {
            $this->core->addErrorMessage("Please pick a valid expiration time");
            return new RedirectResponse($this->core->buildUrl(['git_auth_tokens']));
        }
        if ($expiration === 0) {
            $expiration = null;
        }
        else {
            $time_to_add = new \DateInterval("P{$expiration}M");
            $expiration = $this->core->getDateTimeNow()->add($time_to_add);
        }

        $token = Utils::generateRandomString();
        $hashed_token = password_hash($token, PASSWORD_DEFAULT);

        $auth_token = new GitAuthToken(
            $this->core->getUser()->getId(),
            $hashed_token,
            $name,
            $expiration
        );
        $em = $this->core->getSubmittyEntityManager();
        $em->persist($auth_token);
        $em->flush();

        /** @var GitAuthTokenRepository $repo */
        $repo = $em->getRepository(GitAuthToken::class);
        $tokens = $repo->getAllByUser($this->core->getUser()->getId(), true);

        $this->core->addSuccessMessage("New token created successfully");

        return new WebResponse(
            GitAuthView::class,
            'showGitAuthPage',
            $tokens,
            $auth_token,
            $token
        );
    }

    /**
     * @Route("/git_auth_tokens/revoke", methods={"POST"})
     */
    public function revokeToken(): RedirectResponse {
        if (!isset($_POST['id'])) {
            $this->core->addErrorMessage("ID wasn't specified.");
            return new RedirectResponse($this->core->buildUrl(['git_auth_tokens']));
        }
        $id = $_POST['id'];
        $em = $this->core->getSubmittyEntityManager();
        /** @var GitAuthToken | null $token */
        $token = $em->getRepository(GitAuthToken::class)->find($id);
        if ($token === null || $token->getUserId() !== $this->core->getUser()->getId()) {
            $this->core->addErrorMessage("Unknown token");
            return new RedirectResponse($this->core->buildUrl(['git_auth_tokens']));
        }
        $em->remove($token);
        $em->flush();
        $this->core->addSuccessMessage("Token revoked");
        return new RedirectResponse($this->core->buildUrl(['git_auth_tokens']));
    }
}
