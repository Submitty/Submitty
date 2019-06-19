<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\Output;
use app\libraries\Utils;
use Symfony\Component\Routing\Annotation\Route;

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
    public function __construct(Core $core, $logged_in=false) {
        parent::__construct($core);
        $this->logged_in = $logged_in;
    }

    public function run() {
        switch ($_REQUEST['page']) {
            case 'logout':
                $this->logout();
                break;
            case 'checklogin':
                $this->checkLogin();
                break;
            case 'vcs_login':
                $this->vcsLogin();
                break;
            case 'login':
            default:
                $this->loginForm();
                break;
        }
    }

    /**
     * @param string $old the url to redirect to after login
     */
    private function isLoggedIn($old = null) {
        if ($this->logged_in) {
            $this->core->redirect($old);
        }
    }
    
    /**
     * Logs out the current user from the system. This is done by both deleting the current going
     * session from the database as well as invalidating the session id saved in the cookie. The latter
     * is not strictly necessary, but still good to tidy up.
     *
     * @Route("/authentication/logout")
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
     *
     * @Route("/authentication/login")
     *
     * @var string $old the url to redirect to after login
     */
    public function loginForm($old = null) {
        $this->isLoggedIn(urldecode($old));
        $this->core->getOutput()->renderOutput('Authentication', 'loginForm', $old);
    }
    
    /**
     * Checks the submitted login form via the configured "authentication" setting. Additionally, on successful
     * login, we want to redirect the user $_REQUEST the page they were attempting to goto before being sent to the
     * login form (this being saved in the $_POST['old'] array). However, on failure to login, we want to continue
     * to maintain that old request data passing it back into the login form.
     *
     * @Route("/authentication/check_login")
     *
     * @var string $old the url to redirect to after login
     */
    public function checkLogin($old = null) {
        if (isset($old)) {
            $old = urldecode($old);
        }
        $this->isLoggedIn($old);
        $redirect = array();
        $no_redirect = !empty($_POST['no_redirect']) ? $_POST['no_redirect'] == 'true' : false;
        $_POST['stay_logged_in'] = (isset($_POST['stay_logged_in']));
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $msg = 'Cannot leave user id or password blank';

            if ($no_redirect) {
                $this->core->getOutput()->renderJsonFail($msg);
            }
            else {
                $this->core->addErrorMessage("Cannot leave user id or password blank");
                $this->core->redirect($old);
            }
            return false;
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        if ($this->core->authenticate($_POST['stay_logged_in']) === true) {
            $msg = "Successfully logged in as ".htmlentities($_POST['user_id']);
            $this->core->addSuccessMessage($msg);
            $redirect['success_login'] = "true";

            if ($no_redirect) {
                $this->core->getOutput()->renderJsonSuccess(['message' => $msg, 'authenticated' => true]);
            }
            else {
                $this->core->redirect($old);
            }
            return true;
        }
        else {
            $msg = "Could not login using that user id or password";
            $this->core->addErrorMessage($msg);
            if ($no_redirect) {
                $this->core->getOutput()->renderJsonFail($msg);
            }
            else {
                $this->core->redirect($old);
            }
            return false;
        }
    }

    /**
     * Handle stateless authentication for the VCS endpoints.
     *
     * This endpoint is unique from the other authentication methods in
     * that this requires a specific course so that we can check a user's
     * status, as well as potentially information about a particular
     * gradeable in that course.
     *
     * @Route("{_semester}/{_course}/authentication/vcs_login")
     */
    public function vcsLogin() {
        if (empty($_POST['user_id']) || empty($_POST['password']) || empty($_POST['gradeable_id'])
            || empty($_POST['id']) || !$this->core->getConfig()->isCourseLoaded()) {
            $msg = 'Missing value for one of the fields';
            return $this->core->getOutput()->renderJsonError($msg);
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        if ($this->core->getAuthentication()->authenticate() !== true) {
            $msg = "Could not login using that user id or password";
            return $this->core->getOutput()->renderJsonFail($msg);
        }

        $user = $this->core->getQueries()->getUserById($_POST['user_id']);
        if ($user === null) {
            $msg = "Could not find that user for that course";
            return $this->core->getOutput()->renderJsonFail($msg);
        }
        else if ($user->accessFullGrading()) {
            $msg = "Successfully logged in as {$_POST['user_id']}";
            return $this->core->getOutput()->renderJsonSuccess(['message' => $msg, 'authenticated' => true]);
        }

        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($_POST['gradeable_id']);
        }
        catch (\InvalidArgumentException $exc) {
            $gradeable = null;
        }

        if ($gradeable !== null && $gradeable->isTeamAssignment()) {
            if (!$this->core->getQueries()->getTeamById($_POST['id'])->hasMember($_POST['user_id'])) {
                $msg = "This user is not a member of that team.";
                return $this->core->getOutput()->renderJsonFail($msg);
            }
        }
        elseif ($_POST['user_id'] !== $_POST['id']) {
            $msg = "This user cannot check out that repository.";
            return $this->core->getOutput()->renderJsonFail($msg);
        }

        $msg = "Successfully logged in as {$_POST['user_id']}";
        return $this->core->getOutput()->renderJsonSuccess(['message' => $msg, 'authenticated' => true]);
    }
}
