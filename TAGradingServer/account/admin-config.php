<?php

use \lib\Database;

require_once "../header.php";

check_administrator();

$settings = array();
$checked = array();
Database::query("SELECT * FROM config");
foreach(Database::rows() as $row) {
    $row['config_value'] = process_config_value($row['config_value'], $row['config_type']);
    $settings[$row['config_name']] = $row;
    if ($row['config_type'] == 3) {
        $checked[$row['config_name']] = ($row['config_value']) ? "checked='checked'" : "";
    }
}

$output = <<<HTML
<form action="{$BASE_URL}/account/submit/admin-config.php?course={$_GET['course']}" method="post">
<div id="container" style="width:100%; margin-top:40px;">
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <div class="modal-header">
            <h3 id="myModalLabel">Manage System Configuration</h3>
        </div>
        <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
            Change settings below to manage the system.<br /><br />
            Course Name: <input type="text" name="course_name" value="{$settings['course_name']['config_value']}" /><br />
            Allowed File Extensions: <input type="text" name="allowed_file_extensions" value="{$settings['allowed_file_extensions']['config_value']}" /><br />
            Default Late Days: <input type="text" name="default_late_days" value="{$settings['default_late_days']['config_value']}" /><br />
            Use Autograder: <input type="checkbox" name="use_autograder" value="true" {$checked['use_autograder']} /><br />
            Calculate Diff: <input type="checkbox" name="calculate_diff" value="true" {$checked['calculate_diff']} /><br />
            Zero Rubric Grades: <input type="checkbox" name="zero_rubric_grades" value="true" {$checked['zero_rubric_grades']} />
        </div>
        <div class="modal-footer">
            <div style="width:50%; float:right; margin-top:15px;">
                <input class="btn btn-primary" type="submit" value="Update Configuration"/>
            </div>
        </div>
    </div>
</div>
</form>
HTML;

print $output;

require_once "../footer.php";