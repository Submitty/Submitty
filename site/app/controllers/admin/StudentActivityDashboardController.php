<?php

namespace app\controllers\admin;
use app\controllers\AbstractController;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\response\WebResponse;

/**
 * Class StudentActivityDashboardController
 * @package app\controllers\admin
 * 

 */

class StudentActivityDashboardController extends AbstractController {
  /**
   * @Route("/courses/{_semester}/{_courses}/sad", methods={"GET"})
   * @AccessControl(role="INSTRUCTOR")
   */
   public function getStudents(){
        $students = $this->core->getQueries()->getAttendanceInfo();
        return new WebResponse([
             'admin',
             'StudentActivityDashboard'
        ], 'createTable', $students);
   }
}