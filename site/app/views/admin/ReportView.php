<?php
namespace app\views\admin;

use app\views\AbstractView;

class ReportView extends AbstractView {
    public function showReportUpdates($grade_summaries_last_run) {
        $this->core->getOutput()->addBreadcrumb('Grade Reports');
        return $this->core->getOutput()->renderTwigTemplate("admin/Report.twig", [
            'summaries_url' => $this->core->buildNewCourseUrl(['reports', 'summaries']),
            'csv_url' => $this->core->buildNewCourseUrl(['reports', 'csv']),
            'rainbow_grades_customization_url' => $this->core->buildNewCourseUrl(['rainbow_grades_customization']),
            'grade_summaries_last_run' => $grade_summaries_last_run
        ]);
    }
}
