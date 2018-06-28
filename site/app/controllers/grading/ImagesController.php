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
        $grader_sections = $this->core->getUser()->getGradingRegistrationSections();
        $user_group = $this->core->getUser()->getGroup();
        if ($user_group !== 3) //3 means limited access grader (mentor)
            $grader_sections = array();  //reset grader section to nothing so permission for every image
        $instructor_permission = ($user_group === 1);
        $students = $this->core->getQueries()->getAllUsers();
        $this->core->getOutput()->renderOutput(array('grading', 'Images'), 'listStudentImages', $students, $grader_sections, $instructor_permission);
    }
}
