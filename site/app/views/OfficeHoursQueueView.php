<?php

namespace app\views;

use app\models\User;

class OfficeHoursQueueView extends AbstractView {
    public function showQueue() {
        /*$this->core->getOutput()->addBreadcrumb("Notifications");
        $this->core->getOutput()->addInternalCss('notifications.css');*/
        $this->core->getOutput()->renderTwigOutput("OfficeHoursQueue.twig");
    }
}
