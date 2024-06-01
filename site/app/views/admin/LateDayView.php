<?php

namespace app\views\admin;

use app\models\User;
use app\views\AbstractView;
use app\libraries\Utils;
use app\libraries\FileUtils;
use app\models\gradeable\LateDays;
use app\models\gradeable\LateDayInfo;

class LateDayView extends AbstractView {
    public function displayLateDays($users, $students, $initial_late_days) {
        $this->core->getOutput()->addInternalCss('exceptionforms.css');
        $this->core->getOutput()->addInternalCss('latedaystableplugin.css');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalCss('simple-grading.css');
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

    /**
     * @param User[] $students
     * @param int $initial_late_days
     * @return string
     */
    public function displayLateDayCache($students, $initial_late_days) {
        $this->core->getOutput()->addInternalCss('details.css');
        $this->core->getOutput()->addInternalCss('exceptionforms.css');
        $this->core->getOutput()->addInternalCss('simple-grading.css');
        $this->core->getOutput()->addInternalCss('admin-gradeable.css');
        $this->core->getOutput()->addInternalCss('latedaystableplugin.css');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalJs('latedays.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->addBreadcrumb('Bulk Late Days');
        $this->core->getOutput()->enableMobileViewport();

        $student_full = Utils::getAutoFillData($students);
        $view_all = isset($_GET['view']) && $_GET['view'] === 'all';

        $late_day_cache = $this->core->getQueries()->getLateDayCache();
        $late_day_update_dates = $this->core->getQueries()->getLateDayUpdateTimestamps();
        $last_late_day_events = $this->core->getQueries()->getLastLateDayUpdatesForUsers();
        $gradeables_iterator = $this->core->getQueries()->getGradeableConfigs(null, ['submission_due_date', 'grade_released_date', 'g_id']);

        $gradeables = [];
        foreach ($gradeables_iterator as $g) {
            if (!LateDays::filterCanView($this->core, $g)) {
                continue;
            }
            $gradeables[] = $g;
        }

        // sort gradeables by date and g_id
        usort($gradeables, function ($g1, $g2) {
            $diff = 0;
            if ($g1->getSubmissionDueDate() !== null && $g2->getSubmissionDueDate() !== null) {
                $diff = $g1->getSubmissionDueDate()->getTimestamp() - $g2->getSubmissionDueDate()->getTimestamp();

                if ($diff === 0) {
                    $diff = strcmp($g1->getId(), $g2->getId());
                }
            }
            elseif ($g1->getSubmissionDueDate() !== null) {
                $diff = 1;
            }
            elseif ($g2->getSubmissionDueDate() !== null) {
                $diff = -1;
            }
            return $diff;
        });

        $lastest_gradeable = end($gradeables);
        if (end($gradeables) && end($gradeables)->getSubmissionDueDate() !== null) {
            $lastest_gradeable_date = end($gradeables)->getSubmissionDueDate();
            // if the last gradeable assigned is after the latest late day update for the user, replace it
            foreach ($students as $s) {
                if (!isset($last_late_day_events[$s->getId()]) || $last_late_day_events[$s->getId()] <= $lastest_gradeable_date) {
                    $last_late_day_events[$s->getId()] = end($gradeables)->getTitle();
                }
            }
        }

        // Create the late day header in order of event date
        $late_day_cache_header = [];
        $update = 0;
        $g = 0;

        while ($update < count($late_day_update_dates) || $g < count($gradeables)) {
            if ($update >= count($late_day_update_dates)) {
                $late_day_cache_header[] = $gradeables[$g]->getTitle();
                $g++;
            }
            elseif ($g >= count($gradeables)) {
                $late_day_cache_header[] = $late_day_update_dates[$update];
                $update++;
            }
            elseif ($late_day_update_dates[$update] <= $gradeables[$g]->getSubmissionDueDate()) {
                $late_day_cache_header[] = $late_day_update_dates[$update];
                $update++;
            }
            else {
                $late_day_cache_header[] = $gradeables[$g]->getTitle();
                $g++;
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("admin/late_day_cache/LateDayCacheBase.twig", [
            "student_full" => $student_full,
            "students" => $students,
            "initial_late_days" => $initial_late_days,
            "flush_cache_url" => $this->core->buildCourseUrl(['bulk_late_days', 'flush']),
            "calculate_cache_url" => $this->core->buildCourseUrl(['bulk_late_days', 'calculate']),
            "cache_url" => $this->core->buildCourseUrl(['bulk_late_days']),
            "view_all" => $view_all,
            "status_to_simple_message" => LateDayInfo::getSimpleMessageFromSatus(),
            "late_day_cache_header" => $late_day_cache_header,
            "last_late_day_events" => $last_late_day_events,
            "late_day_cache" => $late_day_cache,
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}
