<?php

namespace app\views\admin;

use app\libraries\FileUtils;
use app\views\AbstractView;

class ReportView extends AbstractView {
    public function showReportUpdates($json) {
        $this->core->getOutput()->addBreadcrumb('Grade Reports');
        $this->core->getOutput()->addInternalCss('grade-report.css');
        $this->core->getOutput()->addInternalCss('highlightjs/atom-one-light.css');
        $this->core->getOutput()->addInternalCss('highlightjs/atom-one-dark.css');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('highlight.js', 'highlight.min.js'));
        $this->core->getOutput()->addInternalJs('markdown-code-highlight.js');
        return $this->core->getOutput()->renderTwigTemplate("admin/Report.twig", [
            'rainbow_grades_customization_url' => $this->core->buildCourseUrl(['reports', 'rainbow_grades_customization']),
            'csrfToken' => $this->core->getCsrfToken(),
        ]);
    }

    /**
     * @param array<string, array{file: string, label: string}> $available_sorts
     */
    public function showFullGradebook($grade_file, string $grade_summaries_last_run, array $available_sorts = [], string $selected_sort = 'overall', bool $show_warning = false, ?int $days_since_run = null) {
        $this->core->getOutput()->addBreadcrumb('Gradebook');
        $this->core->getOutput()->addInternalCss('rainbow-grades.css');

        return $this->core->getOutput()->renderTwigTemplate("submission/RainbowGrades.twig", [
            "show_summary" => $grade_file !== null,
            "grade_file" => $grade_file,
            "extra_label" => "For All Students",
            "grade_summaries_last_run" => $grade_summaries_last_run,
            'rainbow_grades_csv_url' => $this->core->buildCourseUrl(['reports', 'rainbow_grades_csv']),
            'show_warning' => $show_warning,
            'days_since_run' => $days_since_run,
            'available_sorts' => $available_sorts,
            'selected_sort' => $selected_sort,
            'gradebook_url' => $this->core->buildCourseUrl(['gradebook']),
        ]);
    }
}
