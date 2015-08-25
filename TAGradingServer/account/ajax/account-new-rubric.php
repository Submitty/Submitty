<?php
include "../../toolbox/functions.php";

use \lib\DiffViewer;

$diffViewer = new DiffViewer();

$iframe = "<script type=\"text/javascript\" language=\"javascript\" src=\"{$BASE_URL}/toolbox/include/custom/js/jquery-2.0.3.min.map.js\"></script>";
$iframe .= $diffViewer->getCSS();
$iframe .= $diffViewer->getJavascript();

$testcases = json_decode(urldecode($_GET['testcases']), true);
$i = 0;
foreach ($testcases['diffs'] as $diff) {
    $iframe .= "<div><h3>{$diff['description']}</h3>";
    $actual = $expected = $difference = "";
    if (isset($diff['student_file']) && file_exists($_GET['directory']."/".$diff['student_file'])) {
        $actual = $_GET['directory']."/".$diff['student_file'];
    }

    if (isset($diff['instructor_file']) && file_exists(implode("/", array(__SUBMISSION_SERVER__, $diff['instructor_file'])))) {
        $expected = implode("/", array(__SUBMISSION_SERVER__, $diff['instructor_file']));
    }

    if (isset($diff['difference']) && file_exists($_GET['directory']."/".$diff['difference'])) {
        $difference = $_GET['directory']."/".$diff['difference'];
    }
    
    $diffViewer->load($actual, $expected, $difference, "id{$i}_");
    $actual = $diffViewer->getDisplayActual();
    $expected .= $diffViewer->getDisplayExpected();
    if ($actual != "") {
        $iframe .= "Actual<br />{$actual}<br />";
    }
    
    if ($expected != "") {
        $iframe .= "Expected<br />{$expected}";
    }
    $iframe .= "</div><br /><br />";
    $diffViewer->reset();
    $i++;
}

echo $iframe;