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
        $expiration = $_POST['expiration'];
        if ($expiration === '') {
            $expiration = null;
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
        $id = $auth_token->getId();

        $em = $this->core->getSubmittyEntityManager();
        /** @var GitAuthTokenRepository $repo */
        $repo = $em->getRepository(GitAuthToken::class);
        $tokens = $repo->getAllByUser($this->core->getUser()->getId());

        $this->core->addSuccessMessage("New token created successfully");

        return new WebResponse(
            GitAuthView::class,
            'showGitAuthPage',
            $tokens,
            [$id => $token]
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
        $token = $em->getRepository(GitAuthToken::class)->find($id);
        $em->remove($token);
        $em->flush();
        $this->core->addSuccessMessage("Token revoked");
        return new RedirectResponse($this->core->buildUrl(['git_auth_tokens']));
    }
}
