<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\Utils;
use app\libraries\FileUtils;

class LateDayView extends AbstractView {
    public function displayLateDays($users, $students, $initial_late_days) {
        $this->core->getOutput()->addInternalCss('exceptionforms.css');
        $this->core->getOutput()->addInternalCss('latedaystableplugin.css');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalJs('latedays.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->addBreadcrumb('Late Days Allowed');
        $this->core->getOutput()->enableMobileViewport();

        $student_full = Utils::getAutoFillData($students);

        return $this->core->getOutput()->renderTwigTemplate("admin/LateDays.twig", [
            "users" => $users,
            "student_full" => $student_full,
            "initial_late_days" => $initial_late_days,
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}
