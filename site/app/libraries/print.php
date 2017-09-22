<?php

namespace app\libraries;

public function printLab($g_id, $section, $grade_by_reg_section, $sort_by){

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
    <td style="width: 20%">Name</td>
HTML;

//Get the names of all of the checkpoints 
$db->query("SELECT gc_title FROM gradeable as g INNER JOIN gradeable_component gc ON g.g_id = gc.g_id WHERE g.g_id=?",array($g_id));
$checkpoints = array();
foreach($db->rows() as $row){
    array_push($checkpoints, $row['gc_title']);
}

$width = (60/count($checkpoints));
for($i = 0; $i < count($checkpoints); $i++) {
    print <<<HTML
        <td style="width: {$width}%">{$checkpoints[$i]}</td>
HTML;
}

print <<<HTML
    </tr>
HTML;

$query = "SELECT * FROM users WHERE ";
if($grade_by_reg_section){
    $query .= "registration_section=?";
}
else{
    $query .= "rotating_section=?";
}
$query .= " AND user_group=? ORDER BY ";
$sort = array();
if($sort_by === 'first'){
    $sort[] = 'user_firstname';
    $sort[] = 'user_lastname';
}
elseif($sort_by === 'last'){
    $sort[] = 'user_lastname';
}
$sort[] = 'user_id';
$query .= implode(', ', $sort);

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
HTML;
    if ($student['user_preferred_firstname']=="") {
        print <<<HTML
        {$student['user_firstname']}
HTML;
    } else {
        print <<<HTML
        {$student['user_preferred_firstname']}
HTML;
    }
    print <<<HTML
            {$student['user_lastname']}
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
}