<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\Output;

/**
 * Class AuthenticationController
 *
 * Controller to deal with user authentication and de-authentication. The actual lifting is done through Core
 * and the associated registered IAuthentication interface returning whether or not we were able to actually
 * authenticate the user or not, and then the controller redirects on that answer.
 */
class AuthenticationController implements IController {

    /** @var Core  */
    private $core;
    
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
        $this->core = $core;
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
            default:
            case 'login':
                $this->isLoggedIn();
                $this->loginForm();
                break;
        }
    }

    public function isLoggedIn() {
        if ($this->logged_in) {
            $redirect = array();
            foreach ($this->core->getControllerTypes() as $type) {
                if (isset($_REQUEST['old'][$type])) {
                    $redirect[$type] = $_REQUEST['old'][$type];
                }
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
        setcookie('session_id', "", time()-3600);
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
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $_SESSION['messages']['error'][] = "Cannot leave user id or password blank";
            $redirect = array();
            foreach ($this->core->getControllerTypes() as $type) {
                if (isset($_REQUEST['old'][$type])) {
                    $redirect['old_'.$type] = $_REQUEST['old'][$type];
                }
            }
            $this->core->redirect($this->core->buildUrl($redirect));
        }

        if ($this->core->authenticate($_POST['user_id'], $_POST['password'])) {
            $redirect = array();
            foreach ($this->core->getControllerTypes() as $type) {
                if (isset($_REQUEST['old'][$type])) {
                    $redirect[$type] = $_REQUEST['old'][$type];
                }
                else {
                    break;
                }
            }
            $_SESSION['messages']['success'][] = "Successfully logged in as ".htmlentities($_POST['user_id']);
            $this->core->redirect($this->core->buildUrl($redirect));
        }
        else {
            $_SESSION['messages']['error'][] = "Could not login using that user id or password";
            $redirect = array();
            foreach ($this->core->getControllerTypes() as $type) {
                if (isset($_REQUEST['old'][$type])) {
                    $redirect['old_'.$type] = $_REQUEST['old'][$type];
                }
            }
            $this->core->redirect($this->core->buildUrl($redirect));
        }
    }
}