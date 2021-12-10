<?php

namespace app\views\admin;

use app\views\AbstractView;

class ReportView extends AbstractView {
    public function showReportUpdates($grade_summaries_last_run, $json) {
        $this->core->getOutput()->addBreadcrumb('Grade Reports');
        $this->core->getOutput()->addInternalCss('grade-report.css');
        $this->core->getOutput()->addInternalModuleJs('grade-report.js');
        $this->core->getOutput()->addInternalCss('highlightjs/atom-one-light.css');
        $this->core->getOutput()->addInternalCss('highlightjs/atom-one-dark.css');
        return $this->core->getOutput()->renderTwigTemplate("admin/Report.twig", [
            'summaries_url' => $this->core->buildCourseUrl(['reports', 'summaries']),
            'csv_url' => $this->core->buildCourseUrl(['reports', 'csv']),
            'rainbow_grades_customization_url' => $this->core->buildCourseUrl(['reports', 'rainbow_grades_customization']),
            'customization_upload_url' => $this->core->buildCourseUrl(['reports', 'rainbow_grades_customization', 'upload']),
            'csrfToken' => $this->core->getCsrfToken(),
            'json' => $json,
            'grade_summaries_last_run' => $grade_summaries_last_run,
        ]);
    }

    public function showFullGradebook($grade_file) {
        $this->core->getOutput()->addBreadcrumb('Gradebook');
        $this->core->getOutput()->addInternalCss('rainbow-grades.css');

        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();

        return $this->core->getOutput()->renderTwigTemplate("submission/RainbowGrades.twig", [
            "show_summary" => $display_rainbow_grades_summary && $grade_file !== null,
            "grade_file" => $grade_file,
            "extra_label" => "For All Students"
        ]);
    }
}
