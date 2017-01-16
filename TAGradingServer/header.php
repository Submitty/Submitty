<?php

// Prevent back button from showing sensitive cached content after logout.
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.
require_once __DIR__."/toolbox/functions.php";

print <<<HTML
<!DOCTYPE html>
<html>

	<head>
		<meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
		<title>$COURSE_NAME Grading</title>
		<meta name="description" content="CONFIDENTIAL: RPI Grading"/>
	    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"/>

        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
        <meta http-equiv="Pragma" content="no-cache" />
        <meta http-equiv="Expires" content="0" />

HTML;
if (__DEBUG__) {
    print <<<HTML
        <link rel="shortcut icon" type="image/x-icon" href="{$BASE_URL}/toolbox/include/custom/img/favicon_debug.ico?v=2"/>

HTML;
}
else {
    print <<<HTML
		<link rel="shortcut icon" type="image/x-icon" href="{$BASE_URL}/toolbox/include/custom/img/favicon.ico"/>

HTML;
}
print <<<HTML

		<link type="text/css" rel="stylesheet" href="{$BASE_URL}/toolbox/include/bootstrap/css/bootstrap.min.css" />
		<link type="text/css" rel="stylesheet" href="{$BASE_URL}/toolbox/include/custom/css/jquery-ui.min.css" />
		<link type="text/css" rel="stylesheet" href="{$BASE_URL}/toolbox/include/custom/css/jquery-ui-timepicker-addon.css" />

		<link type="text/css" rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/lib/codemirror.css" />
		<link type="text/css" rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/theme/eclipse.css" />

		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/jquery-2.0.3.min.map.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/jquery-ui.min.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/jquery-ui-timepicker-addon.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/jquery.color-2.1.2.min.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/bootstrap/js/bootstrap.min.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/script.js"></script>


		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/lib/codemirror.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/clike/clike.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/python/python.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/shell/shell.js"></script>

		<link type="text/css" rel="stylesheet" href="{$BASE_URL}/toolbox/include/custom/css/style.css" />

	</head>

	<body onunload="">
HTML;

if(__DEBUG__) {
    echo "<div style='border-top: 2px solid red; width:100%; position:fixed; top:0px; z-index: 2000;'></div>";
}

if ($user_logged_in) {
    $submission_url = __SUBMISSION_URL__;
    $semester = __COURSE_SEMESTER__;
    $semester_upper = strtoupper($semester);
    $course = __COURSE_CODE__;
    $course_upper = strtoupper($course);
    $course_name = __COURSE_NAME__;

    print <<<HTML
        <div class="navbar navbar-inverse navbar-fixed-top">
            <div class="navbar-inner">
                <div class="container-fluid" style="font-weight: 300; display: inline-block;color:#999;">
                    <h4>{$semester_upper} &gt;
                        <a href="{$submission_url}/index.php?semester={$semester}&course={$course}" role="button" data-toggle="modal">
                            {$course_upper}: {$course_name}
                        </a>
HTML;
    if(isset($_GET['g_id'])){
        $db->query("SELECT g_title FROM gradeable WHERE g_id=?",array($_GET['g_id']));

        $title = $db->row();
        if(!empty($title)){
            print <<<HTML
                &gt; {$title['g_title']}
HTML;
        }
    }
    print <<<HTML
                </h4>
            </div>
        </div>
    </div>
HTML;
}