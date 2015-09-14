<?php
include "../../toolbox/functions.php";

use \lib\DiffViewer;

$diffViewer = new DiffViewer();

$iframe = <<<HTML
		<link rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/lib/codemirror.css" />
		<link rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/theme/eclipse.css" />
        <script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/jquery-2.0.3.min.map.js"></script>
        <script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/lib/codemirror.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/clike/clike.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/python/python.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/shell/shell.js"></script>
HTML;

$iframe .= $diffViewer->getCSS();
$iframe .= $diffViewer->getJavascript();

$testcase = json_decode(urldecode($_GET['testcases']), true);
$i = 0;
if (count($testcase['diffs']) > 0) {
    foreach ($testcase['diffs'] as $diff) {
        $iframe .= "<div style='height:auto'><h3>{$diff['description']}</h3>";
        $actual = $expected = $difference = "";
        if (isset($diff['student_file']) && file_exists($_GET['directory'] . "/" . $diff['student_file'])) {
            $actual = $_GET['directory'] . "/" . $diff['student_file'];
        }

        if (isset($diff['instructor_file']) && file_exists(implode("/", array(__SUBMISSION_SERVER__, $diff['instructor_file'])))) {
            $expected = implode("/", array(__SUBMISSION_SERVER__, $diff['instructor_file']));
        }

        if (isset($diff['difference']) && file_exists($_GET['directory'] . "/" . $diff['difference'])) {
            $difference = $_GET['directory'] . "/" . $diff['difference'];
        }

        if ($difference != "") {
            $diffViewer->load($actual, $expected, $difference, "id{$i}_");
            $actual = $diffViewer->getDisplayActual();
            $expected = $diffViewer->getDisplayExpected();
            if ($actual != "") {
                $iframe .= "Actual<br />{$actual}<br />";
            }
            
            if ($expected != "") {
                
                $iframe .= "Expected<br />{$expected}";
            }
            $iframe .= "<br /><br />";
            $diffViewer->reset();
        }
        else {
            if ($actual != "") {
                $out = file_get_contents($actual);
                $iframe .= <<<HTML
    Student File<br />
    <textarea id="code{$i}">{$out}</textarea>
HTML;
                $iframe .= sourceSettingsJS($diff['student_file'], $i++);
            }
            if ($expected != "") {
                $out = file_get_contents($expected);
                $iframe .= <<<HTML
    Instructor File<br />
    <textarea id="code{$i}">{$out}</textarea>
HTML;
                $iframe .= sourceSettingsJS($diff['instructor_file'], $i++);
            }
        }
        //$iframe .= "</div>";
    }
}
if (isset($testcase['compilation_output']) && $testcase['compilation_output'] != "") {
    $out = file_get_contents($_GET['directory'].'/'.$testcase['compilation_output']);
    $iframe .= <<<HTML
    <textarea id="code{$i}">{$out}</textarea>
HTML;
    $iframe .= sourceSettingsJS($testcase['compilation_output'], $i++);
}

echo $iframe;