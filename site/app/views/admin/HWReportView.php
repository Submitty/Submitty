<?php
namespace app\views\admin;

use app\views\AbstractView;

class HWReportView extends AbstractView {
    public function showHWReport() {
        $return = "";
        $return .= <<<HTML
<div class="content">
    <table>
        <tbody>
            <tr>
                <td width="50%">
                    <p>Pushing this button will update or create Homework Reports for all students for all Gradeables, regardless of grades. This happens on an individual basis whenever a TA or instructor enters a grade for a student. This action shouldn't be necessary, except to update late day information that effects other electronic gradeables.
                    </p>
                </td>
                <td width="5%"> </td>
                <td width="45%" style="position:relative">
                    <form method="post" action="{$this->core->getConfig()->getTABaseUrl()}/account/ajax/admin-hw-report.php?course={$_GET['course']}&semester={$_GET['semester']}">
                        <input type="hidden" value="{$_SESSION['csrf']}">
                        <button type="submit" class="btn btn-primary" style="width:100%;position:absolute;top:50%;transform:translate(0,-50%);">Generate Homework Reports</button>
                    </form>
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
                    <button class="btn btn-primary" style="width:100%;position:absolute;top:50%;transform:translate(0,-50%);">Generate Grade Summaries</button>
                </td>
            </tr>
        <tbody>
    </table>
</div>
HTML;
        return $return;
    }
}




?>