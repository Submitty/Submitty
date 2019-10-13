<?php

namespace app\views;

use app\models\User;
use app\Models\OfficeHoursQueueStudent;
use app\Models\OfficeHoursQueueInstructor;

class OfficeHoursQueueView extends AbstractView {
    public function showQueueStudent(OfficeHoursQueueStudent $oh_queue) {
        /*$this->core->getOutput()->addBreadcrumb("Notifications");
        $this->core->getOutput()->addInternalCss('notifications.css');*/
        $this->core->getOutput()->renderTwigOutput("OfficeHoursQueueStudent.twig",[
          'csrf_token' => $this->core->getCsrfToken(),
          'add_url' => $this->core->buildCourseUrl(["OfficeHoursQueue/add"]),
          'remove_url' => $this->core->buildCourseUrl(["OfficeHoursQueue/remove"]),
          'oh_queue' => $oh_queue
        ]);
    }

    public function showQueueInstructor(OfficeHoursQueueInstructor $oh_queue){

      $this->core->getOutput()->renderTwigOutput("OfficeHoursQueueInstructor.twig",[
        'csrf_token' => $this->core->getCsrfToken(),
        'entries' => $oh_queue->getEntries(),
        'entries_helped' => $oh_queue->getEntriesHelped(),
        'num_in_queue' => count($oh_queue->getEntries()),
        'remove_url' => $this->core->buildCourseUrl(["OfficeHoursQueue/remove"]),
        'start_help_url' => $this->core->buildCourseUrl(["OfficeHoursQueue/startHelp"]),
        'finish_help_url' => $this->core->buildCourseUrl(["OfficeHoursQueue/finishHelp"])
      ]);
    }
}
