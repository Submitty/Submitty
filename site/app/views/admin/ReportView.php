<?php

namespace app\views\admin;

use app\views\AbstractView;

class ReportView extends AbstractView {
    public function showReportUpdates($grade_summaries_last_run) {
        $this->core->getOutput()->addBreadcrumb('Grade Reports');
        return $this->core->getOutput()->renderTwigTemplate("admin/Report.twig", [
            'summaries_url' => $this->core->buildCourseUrl(['reports', 'summaries']),
            'csv_url' => $this->core->buildCourseUrl(['reports', 'csv']),
            'rainbow_grades_customization_url' => $this->core->buildCourseUrl(['reports', 'rainbow_grades_customization']),
            'grade_summaries_last_run' => $grade_summaries_last_run,
        ]);
    }

    public function showFullGradebook($grade_file) {
        $this->core->getOutput()->addBreadcrumb('Gradebook');

        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();

        return $this->core->getOutput()->renderTwigTemplate("submission/RainbowGrades.twig", [
            "show_summary" => $display_rainbow_grades_summary && $grade_file !== null,
            "grade_file" => $grade_file,
            "extra_label" => "For All Students"
        ]);
    }
}
