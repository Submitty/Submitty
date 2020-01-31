<?php

namespace app\views;

use app\models\User;
use app\models\OfficeHoursQueueViewer;

class OfficeHoursQueueView extends AbstractView {

    public function showTheQueue($viewer) {
        $this->core->getOutput()->addBreadcrumb("Office Hours Queue");
        $this->core->getOutput()->renderTwigOutput("OfficeHoursQueue.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'viewer' => $viewer,
            'base_url' => $this->core->buildCourseUrl() . '/office_hours_queue'
        ]);
    }
}
