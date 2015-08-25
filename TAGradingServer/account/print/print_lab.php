<?php

include "../../toolbox/functions.php";
$lab = intval($_GET['lab_id']);
$section = intval($_GET['section_id']);

$query = "SELECT * FROM labs WHERE lab_id=?";
$db->query($query, array($lab));
$lab = $db->row();

$query = "SELECT * FROM students WHERE student_section_id=? ORDER BY student_rcs";
$db->query($query, array($section));

print <<<HTML
Name: ____________&emsp;&emsp;&emsp;&emsp;
Date: ____________________&emsp;&emsp;&emsp;
Lab: <b>{$lab['lab_title']}</b>&emsp;&emsp;&emsp;&emsp;
Section: <b>{$section}</b>
<br /><br />
<table border="1">
    <tr>
    <td style="width: 20%">RCS</td>
    <td style="width: 20%">Last Name</td>
    <td style="width: 20%">First Name</td>
HTML;

$checkpoints = explode(",",$lab['lab_checkpoints']);
$width = (40/count($checkpoints));
for($i = 0; $i < count($checkpoints); $i++) {
    print <<<HTML
        <td style="width: {$width}%">{$checkpoints[$i]}</td>
HTML;
}

print <<<HTML
    </tr>
HTML;

$j = 0;
foreach($db->rows() as $student) {
    $color = ($j % 2 == 0) ? "white" : "lightgrey";
    print <<<HTML
    <tr style="background-color: {$color}">
        <td>
            {$student['student_rcs']}
        </td>
        <td>
            {$student['student_last_name']}
        </td>
        <td>
            {$student['student_first_name']}
        </td>
HTML;
    for($i = 0; $i < count($checkpoints); $i++) {
        print <<<HTML
        <td></td>
HTML;
    }
    print <<<HTML
    </tr>
HTML;
    $j++;
}

print <<<HTML
</table>
HTML;

?>