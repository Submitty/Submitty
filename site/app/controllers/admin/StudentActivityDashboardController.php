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
   * @Route("/courses/{_semester}/{_course}/sad", methods={"GET"})
   * @AccessControl(role="INSTRUCTOR")
   */
   public function getStudents(){
        $data_dump = $this->core->getQueries()->getAttendanceInfo();
        //var_dump($data_dump);
        return new WebResponse([
             'admin',
             'StudentActivityDashboard'
        ], 'createTable', $data_dump);
   }
}