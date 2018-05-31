<?php

namespace app\controllers;

use app\controllers\grading\ElectronicGraderController;
use app\controllers\grading\SimpleGraderController;
use app\controllers\grading\TeamListController;
use app\libraries\DateUtils;


class GradingController extends AbstractController {
    
    public function run() {
        $controller = null;
        switch ($_REQUEST['page']) {
            case 'simple':
                $controller = new SimpleGraderController($this->core);
                break;
            case 'electronic':
                $controller = new ElectronicGraderController($this->core);
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller ".get_class($this));
                break;
        }
        $controller->run();
    }

    /**
    * Returns true only if a user is authorized to view the ENTIRETY of a gradeable.
    *
    * Given a gradeable, determine whether or not the current user can view it. 
    * If hidden is true, a regular users cannot view their own files.
    */
    protected function canIViewThis($req_gradeable, $who_id, $hidden=False){
        

        //admins and full access graders can see everything.
        if($this->core->getUser()->accessAdmin() || $this->core->getUser()->accessFullGrading()){
            return true;
        }

        $now = new \DateTime("now", $this->core->getConfig()->getTimezone());

        //If a user is a limited access grader, and the gradeable is being graded, and the
        // gradeable can be viewed by limited access graders.
        if(($this->core->getUser()->accessGrading()) && ($req_gradeable->getGradeStartDate() <= $now) &&
                ($this->core->getUser()->getGroup() <= $req_gradeable->getMinimumGradingGroup())) {
            //Check to see if the requested user is assigned to this grader.
            if ($req_gradeable->isGradeByRegistration()) {
                   $sections = $this->core->getUser()->getGradingRegistrationSections();
                   $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            }
            else {
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id,
                   $this->core->getUser()->getId());
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
            foreach($students as $student) {
                if($student->getId() === $who_id){
                    return true;
                }
            }
        }

        //If this item belongs to me, then I can view it.
        if($this->core->getUser()->getId() === $who_id && !$hidden){
            return true;
        }

        //If you are not an full access grader or a access grader with appropriate permissions, you cannot view the object.
        return false;
    }
}
