<?php

namespace app\views;

use app\models\User;
use app\models\OfficeHoursQueueViewer;

class OfficeHoursQueueView extends AbstractView {

    public function showTheQueue($viewer) {
        $this->core->getOutput()->addBreadcrumb("Office Hours Queue");

        $output = $this->renderPart($viewer, "officeHoursQueue/QueueHeader.twig");
        $output .= $this->renderPart($viewer, "officeHoursQueue/FilterQueues.twig");
        $output .= $this->renderPart($viewer, "officeHoursQueue/CurrentQueue.twig");
        $output .= $this->renderPart($viewer, "officeHoursQueue/QueueHistory.twig");
        $output .= $this->renderPart($viewer, "officeHoursQueue/QueueFooter.twig");

        return $output;
    }

    private function renderPart($viewer, $twig_location){
      return $this->core->getOutput()->renderTwigTemplate($twig_location, [
          'csrf_token' => $this->core->getCsrfToken(),
          'viewer' => $viewer,
          'base_url' => $this->core->buildCourseUrl() . '/office_hours_queue'
      ]);
    }
}
