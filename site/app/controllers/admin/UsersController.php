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
            case 'rotating_sections':
                $this->rotatingSectionsForm();
                break;
            case 'update_rotating_sections':
                $this->updateRotatingSections();
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
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'listStudents', $students);
        $this->renderUserForm('update_student');
    }

    public function listGraders() {
        $graders = $this->core->getQueries()->getAllGraders();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'listGraders', $graders);
        $this->renderUserForm('update_grader');
    }

    private function renderUserForm($action) {
        $reg_sections = $this->core->getQueries()->getRegistrationSections();
        $rot_sections = $this->core->getQueries()->getRotatingSections();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'userForm', $reg_sections, $rot_sections, $action);
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

        if ($_POST['edit_user'] == "true") {
            $user = $this->core->getQueries()->getSubmittyUser($_POST['user_id']);
            if ($user === null) {
                $this->core->addErrorMessage("No user found with that user id");
                $this->core->redirect($return_url);
            }
        }
        else {
            $user = $this->core->loadModel(User::class);
            $user->setId($_POST['user_id']);
        }

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

        if ($_POST['rotating_section'] == "null") {
            $user->setRotatingSection(null);
        }
        else {
            $user->setRotatingSection(intval($_POST['rotating_section']));
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
            $this->core->getQueries()->updateUser($user, $this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse());
            $this->core->addSuccessMessage("User '{$user->getId()}' updated");
        }
        else {
            $this->core->getQueries()->insertUser($user, $this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse());
            $this->core->addSuccessMessage("User '{$user->getId()}' created");
        }
        $this->core->redirect($return_url);
    }

    public function rotatingSectionsForm() {
        $non_null_counts = $this->core->getQueries()->getCountUsersRotatingSections();
        $null_counts = $this->core->getQueries()->getCountNullUsersRotatingSections();
        $max_section = $this->core->getQueries()->getMaxRotatingSection();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'rotatingUserForm',
            $non_null_counts, $null_counts, $max_section);
    }

    public function updateRotatingSections() {
        $return_url = $this->core->buildUrl(
            array('component' => 'admin',
                  'page' => 'users',
                  'action' => 'rotating_sections')
        );

        if (!$this->core->checkCsrfToken()) {
            $this->core->addErrorMessage("Invalid CSRF token. Try again.");
            $this->core->redirect($return_url);
        }

        if (!isset($_REQUEST['sort_type'])) {
            $this->core->addErrorMessage("Must select one of the three options for setting up rotating sections");
            $this->core->redirect($return_url);
        }
        else if ($_REQUEST['sort_type'] === "drop_null") {
            $this->core->getQueries()->setNonRegisteredUsersRotatingSectionNull();
            $this->core->addSuccessMessage("Non registered students removed from rotating sections");
            $this->core->redirect($return_url);
        }

        if (isset($_REQUEST['rotating_type']) && in_array($_REQUEST['rotating_type'], array('random', 'alphabetically'))) {
            $type = $_REQUEST['rotating_type'];
        }
        else {
            $type = 'random';
        }

        $section_count = intval($_REQUEST['sections']);
        if ($section_count < 1) {
            $this->core->addErrorMessage("You must have at least one rotating section");
            $this->core->redirect($return_url);
        }

        if (in_array($_REQUEST['sort_type'], array('redo', 'fewest')) && $type == "random") {
            $sort = $_REQUEST['sort_type'];
        }
        else {
            $sort = 'redo';
        }

        $section_counts = array_fill(0, $section_count, 0);
        if ($sort === 'redo') {
            $users = $this->core->getQueries()->getRegisteredUserIds();
            if ($type === 'random') {
                shuffle($users);
            }
            $this->core->getQueries()->setAllUsersRotatingSectionNull();
            $this->core->getQueries()->deleteAllRotatingSections();
            for ($i = 1; $i <= $section_count; $i++) {
                $this->core->getQueries()->insertNewRotatingSection($i);
            }

            for ($i = 0; $i < count($users); $i++) {
                $section = $i % $section_count;
                $section_counts[$section]++;
            }
        }
        else {
            $this->core->getQueries()->setNonRegisteredUsersRotatingSectionNull();
            $max_section = $this->core->getQueries()->getMaxRotatingSection();
            if ($max_section === null) {
                $this->core->addErrorMessage("No rotating sections have been added to the system, cannot use fewest");
            }
            else if ($max_section != $section_count) {
                $this->core->addErrorMessage("Cannot use a different number of sections when setting up via fewest");
                $this->core->redirect($return_url);
            }
            $users = $this->core->getQueries()->getRegisteredUserIdsWithNullRotating();
            // only random sort can use 'fewest' type
            shuffle($users);
            $sections = $this->core->getQueries()->getCountUsersRotatingSections();
            $use_section = 0;
            $max = $sections[0]['count'];
            foreach ($sections as $section) {
                if ($section['count'] < $max) {
                    $use_section = $section['rotating_section'] - 1;
                    break;
                }
            }

            for ($i = 0; $i < count($users); $i++) {
                $section_counts[$use_section]++;
                $use_section = ($use_section + 1) % $section_count;
            }
        }

        for ($i = 0; $i < $section_count; $i++) {
            $update_users = array_splice($users, 0, $section_counts[$i]);
            if (count($update_users) == 0) {
                continue;
            }
            $this->core->getQueries()->updateUsersRotatingSection($i + 1, $update_users);
        }

        $this->core->addSuccessMessage("Rotating sections setup");
        $this->core->redirect($return_url);
    }
}
