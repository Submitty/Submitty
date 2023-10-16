<?php

namespace app\authentication;

use app\exceptions\CurlException;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\SamlSettings;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\ValidationError;

class SamlAuthentication extends AbstractAuthentication {
    private $auth;
    private $valid_usernames = null;

    public function __construct(Core $core) {
        // Library requires directory separator needed at end of path
        define('ONELOGIN_CUSTOMPATH', FileUtils::joinPaths($core->getConfig()->getConfigPath(), 'saml') . DIRECTORY_SEPARATOR);
        $this->auth = new Auth(SamlSettings::getSettings($core));
        parent::__construct($core);
    }

    public function redirect($old): string {
        if ($old === null) {
            $redirect = $this->core->buildUrl(['home']);
        }
        else {
            $redirect = urldecode($old);
        }
        $url = $this->auth->login($redirect, [], false, false, true);
        $_SESSION['AuthnRequestID'] = $this->auth->getLastRequestID();
        return $url;
    }

    public function setValidUsernames(array $usernames) {
        try {
            $username_check = $this->core->curlRequest(
                $this->core->getConfig()->getCgiUrl() . "saml_check.cgi",
                ['usernames' => json_encode($usernames)]
            );
            $username_check = json_decode($username_check, true);
            if ($username_check !== null && isset($username_check['always_valid'])) {
                if ($username_check['always_valid'] === true) {
                    $this->valid_usernames = null;
                    return;
                }
            }
            if ($username_check !== null && isset($username_check['usernames'])) {
                $this->valid_usernames = $username_check['usernames'];
            }
            else {
                $this->valid_usernames = [];
            }
        }
        catch (CurlException $e) {
            $this->valid_usernames = [];
        }
    }

    /**
     * Checks if provided username is valid
     * setValidUsernames must be called beforehand
     *
     * @param string $username
     * @return bool
     */
    public function isValidUsername(string $username): bool {
        if ($this->valid_usernames === null) {
            return true;
        }
        return in_array($username, $this->valid_usernames, true);
    }

    /**
     * Checks if provided username is invalid
     * setValidUsernames must be called beforehand
     *
     * @param string $username
     * @return bool
     */
    public function isInvalidUsername(string $username): bool {
        if ($this->valid_usernames === null) {
            return false;
        }
        return !in_array($username, $this->valid_usernames);
    }

    public function authenticate(): bool {
        if (isset($_POST['user_id']) && isset($_SESSION['Authenticated_User_Id'])) {
            // need to check if SAML_Authenticated_User has permission to log in as user_id
            $authorized_users = array_map(function ($user) {
                return $user['user_id'];
            }, $this->core->getQueries()->getSAMLAuthorizedUserIDs($_SESSION['Authenticated_User_Id']));
            unset($_SESSION['Authenticated_User_Id']);
            if (in_array($_POST['user_id'], $authorized_users)) {
                $this->user_id = $_POST['user_id'];
                $_POST['RelayState'] = $_SESSION['RelayState'];
                unset($_SESSION['RelayState']);
                return true;
            }
            return false;
        }

        if (!isset($_SESSION['AuthnRequestID'])) {
            $this->core->addErrorMessage("Something went wrong. Please try again.");
            return false;
        }
        $request_id = $_SESSION['AuthnRequestID'];
        unset($_SESSION['AuthnRequestID']);
        try {
            $this->auth->processResponse($request_id);
        }
        catch (Error | ValidationError) {
            $this->core->addErrorMessage("Invalid request. Please try again.");
            return false;
        }

        if ($this->auth->isAuthenticated() && empty($this->auth->getErrors())) {
            $attribute_name = $this->core->getConfig()->getSamlOptions()['username_attribute'];

            $saml_user_id = $this->auth->getAttribute($attribute_name)[0];

            $authorized_user_ids = $this->core->getQueries()->getSAMLAuthorizedUserIDs($saml_user_id);

            if (isset($_POST['RelayState'])) {
                if (!str_starts_with($_POST['RelayState'], $this->core->buildUrl())) {
                    $_POST['RelayState'] = $this->core->buildUrl(['home']);
                }
            }
            else {
                $_POST['RelayState'] = $this->core->buildUrl(['home']);
            }

            $num_auth_user_ids = count($authorized_user_ids);

            if ($num_auth_user_ids === 0) {
                $this->core->addErrorMessage("Please contact your instructor to log in.");
                return false;
            }

            if ($num_auth_user_ids === 1) {
                $this->setUserId($authorized_user_ids[0]['user_id']);
                return true;
            }

            $_SESSION['Authenticated_User_Id'] = $saml_user_id;
            $_SESSION['RelayState'] = $_POST['RelayState'];
            $this->core->redirect($this->core->buildUrl(['authentication', 'user_select']));
        }
        return false;
    }

    public function getMetaData(): string {
        return $this->auth->getSettings()->getSPMetadata(
            true,
            time() + 60 * 60 * 24 * 365 * 10 // 10 years
        );
    }
}
