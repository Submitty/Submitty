<?php

echo <<<HTML
<!DOCTYPE html>
<html>
	<head>
		<!-- CSS Styles and Scripts-->
		<link href='https://fonts.googleapis.com/css?family=Open+Sans+Condensed:300,300italic,700' rel='stylesheet' type='text/css'>
		<link href='https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic,700italic' rel='stylesheet' type='text/css'>
		<link href='https://fonts.googleapis.com/css?family=PT+Sans:700,700italic' rel='stylesheet' type='text/css'>
		<link href='https://fonts.googleapis.com/css?family=Inconsolata' rel='stylesheet' type='text/css'>

		<link href="/resources/override.css" rel="stylesheet"></link>
		<link href="/resources/bootmin.css" rel="stylesheet"></link>
		<link href="/resources/badge.css" rel="stylesheet"></link>
		<script src="/resources/script/main.js"></script>
		<link href="/resources/default_main.css" rel="stylesheet"></link>

		<style>
			table {
				width: 100%;
				# margin: 10px;
				padding: 8px;
				border: 2px solid grey;
			}
			table .colspan{
				padding: 10px;
				font-size: 18px;
				font-weight: bold;
				font-family: 'Open Sans Condensed', sans-serif;
			}
			table .student td{
				width:33%;
				text-align: center;
			}
			table .student:first-child {
				text-align: justify;
			}
			.not-submitted {
				background-color: red;
			}
		</style>
	</head>
	<body>
		<!-- site-nav -->
		<div class="site-nav" style="width:100%">
			<h3>
				<a class="page-link" href="http://submitty.org/" >Submitty</a> >
				<a class="page-link" href="/">{$semester}</a> >
				<a class="page-link">{$course}</a>
				<span>{$username}</span>
			</h3>
		</div>
HTML;

// echo '<span>'.$username.'</span>';
// echo <<<HTML
// 			</h3>
// 		</div> 

// HTML;

// get gradeables from json
// note: assume already sorted chronologically
$all_gradeables = parse_json($gradeable_addresses);

//========================================================
// for debug: print out all the json files for gradeables
echo "<div>";
foreach ($gradeable_addresses as $g) {
	echo $g."<br>";
}
echo "</div>";
//========================================================
echo <<<HTML
	<table>
HTML;

