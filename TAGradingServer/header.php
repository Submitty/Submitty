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
		
		<link type="text/css" rel="stylesheet" href="{$BASE_URL}/toolbox/include/bootstrap/css/bootstrap.min.css"/>
		<link type="text/css" rel="stylesheet" href="{$BASE_URL}/toolbox/include/pickadate/themes/pickadate.02.classic.css">
		<link type="text/css" rel="stylesheet" href="{$BASE_URL}/toolbox/include/custom/css/style.css"/>
		
		<link href="{$BASE_URL}/toolbox/include/codemirror/lib/codemirror.css" rel="stylesheet" >
		<link rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/theme/eclipse.css">

		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/jquery-2.0.3.min.map.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/jquery.color-2.1.2.min.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/bootstrap/js/bootstrap.min.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/pickadate/source/pickadate.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/script.js"></script>
		
		<script src="{$BASE_URL}/toolbox/include/codemirror/codemirror-compressed.js"></script>
		<script src="{$BASE_URL}/toolbox/include/codemirror/mode/clike/clike.js"></script>
		<script src="{$BASE_URL}/toolbox/include/codemirror/mode/python/python.js"></script>
		<script src="{$BASE_URL}/toolbox/include/codemirror/mode/shell/shell.js"></script>
		
	</head>
	
	<body onunload="">
HTML;

if(__DEBUG__) {
    echo "<div style='border-top: 2px solid red; width:100%; position:fixed; top:0px; z-index: 2000;'></div>";
}

$bonus_message = "new!";

if ($user_logged_in) {
    print <<<HTML
        <div class="navbar navbar-inverse navbar-fixed-top">
            <div class="navbar-inner">
                <div class="container-fluid">
                    <a class="brand" href="{$BASE_URL}/account/index.php">$COURSE_NAME Grading Server $bonus_message</a>

                    <ul class="nav" role="navigation">
                        <li class="dropdown">
                            <a href="{$BASE_URL}/account/index.php">Homeworks</a>
                        </li>

                        <li class="dropdown">
                            <a href="{$BASE_URL}/account/account-labs.php">Labs</a>
                        </li>

                        <li class="dropdown">
                            <a href="{$BASE_URL}/account/account-tests.php">Tests</a>
                        </li>
HTML;
    if ($user_is_administrator) {
        print <<<HTML
                        <li class="divider-vertical"
                            style="border-right-color: #666;height: 18px; margin-top: 11px;"></li>

                        <li class="dropdown">
                            <a id="drop1" href="#" role="button" class="dropdown-toggle" data-toggle="dropdown">
                                Grading Tools
                            </a>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="drop-grade">
                                <li><a tabindex="-1" href="{$BASE_URL}/account/admin-hw-report.php" role="button" data-toggle="modal">
                                    Homework Report
                                </a></li>
                                <li><a tabindex="-1" href="{$BASE_URL}/account/admin-grade-summaries.php" role="button" data-toggle="modal">
                                    Generate Grade Summaries
                                </a></li>
                            </ul>
                        </li>
                        <li class="dropdown">
                            <a id="drop1" href="#" role="button" class="dropdown-toggle" data-toggle="dropdown">
                                Manage Assignments
                            </a>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="drop-assignments">
                                <li><a tabindex="-1" href="{$BASE_URL}/account/admin-rubrics.php" role="button" data-toggle="modal">
                                        Manage Rubrics
                                </a></li>
                                <li><a tabindex="-1" href="{$BASE_URL}/account/admin-labs.php" role="button" data-toggle="modal">
                                        Manage Labs
                                </a></li>
                                <li><a tabindex="-1" href="{$BASE_URL}/account/admin-tests.php" role="button" data-toggle="modal">
                                        Manage Tests
                                </a></li>
                            </ul>
                        </li>
                        <li class="dropdown">
                            <a id="drop1" href="#" role="button" class="dropdown-toggle" data-toggle="dropdown">
                                System Management
                            </a>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="drop-utility">
                                <li><a tabindex="-1" href="{$BASE_URL}/account/admin-config.php" role="button" data-toggle="modal">
                                    System Configuration
                                </a></li>
                                <li><a tabindex="-1" href="{$BASE_URL}/account/admin-classlist.php" role="button" data-toggle="modal">
                                    Upload Classlist
                                </a></li>
                                <li><a tabindex="-1" href="{$BASE_URL}/account/admin-orphans.php" role="button" data-toggle="modal">
                                    View Orphans
                                </a></li>
                            </ul>
                        </li>
HTML;
    }
    print <<<HTML
                    </ul>

                    <ul class="nav" role="navigation" style="float:right">
                        <li class="dropdown">
                            <a id="drop1" href="#" role="button" class="dropdown-toggle" data-toggle="dropdown">
                                Welcome back, {$user_info["user_firstname"]} <b class="caret"></b>

                            </a>

                            <ul class="dropdown-menu" role="menu" aria-labelledby="drop1">
HTML;

    if ($DEVELOPER) {
        print <<<HTML
                                <li><a tabindex="-1" href="#" role="button" data-toggle="modal" onClick="toggle()">
                                    Toggle Page Details
                                </a></li>
HTML;
    }
    print <<<HTML
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
HTML;
}
?>
