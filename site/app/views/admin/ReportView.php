<?php
namespace app\views\admin;

use app\views\AbstractView;

class ReportView extends AbstractView {
    public function showReportUpdates() {
        $return = "";
        $return .= <<<HTML
<div class="content">
    <table>
        <tbody>
            <tr>
                <td width="50%">
                    <p>Pushing this button will update or create Reports for all students for all Gradeables, regardless of grades. This happens on an individual basis whenever a TA or instructor enters a grade for a student. This action shouldn't be necessary, except to update late day information that effects other electronic gradeables.
                    </p>
                </td>
                <td width="5%"> </td>
                <td width="45%" style="position:relative">
                    <button onclick="location.href='{$this->core->buildUrl(array('component' => 'admin', 'page' => 'reports', 'action' => 'hwreport'))}'" class="btn btn-primary" style="width:100%;position:absolute;top:50%;transform:translate(0,-50%);">Generate Gradeable Reports</button>
                <td>
            </tr>
            <tr class="bar"></tr>
            <tr class="bar"></tr>
            <tr>
                <td width="50%">
                    <p> Pushing this button will update the grade summary data used to generate the rainbow grades reports, for all students in the class.
                    </p>
                </td>
                <td width="5%"> </td>
                <td width="45%" style="position:relative">
                    <button onclick="location.href='{$this->core->buildUrl(array('component' => 'admin', 'page' => 'reports', 'action' => 'summary'))}'" class="btn btn-primary" style="width:100%;position:absolute;top:50%;transform:translate(0,-50%);">Generate Grade Summaries</button>
                </td>
            </tr>
            <tr class="bar"></tr>
            <tr class="bar"></tr>
            <tr>
                <td width="50%">
                    <p>Pushing this button will generate a CSV file, with all grades for all gradeables. </p>
                </td>
                <td width="5%"> </td>
                <td width="45%" style="position:relative">
                    <button onclick="location.href='{$this->core->buildUrl(array('component' => 'admin', 'page' => 'reports', 'action' => 'csv'))}'" class="btn btn-primary" style="width:100%;position:absolute;top:50%;transform:translate(0,-50%);">Generate CSV Reports</button>
                </td>
            </tr>
        <tbody>
    </table>
</div>
HTML;
        return $return;
    }
}
