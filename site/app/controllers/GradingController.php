<?php

namespace app\controllers;

use app\controllers\grading\ElectronicGraderController;
use app\controllers\grading\SimpleGraderController;
use app\controllers\grading\TeamListController;


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
    * pass ishidden as true if a student can view their own. (e.g. set it to true
    * on pages with hidden testcases to recieve a false from this function when the 
    * student owns this gradeable.)
    */
    protected function canIViewThis($req_gradeable){
        
        //admins and full access graders can see everything.
        if($this->core->getUser()->accessAdmin() || $this->core->getUser()->accessFullGrading()){
            return true;
        }


        //If a user is a limited access grader, and the gradeable is being graded, and the
        // gradeable can be viewed by limited access graders.
        //TODO: When Peer grading is properly implemented, add a check that sees if the gradeable
        //belongs to someone this grader has been assigned to.
        if(($this->core->getUser()->accessGrading()) && ($gradeable->getTAViewDate() <= $this->now)  
            && ($this->core->getUser()->getGroup() <= $req_gradeable->getMinimumGradingGroup())) {
            return true;
        }

        //If you are not an full access grader or a access grader with appropriate permissions, you cannot view the object.
        return false;
    }
}
