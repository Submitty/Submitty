<?php

namespace app\views;

use app\models\User;
use app\models\OfficeHoursQueueModel;

class OfficeHoursQueueView extends AbstractView {

    public function showTheQueue($viewer) {
        $this->core->getOutput()->addBreadcrumb("Office Hours Queue");
        $this->core->getOutput()->addInternalCss('officeHoursQueue.css');
        $this->core->getOutput()->enableMobileViewport();

        $output = $this->renderPart($viewer, "officeHoursQueue/QueueHeader.twig");
        $output .= $this->renderPart($viewer, "officeHoursQueue/FilterQueues.twig");
        $output .= $this->renderPart($viewer, "officeHoursQueue/CurrentQueue.twig");
        $output .= $this->renderPart($viewer, "officeHoursQueue/QueueHistory.twig");
        $output .= $this->renderPart($viewer, "officeHoursQueue/QueueFooter.twig");

        return $output;
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

    private function renderPart($viewer, $twig_location) {
        return $this->core->getOutput()->renderTwigTemplate($twig_location, [
          'csrf_token' => $this->core->getCsrfToken(),
          'viewer' => $viewer,
          'base_url' => $this->core->buildCourseUrl() . '/office_hours_queue'
        ]);
    }
}
