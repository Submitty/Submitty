<?php

/*
 * Purpose is to construct the html table for one section and one section only. This is done so that the sections can be
 * loaded asynchronously client side.
 */

$section_data = $_POST['section_data'];

$html = '';
$autograding_max = $section_data['autograding_max'];

foreach ($section_data['students'] as $student) {
    $autograding_points = $student['autograding_points'];

    $ta_points = $student['ta_points'];
    $ta_max = $section_data['ta_max'];

    $total_points = $autograding_points + $ta_points;
    $total_max = $autograding_max + $ta_max;

    $url_string = $_POST['url_string'] . "?g_id=" . $_GET['g_id'] . "&individual=" . $student["user_id"]
        . "&course=" . $_GET['course'] . "&semester=" . $_GET['semester'];

    $ta_points_row = '<td class="ta_grading">';
    $btn_url = $BASE_URL.'/account/index.php?g_id='.$_GET["g_id"].'&individual='.$student["user_id"].'&course='.$_GET['course'].'&semester='.$_GET['semester'];

    //If not graded yet
    if ($student['gd_grader_id'] == "") {
        $ta_points_row .= "<a class='btn btn-primary' href='{$btn_url}'>Grade</a>";
    } else {
        $ta_points_row .= "<a class='btn ' href='{$btn_url}'>[ " . ($ta_points) .
                    " / " . ($ta_max) . " ]</a>";
    }

    //Build student name string
    $student_name = "";
    if(!is_null($student['preferred_firstname']) || !empty($student['preferred_firstname'])){
        $student_name .= $student['preferred_firstname'];
    }else{
        $student_name .= $student['user_firstname'];
    }
    $student_name .= " " . $student['user_lastname'];

    $ta_points_row .= "</td>";

    $html .= <<<HTML
<tr>
    <td class="user_id">{$student['user_id']}</td>
    <td class="name">{$student_name}</td>
    <td class="autograding">{$autograding_points}/{$autograding_max}</td>
HTML;

    $html .= $ta_points_row;

    $html .= <<<HTML
 
    <td class="total">{$total_points}/{$total_max}</td>
</tr>
HTML;
}

echo $html;