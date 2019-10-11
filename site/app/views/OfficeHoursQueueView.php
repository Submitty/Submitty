<?php

namespace app\views;

use app\models\User;
use app\Models\OfficeHoursQueueStudent;

class OfficeHoursQueueView extends AbstractView {
    public function showQueueStudent(OfficeHoursQueueStudent $oh_queue) {
        /*$this->core->getOutput()->addBreadcrumb("Notifications");
        $this->core->getOutput()->addInternalCss('notifications.css');*/
        $name = $oh_queue->getName();
        $position_in_queue = $oh_queue->getPositionInQueue();
        $in_queue = $oh_queue->isInQueue();
        $num_in_queue = $oh_queue->getNumInQueue();
        $time_in = $oh_queue->getTimeIn();
        $this->core->getOutput()->renderTwigOutput("OfficeHoursQueueStudent.twig",[
          'csrf_token' => $this->core->getCsrfToken(),
          'add_url' => $this->core->buildCourseUrl(["OfficeHoursQueue/add"]),
          'remove_url' => $this->core->buildCourseUrl(["OfficeHoursQueue/remove"]),
          'in_queue' => $in_queue,
          'name' => $name,
          'position_in_queue' => $position_in_queue,
          'num_in_queue' => $num_in_queue,
          'time_in' => $time_in,
          'user_name' => $name
        ]);
    }

    public function showQueueInstructor(OfficeHoursQueueInstructor $oh_queue){
      $this->core->getOutput()->renderTwigOutput("OfficeHoursQueueInstructor.twig",[
        'csrf_token' => $this->core->getCsrfToken()
      ]);
    }
}