if(isInstructor($username)) {
	echo '<tr class="colspan"><td colspan="4">Future Items</td></tr>';
	foreach ($all_gradeables['future'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submit Open {$gradeable["date_submit"]}</td>
		<td><button class="pure-button">Grade</button></td>
		<td><button class='pure-button'>Edit</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="4">Open Items</td></tr>';
	foreach ($all_gradeables['submission_open'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submit Due {$gradeable["date_due"]}</td>
		<td><button class="pure-button">Grade</button></td>
		<td><button class='pure-button'>Edit</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="4">Closed Items</td></tr>';
	foreach ($all_gradeables['submission_closed'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submissions</td>
		<td><button class="pure-button">Grading open {$gradeable["date_grade"]}</button></td>
		<td><button class='pure-button' style="width:50%">Edit</button><button class='pure-button' style="width:50%">Open</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="4">Items Being Graded</td></tr>';
	foreach ($all_gradeables['grading_open'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submissions</td>
		<td><button class="pure-button">Grading by {$gradeable["date_released"]}</button></td>
		<td><button class='pure-button' style="width:50%">Edit</button><button class='pure-button' style="width:50%">Publish</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="4">Graded Items</td></tr>';
	foreach ($all_gradeables['graded'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submissions</td>
		<td><button class="pure-button">Grade</button></td>
		<td><button class='pure-button'>Edit</button></td>
		</tr>
HTML;
	}
}
else if (isTA($username)) {
	?><script>console.log("is ta");</script><?php
	echo '<tr class="colspan"><td colspan="3">Open Items</td></tr>';
	foreach ($all_gradeables['submission_open'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submit Due {$gradeable["date_due"]}</td>
		<td><button class="pure-button">Grade</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="3">Closed Items</td></tr>';
	foreach ($all_gradeables['submission_closed'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submissions</td>
		<td><button class="pure-button">Grading open {$gradeable["date_grade"]}</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="3">Items Being Graded</td></tr>';
	foreach ($all_gradeables['grading_open'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submissions</td>
		<td><button class="pure-button">Grading by {$gradeable["date_released"]}</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="3">Graded Items</td></tr>';
	foreach ($all_gradeables['graded'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submissions</td>
		<td><button class="pure-button">Grade</button></td>
		</tr>
HTML;
	}
}
else if (isStudent($username)) {
	?><script>console.log("is student");</script><?php
	echo '<tr class="colspan"><td colspan="3">Open Items</td></tr>';
	foreach ($all_gradeables['submission_open'] as $gradeable) {
		if(isElectronic($gradeable)){
			$num_submissions = get_highest_assignment_version($username, $semester,$course, $gradeable["gradeable_id"]);
			$button_class = ($num_submissions > 0) ? "submitted" : "not-submitted";
			$num_submissions = ($num_submissions > 0) ? $num_submissions : "No";
			$num_submissions = ($num_submissions == 1) ? $num_submissions." Submission" : $num_submissions." Submissions";
			echo <<<HTML
			<tr class="student">
			<td>{$gradeable["gradeable_title"]}</td>
			<td>Submit Due {$gradeable["date_due"]}</td>
			<td><button class="{$button_class}">{$num_submissions}</button></td>
			</tr>
HTML;
		}
	}
	echo '<tr class="colspan"><td colspan="3">Closed Items</td></tr>';
	foreach (array_merge($all_gradeables['submission_closed'], $all_gradeables['grading_open']) as $gradeable) {
		if(isElectronic($gradeable)){
			echo <<<HTML
			<tr class="student">
			<td>{$gradeable["gradeable_title"]}</td>
			<td>Submit Due {$gradeable["date_due"]}</td>
			<td><button>Submissions</button></td>
			</tr>
HTML;
		}
	}
	echo '<tr class="colspan"><td colspan="3">Graded Items</td></tr>';
	foreach ($all_gradeables['graded'] as $gradeable) {
		if(isElectronic($gradeable)){
			echo <<<HTML
			<tr class="student">
			<td>{$gradeable["gradeable_title"]}</td>
			<td>View Submissions</td>
			</tr>
HTML;
		}
	}
}

echo <<<HTML
		</table>
	</body>
	<script>
	function goEdit(id){
		window.location.href = "";
	}
	</script>
</html>
HTML;



function parse_json($gradeable_addresses) {
    $future = array();
    $submission_open = array();
    $submission_closed = array();
    $grading_open = array();
    $graded = array();

    // get the time now
    $now = new DateTime("NOW");
    // for all json files
    foreach($gradeable_addresses as $address){
		$gradeable = json_decode(file_get_contents($address), true);
	    if(new DateTime($gradeable['date_submit']) > $now) {
	    	$future[] = $gradeable;
	    	?><script>console.log("is future");</script><?php
	    }
	    else if (new DateTime($gradeable['date_due']) > $now) {
	    	$submission_open[] = $gradeable;
	    	?><script>console.log("is open");</script><?php
	    }
	    else if (new DateTime($gradeable['date_grade']) > $now) {
	    	$submission_closed[] = $gradeable;
	    	?><script>console.log("is closed");</script><?php
	    }
	    else if (new DateTime($gradeable['date_released']) > $now) {
	    	$grading_open[] = $gradeable;
	    	?><script>console.log("is being graded");</script><?php
	    }
	    else {
	    	$graded[] = $gradeable;
	    	?><script>console.log("is graded");</script><?php
	    }
	}
    $all_gradeables = array('future'=>$future, 'submission_open'=>$submission_open, 'submission_closed'=>$submission_closed, 'grading_open'=>$grading_open, 'graded'=>$graded);
    return $all_gradeables;
}

// hard-coded user check
function isInstructor($username){
	return $username == "instructor";
}

function isTA($username){
	return $username == "ta";
}

function isStudent($username){
	return $username == "student";
}

function isElectronic($gradeable) {
	?><script>console.log("is electronic");</script><?php
	return $gradeable["gradeable-type"] === "Electronic File";
}

?>