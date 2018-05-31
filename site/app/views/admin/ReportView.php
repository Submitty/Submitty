<?php
namespace app\views\admin;

use app\views\AbstractView;

class ReportView extends AbstractView {
    public function showReportUpdates() {
        return $this->core->getOutput()->renderTwigTemplate("admin/Report.twig");
    }
}
