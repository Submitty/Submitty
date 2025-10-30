<?php

namespace app\controllers\superuser;

use app\authentication\SamlAuthentication;
use app\controllers\AbstractController;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\models\User;
use app\views\ErrorView;
use app\views\superuser\SamlManagerView;
use Symfony\Component\Routing\Annotation\Route;

#[AccessControl(level: "SUPERUSER")]
class SamlManagerController extends AbstractController {
    /**
     * @return SamlAuthentication|false
     */
    private function checkSamlEnabled() {
        $auth = $this->core->getAuthentication();
        if ($auth instanceof SamlAuthentication) {
            return $auth;
        }
        return false;
    }

    /**
     * @return WebResponse
     */
    #[Route("/superuser/saml")]
    public function showPage(): WebResponse {
        if ($this->checkSamlEnabled() === false) {
            return new WebResponse(
                ErrorView::class,
                "errorPage",
                "SAML not enabled"
            );
        }
        $proxy_mapped_users = $this->core->getQueries()->getProxyMappedUsers();
        return new WebResponse(SamlManagerView::class, 'renderPage', $proxy_mapped_users);
    }

    /**
     * @return RedirectResponse
     */
    #[Route("/superuser/saml/new_user", methods: ["POST"])]
    public function newProxyUser(): RedirectResponse {
        $return_url = $this->core->buildUrl(['superuser', 'saml']);
        $auth = $this->checkSamlEnabled();
        if ($auth === false) {
            return new RedirectResponse($return_url);
        }
        $user_id = trim($_POST['user_id']);
        if (empty($user_id)) {
            $this->core->addErrorMessage("User ID can't be empty.");
            return new RedirectResponse($return_url);
        }
        $user = $this->core->getQueries()->getUserById($user_id);
        if ($user !== null) {
            $this->core->addErrorMessage("User ID already exists.");
            return new RedirectResponse($return_url);
        }
        if (empty(trim($_POST['user_numeric_id']))) {
            $this->core->addErrorMessage("Numeric ID can't be empty.");
            return new RedirectResponse($return_url);
        }
        $saml_id = trim($_POST['user_saml']);
        if (empty($saml_id)) {
            $this->core->addSuccessMessage("SAML ID not provided.");
            return new RedirectResponse($return_url);
        }
        $auth->setValidUsernames([$user_id, $saml_id]);
        if ($auth->isValidUsername($user_id)) {
            $this->core->addErrorMessage("User ID is a valid SAML username and cannot be used for a proxy user.");
            return new RedirectResponse($return_url);
        }

        $error_msg = "";
        $error_msg .= User::validateUserData('user_id', $user_id) ? "" : "Error in User ID\n";
        $error_msg .= User::validateUserData('user_legal_givenname', trim($_POST['user_given_name'])) ? "" : "Error in first name\n";
        $error_msg .= User::validateUserData('user_legal_familyname', trim($_POST['user_family_name'])) ? "" : "Error in last name\n";
        $error_msg .= User::validateUserData('user_email', trim($_POST['user_email'])) ? "" : "Error in email\n";

        if (!is_numeric(trim($_POST['user_numeric_id']))) {
            $error_msg .= "Error in numeric ID";
        }

        if (!empty($error_msg)) {
            $this->core->addErrorMessage($error_msg);
            return new RedirectResponse($return_url);
        }

        if (!$auth->isValidUsername($saml_id)) {
            $this->core->addErrorMessage("Provided SAML ID is not valid.");
            return new RedirectResponse($return_url);
        }

        $user = new User($this->core);
        $user->setId($user_id);
        $user->setLegalGivenName(trim($_POST['user_given_name']));
        $user->setLegalFamilyName(trim($_POST['user_family_name']));
        $user->setEmail(trim($_POST['user_email']));
        $user->setNumericId(trim($_POST['user_numeric_id']));

        $this->core->getQueries()->insertSubmittyUser($user);
        $this->core->getQueries()->insertSamlMapping($saml_id, $user_id);

        $this->core->addSuccessMessage("New User Created");
        return new RedirectResponse($return_url);
    }

    /**
     * @return RedirectResponse
     */
    #[Route("/superuser/saml/assign", methods: ["POST"])]
    public function assignProxyMapping(): RedirectResponse {
        $return_url = $this->core->buildUrl(['superuser', 'saml']);
        $auth = $this->checkSamlEnabled();
        if ($auth === false) {
            return new RedirectResponse($return_url);
        }
        $saml_id = trim($_POST['saml_id']);
        $auth->setValidUsernames([$saml_id]);
        if (!$auth->isValidUsername($saml_id)) {
            $this->core->addErrorMessage("SAML ID must be a valid SAML username");
            return new RedirectResponse($return_url);
        }
        $submitty_id = trim($_POST['submitty_id']);
        $user = $this->core->getQueries()->getUserById($submitty_id);
        if ($user === null) {
            $this->core->addErrorMessage("Submitty user not found with that ID");
            return new RedirectResponse($return_url);
        }
        $this->core->getQueries()->insertSamlMapping($saml_id, $submitty_id);
        $this->core->addSuccessMessage("SAML mapping added");
        return new RedirectResponse($return_url);
    }

    /**
     * @return RedirectResponse
     */
    #[Route("/superuser/saml/update_active", methods: ["POST"])]
    public function updateActiveSaml(): RedirectResponse {
        $return_url = $this->core->buildUrl(['superuser', 'saml']);

        if (!isset($_POST['id']) || !isset($_POST['activate'])) {
            $this->core->addErrorMessage("Missing id or active status");
            return new RedirectResponse($return_url);
        }

        $id = intval($_POST['id']);
        $active = $_POST['activate'] === "1";

        if (!$this->core->getQueries()->isSamlProxyUser($id)) {
            $this->core->addErrorMessage("You can't update the active status on that mapping");
            return new RedirectResponse($return_url);
        }

        $this->core->getQueries()->updateSamlMapping($id, $active);
        $this->core->addSuccessMessage("Successfully updated");

        return new RedirectResponse($return_url);
    }

    /**
     * @return RedirectResponse
     */
    #[Route("/superuser/saml/delete", methods: ["POST"])]
    public function deleteSamlMapping(): RedirectResponse {
        $return_url = $this->core->buildUrl(['superuser', 'saml']);

        if (!isset($_POST['id'])) {
            $this->core->addErrorMessage("Missing id");
            return new RedirectResponse($return_url);
        }

        $id = intval($_POST['id']);

        if (!$this->core->getQueries()->samlMappingDeletable($id)) {
            $this->core->addErrorMessage("You can't delete that mapping");
            return new RedirectResponse($return_url);
        }

        $this->core->getQueries()->deleteSamlMapping($id);
        $this->core->addSuccessMessage("Successfully deleted");
        return new RedirectResponse($return_url);
    }

    /**
     * @return WebResponse
     */
    #[Route("/superuser/saml/validate")]
    public function validate(): WebResponse {
        if ($this->checkSamlEnabled() === false) {
            return new WebResponse(
                ErrorView::class,
                "errorPage",
                "SAML not enabled"
            );
        }
        // check that all users have at least 1 mapping in saml_mapped_users
        $users = $this->core->getQueries()->checkNonMappedUsers();
        $proxy_mapped_users = $this->core->getQueries()->getProxyMappedUsers();
        return new WebResponse(SamlManagerView::class, 'renderPage', $proxy_mapped_users, $users);
    }
}
