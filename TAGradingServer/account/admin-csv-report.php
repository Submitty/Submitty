<?php
include "../header.php";
check_administrator();

print <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <form action="{$BASE_URL}/account/ajax/admin-csv-report.php?course={$_GET['course']}&semester={$_GET['semester']}" method="POST">
            <input type="hidden" name="csrf_token" value="{$_SESSION['csrf']}" />
            <div class="modal-header">
                <h3 id="myModalLabel">Generate CSV Report</h3>
            </div>
            <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
                Generate a CSV report for all homeworks, labs, and tests. Students without a grade for a lab or
                test will just get a 0 for it, while for homeworks without grades will be blank.
            </div>
            <div class="modal-footer">
                <div style="width:50%; float:right; margin-top:5px;">
                    <input class="btn btn-primary" type="submit" value="Generate CSV Report"/>
                </div>
            </div>
        </form>
    </div>
</div>
HTML;

include "../footer.php";