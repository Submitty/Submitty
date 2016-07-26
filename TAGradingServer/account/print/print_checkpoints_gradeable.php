<?php

include "../../toolbox/functions.php";
$g_id = $_GET['g_id'];
$section = intval($_GET['section_id']);
$grade_by_reg_section = $_GET['grade_by_reg_section'];

$db->query("SELECT * FROM gradeable WHERE g_id=?", array($g_id));
$check_g = $db->row();

$section_type = ($grade_by_reg_section ? "Registration": "Rotating");

print <<<HTML
Name: ____________&emsp;&emsp;&emsp;&emsp;
Date: ____________________&emsp;&emsp;&emsp;
<b>{$check_g['g_title']}</b>&emsp;&emsp;&emsp;&emsp;
{$section_type} Section: <b>{$section}</b>
<br /><br />
<table border="1">
    <tr>
    <td style="width: 20%">User Id</td>
    <td style="width: 20%">Last Name</td>
    <td style="width: 20%">First Name</td>
HTML;

//Get the names of all of the checkpoints 
$db->query("SELECT gc_title FROM gradeable as g INNER JOIN gradeable_component gc ON g.g_id = gc.g_id WHERE g.g_id=?",array($g_id));
$checkpoints = array();
foreach($db->rows() as $row){
    array_push($checkpoints, $row['gc_title']);
}

$width = (40/count($checkpoints));
for($i = 0; $i < count($checkpoints); $i++) {
    print <<<HTML
        <td style="width: {$width}%">{$checkpoints[$i]}</td>
HTML;
}

print <<<HTML
    </tr>
HTML;

$query = ($grade_by_reg_section) ? "SELECT * FROM users WHERE registration_section=? AND user_group=? ORDER BY user_id"
                                 : "SELECT * FROM users WHERE rotating_section=? AND user_group=? ORDER BY user_id";
$db->query($query, array($section,4));

$j = 0;
foreach($db->rows() as $student) {
    $color = ($j % 2 == 0) ? "white" : "lightgrey";
    print <<<HTML
    <tr style="background-color: {$color}">
        <td>
            {$student['user_id']}
        </td>
        <td>
            {$student['user_lastname']}
        </td>
        <td>
            {$student['user_firstname']}
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