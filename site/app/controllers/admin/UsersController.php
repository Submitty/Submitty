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
            $this->core->getQueries()->updateUser($user);
            $this->core->addSuccessMessage("User '{$user->getId()}' updated");
        }
        else {
            $this->core->getQueries()->createUser($user);
            $this->core->addSuccessMessage("User '{$user->getId()}' created");
        }
        $this->core->redirect($return_url);
    }

    public function rotatingSectionsForm() {
        $non_null_counts = $this->core->getQueries()->getCountUsersRotatingSections();
        $null_counts = $this->core->getQueries()->getCountNullUsersRotatingSections();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'rotatingUserForm', $non_null_counts, $null_counts);
    }

    public function updateRotatingSections() {
        $return_url = $this->core->buildUrl(
            array('component' => 'admin',
                  'page' => 'users',
                  'action' => 'rotating_sections')
        );
        if ($this->core->checkCsrfToken()) {
            $this->core->addErrorMessage("Invalid CSRF token. Try again.");
            $this->core->redirect($return_url);
        }

        if (in_array($_REQUEST['rotating_type'], array('random', 'alphabetically'))) {
            $type = $_REQUEST['rotating_type'];
        }
        else {
            $type = 'random';
        }

        $sections = intval($_REQUEST['sections']);
        if (in_array($_REQUEST['sort_type'], array('redo', 'fewest')) && $type == "random") {
            $sort = $_REQUEST['sort_type'];
        }
        else {
            $sort = 'redo';
        }

        $max_section = $this->core->getQueries()->getMaxRotatingSection();
        $section_counts = array_fill(0, $sections, 0);
        if ($sort === 'redo') {
            $users = $this->core->getQueries()->getRegisteredOrManualStudentIds();
            if ($type === 'random') {
                shuffle($users);
            }
            $this->core->getQueries()->setAllUsersRotatingSectionNull();
            for ($i = 0; $i < count($users); $i++) {
                $section = $i % $sections;
                $section_counts[$section]++;
                if ($section > $max_section) {
                    $this->core->getQueries()->insertNewRotatingSection($section);
                }
            }
        }
        else {
            // TODO: get all users without a rotating section that need one (registered or manual)
            $users = array();
            shuffle($users);
            // TODO: get count of users in all rotating sections (ignoring null section)
            // TODO: figure out which sections requires new users to be added
            $counts_rows = $this->core->getQueries()->getCountUsersRotatingSections();
            $counts = array();
            foreach($counts_rows as $counts_row) {
                if ($counts_row['rotating_section'] === null) {
                    continue;
                }
                $counts[$counts_row['rotating_section']] = $counts_row['count'];
            }
        }

        for ($i = 0; $i < $sections; $i++) {
            $users = array_splice($users, 0, $section_counts[$i]);
            $this->core->getQueries()->updateUsersRotatingSection($i + 1, $users);
        }
    }
}