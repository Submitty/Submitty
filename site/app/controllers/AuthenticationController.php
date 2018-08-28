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
            case 'vcs_login':
                $this->vcsLogin();
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
            if(isset($_REQUEST['old'])) {
                foreach ($_REQUEST['old'] as $key => $value) {
                    $redirect[$key] = $value;
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
        $cookie_id = 'submitty_session_id';
        Utils::setCookie($cookie_id, '', time() - 3600);
        $redirect = array();
        $redirect['page'] = 'login';
        $this->core->removeCurrentSession();
        $this->core->redirect($this->core->buildUrl($redirect));
    }
    
    /**
     * Display the login form to the user
     */
    public function loginForm() {
        $old = $_REQUEST['old'] ?? [];

        //Don't log in to bring us back to login
        if (array_key_exists("page", $old) && $old["page"] === "login") {
            unset($old["page"]);
        }

        $this->core->getOutput()->renderOutput('Authentication', 'loginForm', $old);
    }
    
    /**
     * Checks the submitted login form via the configured "authentication" setting. Additionally, on successful
     * login, we want to redirect the user $_REQUEST the page they were attempting to goto before being sent to the
     * login form (this being saved in the $_POST['old'] array). However, on failure to login, we want to continue
     * to maintain that old request data passing it back into the login form.
     */
    public function checkLogin() {
        $redirect = array();
        $no_redirect = !empty($_POST['no_redirect']) ? $_POST['no_redirect'] == 'true' : false;
        $_POST['stay_logged_in'] = (isset($_POST['stay_logged_in']));
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $msg = 'Cannot leave user id or password blank';

            foreach ($_REQUEST as $key => $value) {
                if (substr($key, 0, 4) == "old_") {
                    $redirect[$key] = $_REQUEST['old'][$value];
                }
            }
            if ($no_redirect) {
                $this->core->getOutput()->renderJsonFail($msg);
            }
            else {
                $this->core->addErrorMessage("Cannot leave user id or password blank");
                $this->core->redirect($this->core->buildUrl($redirect));
            }
            return false;
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        if ($this->core->authenticate($_POST['stay_logged_in']) === true) {
            foreach ($_REQUEST as $key => $value) {
                if (substr($key, 0, 4) == "old_") {
                    $redirect[substr($key, 4)] = $value;
                }
            }
            $msg = "Successfully logged in as ".htmlentities($_POST['user_id']);
            $this->core->addSuccessMessage($msg);
            $redirect['success_login'] = "true";

            if ($no_redirect) {
                $this->core->getOutput()->renderJsonSuccess(['message' => $msg, 'authenticated' => true]);
            }
            else {
                $this->core->redirect($this->core->buildUrl($redirect));
            }
            return true;
        }
        else {
            $msg = "Could not login using that user id or password";
            $this->core->addErrorMessage($msg);
            foreach ($_REQUEST as $key => $value) {
                if (substr($key, 0, 4) == "old_") {
                    $redirect[substr($key, 4)] = $value;
                }
            }
            if ($no_redirect) {
                $this->core->getOutput()->renderJsonFail($msg);
            }
            else {
                $this->core->redirect($this->core->buildUrl($redirect));
            }
            return false;
        }
    }

    public function vcsLogin() {
        if (empty($_POST['user_id']) || empty($_POST['password']) || empty($_POST['gradeable_id'])
            || empty($_POST['id']) || !$this->core->getConfig()->isCourseLoaded()) {
            $msg = 'Missing value for one of the fields';

            $this->core->getOutput()->renderJsonError($msg);
            return false;
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        if ($this->core->authenticate(false) !== true) {
            $msg = "Could not login using that user id or password";
            $this->core->getOutput()->renderJsonFail($msg);
            return false;
        }

        $user = $this->core->getQueries()->getUserById($_POST['user_id']);
        if ($user === null) {
            $msg = "Could not find that user for that course";
            $this->core->getOutput()->renderJsonFail($msg);
            return false;
        }
        else if ($user->accessFullGrading()) {
            $msg = "Successfully logged in as {$_POST['user_id']}";
            $this->core->getOutput()->renderJsonSuccess(['message' => $msg, 'authenticated' => true]);
            return true;
        }

        $gradeable = $this->core->getQueries()->getGradeableConfig($_POST['gradeable_id']);
        if ($gradeable !== null && $gradeable->isTeamAssignment()) {
            if (!$this->core->getQueries()->getTeamById($_POST['id'])->hasMember($_POST['user_id'])) {
                $msg = "This user is not a member of that team.";
                $this->core->getOutput()->renderJsonFail($msg);
                return false;
            }
        }
        elseif ($_POST['user_id'] !== $_POST['id']) {
            $msg = "This user cannot check out that repo.";
            $this->core->getOutput()->renderJsonFail($msg);
            return false;
        }

        $msg = "Successfully logged in as {$_POST['user_id']}";
        $this->core->getOutput()->renderJsonSuccess(['message' => $msg, 'authenticated' => true]);
        return true;
    }
}
