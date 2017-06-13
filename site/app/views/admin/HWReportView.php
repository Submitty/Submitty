<?php
namespace app\views;

class HWReportView extends AbstractView {
    public function showHWReport() {
        $return = "";
        $return .= <<<HTML
<div class="contents">
    <p>Pushing the button below will update or create Homework Reports for all students for all Gradeables, regardless of grades. This happens on an individual basis whenever a TA or instructor enters a grade for a student. This action shouldn't be necessary, except to update late day information that effects other electronic gradeables.
    </p>
    <button class="btn btn-primary">Generate Homework Reports </button>
</div>
HTML;
        
    }
}




?>