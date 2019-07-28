<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class GradeOverrideController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */
class GradeOverrideController extends AbstractController {
    /**
     * @deprecated
     */
    public function run() {
        return null;
    }

    /**
     * @Route("/{_semester}/{_course}/grade_override")
     */
    public function viewOverriddenGrades() {
        $gradeables = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        $this->core->getOutput()->renderOutput(array('admin','GradeOverride'), 'displayOverriddenGrades', $gradeables);
    }

    /**
     * @Route("/{_semester}/{_course}/grade_override/{gradeable_id}")
     */
    public function getOverriddenGrades($gradeable_id) {
        $users = $this->core->getQueries()->getUsersWithOverriddenGrades($gradeable_id);
        $user_table = array();
        foreach($users as $user){
            $user_table[] = array('user_id' => $user->getId(),'user_firstname' => $user->getDisplayedFirstName(), 'user_lastname' => $user->getDisplayedLastName(), 'marks' => $user->getMarks(), 'comment' => $user->getComment());
        }
        return $this->core->getOutput()->renderJsonSuccess(array(
            'gradeable_id' => $gradeable_id,
            'users' => $user_table,
        )); 
    }

    /**
     * @Route("/{_semester}/{_course}/grade_override/{gradeable_id}/delete", methods={"POST"})
     */
    public function deleteOverriddenGrades($gradeable_id) {
        $this->core->getQueries()->deleteOverriddenGrades($_POST['user_id'], $gradeable_id);
        return $this->getOverriddenGrades($gradeable_id);
    }

    /**
     * @Route("/{_semester}/{_course}/grade_override/{gradeable_id}/update", methods={"POST"})
     */
    public function updateOverriddenGrades($gradeable_id) {
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
        
        $this->core->getQueries()->updateGradeOverride($_POST['user_id'], $gradeable_id, $_POST['marks'], $_POST['comment']);
        $this->getOverriddenGrades($gradeable_id);
    }
}