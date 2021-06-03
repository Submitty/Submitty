<?php

namespace app\views;

use app\models\User;
use app\models\OfficeHoursQueueModel;
use app\libraries\FileUtils;
use app\libraries\Utils;

class OfficeHoursQueueView extends AbstractView {

    public function showTheQueue($viewer) {
        $this->core->getOutput()->addBreadcrumb("Office Hours Queue");
        $this->core->getOutput()->addInternalCss('officeHoursQueue.css');
        $this->core->getOutput()->addInternalJs('officeHoursQueue.js');
        $this->core->getOutput()->addInternalJs('websocket.js');
        $this->core->getOutput()->addInternalJs('notification-sound.js');
        $this->core->getOutput()->enableMobileViewport();

        return $this->renderPart($viewer, "officeHoursQueue/QueueHeader.twig");
    }

    public function renderCurrentQueue($viewer) {
        return $this->renderPart($viewer, "officeHoursQueue/CurrentQueue.twig");
    }

    public function renderQueueHistory($viewer) {
        return $this->renderPart($viewer, "officeHoursQueue/QueueHistory.twig");
    }

    public function renderNewStatus($viewer) {
        return $this->renderPart($viewer, "officeHoursQueue/QueueStatus.twig");
    }

    public function showQueueStats($overallData, $todayData, $weekDayThisWeekData, $weekDayData, $queueData, $weekNumberData): string {
        $this->core->getOutput()->addBreadcrumb("Office Hours/Lab Queue", $this->core->buildCourseUrl(["office_hours_queue"]));
        $this->core->getOutput()->addBreadcrumb("Statistics");
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate('officeHoursQueue/QueueStats.twig', [
          'csrf_token' => $this->core->getCsrfToken(),
          'access_full_grading' => $this->core->getUser()->accessFullGrading(),
          'overall_data' => $overallData,
          'today_data' => $todayData,
          'week_day_this_week_data' => $weekDayThisWeekData,
          'week_day_data' => $weekDayData,
          'queue_data' => $queueData,
          'week_number_data' => $weekNumberData,
          'viewer' => new OfficeHoursQueueModel($this->core),
          'base_url' => $this->core->buildCourseUrl() . '/office_hours_queue'
        ]);
    }

    public function showQueueStudentStats($studentData) {
        $this->core->getOutput()->addBreadcrumb("Office Hours/Lab Queue", $this->core->buildCourseUrl(["office_hours_queue"]));
        $this->core->getOutput()->addBreadcrumb("Statistics");
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("officeHoursQueue/QueueStatsStudents.twig", [
          'csrf_token' => $this->core->getCsrfToken(),
          'access_full_grading' => $this->core->getUser()->accessFullGrading(),
          'student_data' => $studentData,
          'viewer' => new OfficeHoursQueueModel($this->core),
          'base_url' => $this->core->buildCourseUrl() . '/office_hours_queue'
        ]);
    }

    public function previewAnnouncement($enablePreview, $content){
        $this->core->getOutput()->disableRender();
        if(!$enablePreview){
            return;
        }
        return $this->core->getOutput()->renderTwigTemplate("generic/Markdown.twig", [
                "style" => "font-family: 'Source Sans Pro', sans-serif;",
                "content" => $content
        ]);
    }

    public function renderNewAnnouncement($viewer) {
        return $this->renderPart($viewer, "officeHoursQueue/AnnouncementMsg.twig");
    }

    private function renderPart($viewer, $twig_location) {
        return $this->core->getOutput()->renderTwigTemplate($twig_location, [
          'csrf_token' => $this->core->getCsrfToken(),
          'access_full_grading' => $this->core->getUser()->accessFullGrading(),
          'viewer' => $viewer,
          'base_url' => $this->core->buildCourseUrl() . '/office_hours_queue'
        ]);
    }
}
