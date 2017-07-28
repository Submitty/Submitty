<?php

/**
 * Used to display the results of a test when viewing a rubric. Will show either a full diff viewer if there's a difference
 * file available, else it'll just show the individual files in a simple shell colored code window.
 */
include "../../toolbox/functions.php";

use \lib\DiffViewer;

$diffViewer = new DiffViewer();

$iframe = <<<HTML
<link rel="stylesheet" href="{$BASE_URL}/toolbox/include/custom/css/reset.css" />
<link rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/lib/codemirror.css" />
<link rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/theme/eclipse.css" />
<link rel="stylesheet" href="{$BASE_URL}/toolbox/include/custom/css/diff-viewer.css" />
<script type="text/javascript" src="{$BASE_URL}/toolbox/include/custom/js/jquery-2.0.3.min.map.js"></script>
<script type="text/javascript" src="{$BASE_URL}/toolbox/include/codemirror/lib/codemirror.js"></script>
<script type="text/javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/clike/clike.js"></script>
<script type="text/javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/python/python.js"></script>
<script type="text/javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/shell/shell.js"></script>
<script type="text/javascript" src="{$BASE_URL}/toolbox/include/custom/js/diff-viewer.js"></script>
<script type="text/javascript">
    function resizeTextarea(id) {
       $("#" + id).attr('rows', $("#" + id).val().split("\\n").length);
    }
</script>
HTML;

$no_diff = true;

$testcase = json_decode(urldecode($_GET['testcases']), true);
$i = 0;

if (isset($testcase['test_name']) && $testcase['test_name'] != "") {
    $iframe .= "<h3>{$testcase['test_name']}</h3>";
}
if (isset($testcase['autochecks']) && count($testcase['autochecks']) > 0) {
    foreach ($testcase['autochecks'] as $diff) {
        if (isset($diff['description'])) {
            $iframe .= "<div style='height:auto'><h3>{$diff['description']}</h3>";
        }
        $actual = $expected = $difference = "";
        if (isset($diff['actual_file']) && file_exists($_GET['directory'] . "/" . $diff['actual_file'])) {
            $actual = $_GET['directory'] . "/" . $diff['actual_file'];
        }

        if (isset($diff["expected_file"]) && file_exists(implode("/", array(__SUBMISSION_SERVER__, $diff["expected_file"])))) {
            $expected = implode("/", array(__SUBMISSION_SERVER__, $diff["expected_file"]));
        }

        if (isset($diff['difference_file']) && file_exists($_GET['directory'] . "/" . $diff['difference_file'])) {
            $difference = $_GET['directory'] . "/" . $diff['difference_file'];
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
            $no_diff = $no_diff && !$diffViewer->exists_difference();
        }
        else {
            if ($actual != "") {
                $out = htmlentities(file_get_contents($actual));
                $iframe .= <<<HTML
    Student File<br />
    <textarea id="code{$i}">{$out}</textarea>
    <script type="text/javascript">
        resizeTextarea("code{$i}");
    </script>
HTML;
                $iframe .= sourceSettingsJS($diff['actual_file'], $i++);
            }
            if ($expected != "") {
                $out = htmlentities(file_get_contents($expected));
                $iframe .= <<<HTML
    Instructor File<br />
    <textarea id="code{$i}">{$out}</textarea>
    <script type="text/javascript">
        resizeTextarea("code{$i}");
    </script>
HTML;
                $iframe .= sourceSettingsJS($diff["expected_file"], $i++);
            }
        }
        $iframe .= "\n</div>";
    }
}
if (isset($testcase['compilation_output']) && $testcase['compilation_output'] != "") {
    $out = htmlentities(file_get_contents($_GET['directory'].'/'.$testcase['compilation_output']));
    $iframe .= <<<HTML
    <textarea id="code{$i}">{$out}</textarea>
    <script type="text/javascript">
        resizeTextarea("code{$i}");
    </script>
HTML;
    $iframe .= sourceSettingsJS($testcase['compilation_output'], $i++);
}

$diff_difference = ($no_diff) ? "0" : "1";
$iframe .= "\n\n<input type='hidden' name='exists_difference' value='{$diff_difference}' />";

echo $iframe;