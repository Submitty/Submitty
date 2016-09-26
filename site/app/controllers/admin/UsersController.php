<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\Output;
use app\models\User;

class UsersController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'get_user_details':
                $this->ajaxGetUserDetails();
                break;
            case 'update_student':
                $this->updateUser('students');
                break;
            case 'update_grader':
                $this->updateUser('graders');
                break;
            case 'graders':
                $this->core->getOutput()->addBreadcrumb("Graders");
                $this->listGraders();
                break;
            case 'students':
            default:
                $this->core->getOutput()->addBreadcrumb("Students");
                $this->listStudents();
                break;
        }
    }

    public function listStudents() {
        $students = $this->core->getQueries()->getAllUsers();
        $sections = $this->core->getQueries()->getRegistrationSections();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'listStudents', $students, $sections);
    }

    public function listGraders() {
        $graders = $this->core->getQueries()->getAllGraders();
        $sections = $this->core->getQueries()->getRegistrationSections();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'listGraders', $graders, $sections);
    }

    public function ajaxGetUserDetails() {
        $user_id = $_REQUEST['user_id'];
        $user = $this->core->getQueries()->getUserById($user_id);
        $this->core->getOutput()->renderJson(array(
            'user_id' => $user->getId(),
            'user_firstname' => $user->getFirstName(),
            'user_lastname' => $user->getLastName(),
            'user_preferred_firstname' => $user->getPreferredFirstName(),
            'user_email' => $user->getEmail(),
            'user_group' => $user->getGroup(),
            'registration_section' => $user->getRegistrationSection(),
            'rotating_section' => $user->getRotatingSection(),
            'manual_registration' => $user->isManualRegistration(),
            'grading_registration_sections' => $user->getGradingRegistrationSections()
        ));
    }

    public function updateUser($action='students') {
        $return_url = $this->core->buildUrl(array('component' => 'admin', 'page' => 'users',
            'action' => $action), 'user-'.$_POST['user_id']);
        if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $this->core->addErrorMessage("Invalid CSRF token.");
            $this->core->redirect($return_url);
        }
        $user = $this->core->getQueries()->getUserById($_POST['user_id']);
        if (!$user->isLoaded() && $_POST['edit_user'] == "true") {
            $this->core->addErrorMessage("No user found with that user id");
            $this->core->redirect($return_url);
        }

        $user->setId($_POST['user_id']);
        $user->setFirstName($_POST['user_firstname']);
        $user->setPreferredFirstName($_POST['user_preferred_firstname']);
        $user->setLastName($_POST['user_lastname']);
        $user->setEmail($_POST['user_email']);
        if ($_POST['registered_section'] === "null") {
            $user->setRegistrationSection(null);
        }
        else {
            $user->setRegistrationSection(intval($_POST['registered_section']));
        }

        $user->setGroup(intval($_POST['user_group']));
        $user->setManualRegistration(isset($_POST['manual_registration']));
        if (isset($_POST['grading_registration_section'])) {
            $user->setGradingRegistrationSections(array_map("intval", $_POST['grading_registration_section']));
        }
        else {
            $user->setGradingRegistrationSections(array());
        }

        if ($_POST['edit_user'] == "true") {
            $this->core->getQueries()->updateUser($user);
            $this->core->addSuccessMessage("User '{$user->getId()}' updated");
        }
        else {
            $this->core->getQueries()->createUser($user);
            $this->core->addSuccessMessage("User '{$user->getId()}' created");
        }
        $this->core->redirect($return_url);
    }
}