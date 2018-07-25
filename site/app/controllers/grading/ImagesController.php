<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\libraries\FileUtils;

class ImagesController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'view_images_page':
            		$this->viewImagesPage();
                break;
            default:
                $this->viewImagesPage();
                break;
        }
    }

    public function viewImagesPage() {
        $user_group = $this->core->getUser()->getGroup();
        $images_course_path = $this->core->getConfig()->getCoursePath();
        $images_path = Fileutils::joinPaths($images_course_path,"uploads/student_images");
        $any_images_files = FileUtils::getAllFiles($images_path, array(), true);
        if ($user_group === 4 || (($user_group === 2 || $user_group === 3) && count($any_images_files) === 0)) { // student has no permissions to view image page
            $this->core->addErrorMessage("You have no permissions to see images.");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading', 'page' => 'images', 'action' => 'view_images_page')));
            return;
        }
        $grader_sections = $this->core->getUser()->getGradingRegistrationSections();

        //limited-access graders with no assigned sections have no permissions to view images
        if ($user_group === 3 && empty($grader_sections)) {
            $this->core->addErrorMessage("You have no assigned sections and no permissions to see images.");
            return;
        }

        if ($user_group !== 3) //3 means limited access grader (mentor)
            $grader_sections = array();  //reset grader section to nothing so permission for every image
        else { // user_group === 3 ==> mentor
            if (empty($grader_sections)) {
                return;
            }
        }
        $instructor_permission = ($user_group === 1);
        $students = $this->core->getQueries()->getAllUsers();
        $this->core->getOutput()->renderOutput(array('grading', 'Images'), 'listStudentImages', $students, $grader_sections, $instructor_permission);
    }
}
