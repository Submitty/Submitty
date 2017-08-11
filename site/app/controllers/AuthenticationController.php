<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\Output;
use app\libraries\Utils;

/**
 * Class AuthenticationController
 *
 * Controller to deal with user authentication and de-authentication. The actual lifting is done through Core
 * and the associated registered IAuthentication interface returning whether or not we were able to actually
 * authenticate the user or not, and then the controller redirects on that answer.
 */
class AuthenticationController extends AbstractController {
    /**
     * @var bool Is the user logged in or not. We use this to prevent the user going to the login controller
     *           and trying to login again.
     */
    private $logged_in;

    /**
     * AuthenticationController constructor.
     *
     * @param Core $core
     * @param bool $logged_in
     */
    public function __construct(Core $core, $logged_in) {
        parent::__construct($core);
        $this->logged_in = $logged_in;
    }

    public function run() {
        switch ($_REQUEST['page']) {
            case 'logout':
                $this->logout();
                break;
            case 'checklogin':
                $this->isLoggedIn();
                $this->checkLogin();
                break;
            case 'login':
            default:
                $this->isLoggedIn();
                $this->loginForm();
                break;
        }
    }

    public function isLoggedIn() {
        if ($this->logged_in) {
            $redirect = array();
            foreach ($_REQUEST['old'] as $key => $value) {
                $redirect[$key] = $value;
            }
            $this->core->redirect($this->core->buildUrl($redirect));
        }
    }
    
    /**
     * Logs out the current user from the system. This is done by both deleting the current going
     * session from the database as well as invalidating the session id saved in the cookie. The latter
     * is not strictly necessary, but still good to tidy up.
     */
    public function logout() {
        $cookie_id = 'submitty_session_id';
        Utils::setCookie($cookie_id, '', time() - 3600);
        $this->core->removeCurrentSession();
        $this->core->redirect($this->core->buildUrl());
    }
    
    /**
     * Display the login form to the user
     */
    public function loginForm() {
        $this->core->getOutput()->renderOutput('Authentication', 'loginForm');
    }
    
    /**
     * Checks the submitted login form via the configured "authentication" setting. Additionally, on successful
     * login, we want to redirect the user $_REQUEST the page they were attempting to goto before being sent to the
     * login form (this being saved in the $_POST['old'] array). However, on failure to login, we want to continue
     * to maintain that old request data passing it back into the login form.
     */
    public function checkLogin() {
        $redirect = array();
        $_POST['stay_logged_in'] = (isset($_POST['stay_logged_in']));
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $core->addErrorMessage("Cannot leave user id or password blank");
            foreach ($_REQUEST as $key => $value) {
                if (substr($key, 0, 4) == "old_") {
                    $redirect[$key] = $_REQUEST['old'][$value];
                }
            }
            $this->core->redirect($this->core->buildUrl($redirect));
        }

        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        if ($this->core->authenticate($_POST['stay_logged_in']) === true) {
            foreach ($_REQUEST as $key => $value) {
                if (substr($key, 0, 4) == "old_") {
                    $redirect[substr($key, 4)] = $value;
                }
            }
            $core->addSuccessMessage("Successfully logged in as ".htmlentities($_POST['user_id']));
            $redirect['success_login'] = "true";
            $this->core->redirect($this->core->buildUrl($redirect));
        }
        else {
            $core->addErrorMessage("Could not login using that user id or password");
            foreach ($_REQUEST as $key => $value) {
                if (substr($key, 0, 4) == "old_") {
                    $redirect[$key] = $_REQUEST['old'][$value];
                }
            }
            $this->core->redirect($this->core->buildUrl($redirect));
        }
    }
}
