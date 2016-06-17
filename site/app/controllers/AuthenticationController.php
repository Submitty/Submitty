<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\Output;

class AuthenticationController implements IController {

    private $core;
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

    public function logout() {
        setcookie('session_id', "", time()-3600);
        
        $this->core->redirect($this->core->buildUrl());
    }

    public function loginForm() {
        Output::render('Authentication', 'loginForm');
    }

    public function checkLogin() {
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $_SESSION['messages']['errors'][] = "Cannot leave user id or password blank";
            $redirect = array();
            foreach ($this->core->getControllerTypes() as $type) {
                if (isset($_REQUEST['old'][$type])) {
                    $redirect['old_'.$type] = $_REQUEST['old'][$type];
                }
            }
            $this->core->redirect($this->core->buildUrl(array('component' => 'login')));
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
            $this->core->buildUrl($redirect);
        }
        else {
            $_SESSION['messages']['errors'][] = "Could not login using given user id or password";
            $redirect = array();
            foreach ($this->core->getControllerTypes() as $type) {
                if (isset($_REQUEST['old'][$type])) {
                    $redirect['old_'.$type] = $_REQUEST['old'][$type];
                }
            }
            $this->core->buildUrl($redirect);
        }
    }
}