<?php

namespace app\controllers\admin;

use app\controllers\IController;
use app\libraries\Core;
use app\libraries\Output;

class UsersController implements IController {
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        switch ($_REQUEST['action']) {
            case 'update_student':
                $this->userForm(true);
                break;
            case 'update_user':
                $this->userForm(true);
                break;
            case 'add_student':
                $this->userForm(false, true);
                break;
            case 'add_user':
                $this->userForm(false, false);
                break;
            case 'users':
                break;
            case 'students':
                $this->listStudents();
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller");
                break;
        }
    }

    public function listStudents() {
        $students = $this->core->getQueries()->getAllStudents();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'listStudents', $students);
    }

    public function userForm($edit_user = false, $student = true) {
        $action = ($student) ? "students" : "users";

        $groups = $this->core->getQueries()->getAllGroups();
        $sections = $this->core->getQueries()->getAllCourseSections();

        $default_group = ($student) ? 1 : 3;
        $default_section = $sections[0]['section_number'];
        foreach ($sections as $section) {
            if ($section['section_is_enabled']) {
                $default_section = $section['section_number'];
                break;
            }
        }

        $user = null;
        if ($edit_user) {
            if (!isset($_REQUEST['user_id'])) {
                $_SESSION['messages']['error'][] = "Invalid requested user id for editing";
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                                  'page' => 'users',
                                                                  'action' => $action)));
            }

            $user = $this->core->getQueries()->getUserById($_REQUEST['user_id']);
            if (empty($user)) {
                $_SESSION['messages']['error'][] = "Invalid requested user id for editing";
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                                  'page' => 'users',
                                                                  'action' => $action)));
            }
        }
        else {
            $user = array(
                'user_id' => "",
                'user_firstname' => "",
                'user_lastnaem' => "",
                'user_email' => "",
                'user_group' => $default_group,
                'user_course_section' => $default_section,
                'user_assignment_section' => 1
            );
        }

        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'userForm', $user, $groups, $sections);
    }
}