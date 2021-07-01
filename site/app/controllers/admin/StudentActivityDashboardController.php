<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\response\WebResponse;
use app\libraries\FileUtils;
use app\libraries\DateUtils;

/**
 * Class StudentActivityDashboardController
 * @package app\controllers\admin
 */

class StudentActivityDashboardController extends AbstractController {
  /**
   * @Route("/courses/{_semester}/{_course}/activity", methods={"GET"})
   * @AccessControl(role="INSTRUCTOR")
   */
    public function getStudents() {
        $data_dump = $this->core->getQueries()->getAttendanceInfo();
        // Convert the time stamp to the user's timezone
        foreach ($data_dump as &$row) {
            if ($row['gradeable_submission'] != null) {
                $row['gradeable_submission'] = DateUtils::convertTimeStamp($this->core->getUser(), $row['gradeable_submission'], 'Y-m-d H:i:s');
            }
            if ($row['forum_view'] != null) {
                $row['forum_view'] = DateUtils::convertTimeStamp($this->core->getUser(), $row['forum_view'], 'Y-m-d H:i:s');
            }
            if ($row['forum_post'] != null) {
                $row['forum_post'] = DateUtils::convertTimeStamp($this->core->getUser(), $row['forum_post'], 'Y-m-d H:i:s');
            }
            if ($row['gradeable_access'] != null) {
                $row['gradeable_access'] = DateUtils::convertTimeStamp($this->core->getUser(), $row['gradeable_access'], 'Y-m-d H:i:s');
            }
            if ($row['office_hours_queue'] != null) {
                $row['office_hours_queue'] = DateUtils::convertTimeStamp($this->core->getUser(), $row['office_hours_queue'], 'Y-m-d H:i:s');
            }
        }
        return new WebResponse([
            'admin',
            'StudentActivityDashboard'
        ], 'createTable', $data_dump);
    }

   /**
    * @Route("/courses/{_semester}/{_course}/activity/download", methods={"GET"})
    * @AccessControl(role="INSTRUCTOR")
    */
    public function downloadData() {
        $data_dump = $this->core->getQueries()->getAttendanceInfo();
        $file_url = FileUtils::joinPaths(
            $this->core->getConfig()->getCoursePath(),
            'tmp'
        );

        if (!FileUtils::createDir($file_url)) {
            return;
        }

        $file_url = FileUtils::joinPaths(
            $file_url,
            'Student_Activity.csv'
        );

        $fp = fopen($file_url, 'w');
        fputcsv($fp, ["Registration Section", "User ID", "First Name", "Last Name", "Gradeable Access Date", "Gradeable Submission Date",
            "Forum View Date", "Number of Poll Responses", "Office Hours Queue Date"]);
        foreach ($data_dump as $rows) {
            fputcsv($fp, $rows);
        }

        return new WebResponse([
            'admin',
            'StudentActivityDashboard'
        ], 'downloadFile', $file_url, $fp);
    }
}
