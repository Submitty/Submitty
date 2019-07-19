<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\DateUtils;
use app\libraries\FileUtils;

class GradeOverrideController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']){
            case 'view_overriden_grades':
                $this->viewOverridenGrades();
                $this->core->getOutput()->addBreadcrumb('Grades Override');
                break;
            case 'update':
                $this->update(false);
                break;
            case 'delete_grades':
                $this->update(true);
                break;
            case 'get_overriden_grades':
                $this->getOverridenGrades($_REQUEST['g_id']);
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller");
                break;
        }
    }

    public function viewOverridenGrades() {
        $gradeables = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        $this->core->getOutput()->renderOutput(array('admin','GradeOverride'), 'displayOverridenGrades', $gradeables);
    }

    public function getOverridenGrades($g_id) {
        $users = $this->core->getQueries()->getUsersWithOverridenGrades($g_id);
        $user_table = array();
        foreach($users as $user){
            $user_table[] = array('user_id' => $user->getId(),'user_firstname' => $user->getDisplayedFirstName(), 'user_lastname' => $user->getDisplayedLastName(), 'marks' => $user->getMarks(), 'comment' => $user->getComment());
        }
        return $this->core->getOutput()->renderJsonSuccess(array(
            'gradeable_id' => $g_id,
            'users' => $user_table,
        )); 
    }

    public function update($delete) {
        if($delete){
            $this->core->getQueries()->deleteOverridenGrades($_POST['user_id'], $_POST['g_id']);
            $this->getOverridenGrades($_POST['g_id']);
        } else {
        if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $error = "Invalid CSRF token. Try again.";
            return $this->core->getOutput()->renderJsonFail($error);
        }
        if ((!isset($_POST['g_id']) || $_POST['g_id'] == "" )) {
            $error = "Please choose a gradeable_id";
            return $this->core->getOutput()->renderJsonFail($error);
        }
        $user = $this->core->getQueries()->getSubmittyUser($_POST['user_id']);
        $isUserNotInCourse = empty($this->core->getQueries()->getUsersById(array($_POST['user_id'])));
        if (!isset($_POST['user_id']) || $_POST['user_id'] == "" || $isUserNotInCourse || $user->getId() !== $_POST['user_id']) {
            $error = "Invalid Student ID";
            return $this->core->getOutput()->renderJsonFail($error);
        }
        
        if (((!isset($_POST['marks'])) || $_POST['marks'] == ""  || is_float($_POST['marks'])) ) {
            $error = "Marks be a integer";
            return $this->core->getOutput()->renderJsonFail($error);
        }
        
        $this->core->getQueries()->updateGradeOverride($_POST['user_id'], $_POST['g_id'], $_POST['marks'], $_POST['comment']);
        $this->getOverridenGrades($_POST['g_id']);
        }
    
    }
}