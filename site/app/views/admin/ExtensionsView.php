<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\Utils;
use app\libraries\FileUtils;

class ExtensionsView extends AbstractView {
    public function displayExtensions($gradeables) {
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->addSelect2WidgetCSSAndJs();
        $this->core->getOutput()->addInternalCss('exceptionforms.css');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalJs('extensions.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->addBreadcrumb('Excused Absence Extensions');
        $this->core->getOutput()->enableMobileViewport();

        $students = $this->core->getQueries()->getAllUsers();
        $student_full = Utils::getAutoFillData($students);
        $current_gid = isset($_COOKIE['exception_gid']) ? $_COOKIE['exception_gid'] : null;
        // get gradeable with matching gid
        $g_key = array_search($current_gid, array_column($gradeables, 'g_id'));
        $current_gradeable = $g_key === false ? null : $gradeables[$g_key];

        $users = $this->core->getQueries()->getUsersWithExtensions($current_gid);
        $current_exceptions = [];
        foreach ($users as $user) {
            $current_exceptions[] = ['user_id' => $user->getId(),
                                          'user_givenname' => $user->getDisplayedGivenName(),
                                          'user_familyname' => $user->getDisplayedFamilyName(),
                                          'late_day_exceptions' => $user->getLateDayExceptions(),
                                          'reason_for_exception' => $user->getReasonForException()];
        }
        if (empty($current_exceptions)) {
            $current_exceptions = null;
        }

        return $this->core->getOutput()->renderTwigTemplate("admin/Extensions.twig", [
            "gradeables" => $gradeables,
            "student_full" => $student_full,
            "current_gradeable" => $current_gradeable,
            "current_exceptions" => $current_exceptions,
            "reasons" => [
                "illness",
                "interview",
                "travel",
                "personal issue",
            ],
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}
