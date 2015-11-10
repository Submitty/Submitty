<?php
include "../header.php";
check_administrator();

print <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <form action="{$BASE_URL}/account/ajax/admin-csv-report.php" method="get">
            <div class="modal-header">
                <h3 id="myModalLabel">Generate CSV Report</h3>
            </div>
            <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
                Homework: <select name="hw" style="margin-left:5px;">
                    <option value="-1">No Homework</option>
HTML;

$params = array();
$db->query("SELECT rubric_number FROM rubrics ORDER BY rubric_number ASC", $params);
$temp = $db->rows();
for($i = 0; $i < count($temp); $i++) {
    $row = $temp[$i];
    echo '<option' . ($i == count($temp) -1 ? " selected " : "") . '>' . $row["rubric_number"] . '</option>';
}
print <<<HTML
                </select>
                <br/>
                <br/>
                Lab: <select name="lab" style="margin-left:50px;">
                    <option value="-1">No Lab</option>
HTML;

$params = array();
$db->query("SELECT lab_number FROM labs ORDER BY lab_number ASC", $params);
$temp = $db->rows();
for($i = 0; $i < count($temp); $i++) {
    $row = $temp[$i];
    echo '<option' . ($i == count($temp) -1 ? " selected " : "") . '>' . $row["lab_number"] . '</option>';
}
print <<<HTML
                </select>
                <br/>
                <br/>
                Test: <select name="test" style="margin-left:47px;">
                    <option value="-1">No Test</option>
HTML;

$params = array();
$db->query("SELECT test_number FROM tests ORDER BY test_number ASC", $params);
$temp = $db->rows();
for($i = 0; $i < count($temp); $i++) {
    $row = $temp[$i];
    echo '<option' . ($i == count($temp) -1 ? " selected " : "") . '>' . $row["test_number"] . '</option>';
}

print <<<HTML
                </select>
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