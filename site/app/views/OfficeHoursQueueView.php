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

    private function renderPart($viewer, $twig_location) {
        return $this->core->getOutput()->renderTwigTemplate($twig_location, [
          'csrf_token' => $this->core->getCsrfToken(),
          'viewer' => $viewer,
          'base_url' => $this->core->buildCourseUrl() . '/office_hours_queue'
        ]);
    }

    public function showQueueStats($viewer) {
        $this->core->getOutput()->addBreadcrumb("Office Hours/Lab Queue");
        $this->core->getOutput()->addBreadcrumb("Statistics");
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));

        return $this->renderPart($viewer, "officeHoursQueue/QueueStats.twig");
    }

}
