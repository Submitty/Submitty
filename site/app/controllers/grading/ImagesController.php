<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\models\user;

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
        if ($user_group === USER::GROUP_STUDENT || (($user_group === USER::GROUP_FULL_ACCESS_GRADER || $user_group === USER::GROUP_LIMITED_ACCESS_GRADER) && count($any_images_files) === 0)) { // student has no permissions to view image page
            $this->core->addErrorMessage("You have no permissions to see images.");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading', 'page' => 'images', 'action' => 'view_images_page')));
            return;
        }
        $grader_sections = $this->core->getUser()->getGradingRegistrationSections();

        //limited-access graders with no assigned sections have no permissions to view images
        if ($user_group === USER::GROUP_LIMITED_ACCESS_GRADER && empty($grader_sections)) {
            $this->core->addErrorMessage("You have no assigned sections and no permissions to see images.");
            return;
        }

        if ($user_group !== USER::GROUP_LIMITED_ACCESS_GRADER)
            $grader_sections = array();  //reset grader section to nothing so permission for every image
        else {
            if (empty($grader_sections)) {
                return;
            }
        }
        $instructor_permission = ($user_group === USER::GROUP_INSTRUCTOR);
        $students = $this->core->getQueries()->getAllUsers();
        $this->core->getOutput()->disableBuffer();
        $this->core->getOutput()->renderOutput(array('grading', 'Images'), 'listStudentImages', $students, $grader_sections, $instructor_permission);
    }
}
