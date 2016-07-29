<?php

echo <<<HTML
<!DOCTYPE html>
<html>
	<head>
		<!-- CSS Styles and Scripts from submission page -->
		<link href='https://fonts.googleapis.com/css?family=Open+Sans+Condensed:300,300italic,700' rel='stylesheet' type='text/css'>
		<link href='https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic,700italic' rel='stylesheet' type='text/css'>
		<link href='https://fonts.googleapis.com/css?family=PT+Sans:700,700italic' rel='stylesheet' type='text/css'>
		<link href='https://fonts.googleapis.com/css?family=Inconsolata' rel='stylesheet' type='text/css'>

		<link href="/resources/override.css" rel="stylesheet" />
		<link href="/resources/bootmin.css" rel="stylesheet" />
		<link href="/resources/badge.css" rel="stylesheet" />
		<script src="/resources/script/main.js"></script>
		<link href="/resources/default_main.css" rel="stylesheet" />

		<!-- CSS for course page -->
		<link href="/resources/course_page.css" rel="stylesheet" />
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
		<table>
HTML;

// get gradeables from json
// note: assume already sorted chronologically
$all_gradeables = parse_json($gradeable_addresses);

if(isInstructor($username)) {
	echo '<tr class="colspan"><td colspan="4">Future Items</td></tr>';
	foreach ($all_gradeables['future'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submit Open {$gradeable["date_submit"]->format("M j H:i ")}</td>
		<td><button class="btn">Grade</button></td>
		<td><button class='btn'>Edit</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="4">Open Items</td></tr>';
	foreach ($all_gradeables['submission_open'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submit Due {$gradeable["date_due"]->format("M j H:i ")}</td>
		<td><button class="btn">Grade</button></td>
		<td><button class='btn'>Edit</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="4">Closed Items</td></tr>';
	foreach ($all_gradeables['submission_closed'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submissions</td>
		<td><button class="btn">Grading open {$gradeable["date_grade"]->format("M j H:i ")}</button></td>
		<td><button class='btn' style="width:50%">Edit</button><button class='btn' style="width:50%">Open</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="4">Items Being Graded</td></tr>';
	foreach ($all_gradeables['grading_open'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submissions</td>
		<td><button class="btn">Grading by {$gradeable["date_released"]->format("M j H:i ")}</button></td>
		<td><button class='btn' style="width:50%">Edit</button><button class='btn' style="width:50%">Publish</button></td>
		</tr>
HTML;
	}
	echo '<tr class="colspan"><td colspan="4">Graded Items</td></tr>';
	foreach ($all_gradeables['graded'] as $gradeable) {
		echo <<<HTML
		<tr>
		<td>{$gradeable["gradeable_title"]}</td>
		<td>Submissions</td>
		<td><button class="btn">Grade</button></td>
		<td><button class='btn'>Edit</button></td>
		</tr>
HTML;
	}
}

//==============================================
// FIXME: links to grading page yet to be added
//==============================================
else if (isTA($username)) {
	foreach (array_merge(array("Open Items"), $all_gradeables['submission_open'], array("Closed Items"), $all_gradeables['submission_closed'], array("Items Being Graded"), $all_gradeables['grading_open'], array("Graded Items"), $all_gradeables['graded']) as $gradeable) {
		if(is_string($gradeable)) {
			echo '<tr class="bar"><td colspan="5"></td></tr>';
			echo '<tr class="colspan"><td colspan="5" style="border-bottom:2px black solid;">'.$gradeable.'</td></tr>';
		}
		else if(isElectronic($gradeable)){
			$instructions_url = ($gradeable['instructions-url'] != "" ? "href=\"{$gradeable['instructions-url']}\"" : "");
			$url = ($gradeable['instructions-url'] != "" ? "{$gradeable['instructions-url']}" : "");
			$title_class = $gradeable['instructions-url'] != "" ? "class='has-url'" : "";
			$button_class = "btn btn-primary";
			echo <<<HTML
			<tr class="ta">
			<td class="gradeable_title"><a {$instructions_url} onclick="goToPage('{$url}'); return false;"><label {$title_class}>{$gradeable["gradeable_title"]}</label></a></td>
			<td class="date">{$gradeable["date_submit"]->format("M j H:i ")} - {$gradeable["date_due"]->format("M j H:i ")}</td>
			<td class="option"><button class="btn btn-primary" style="width:100%;" onclick="location.href='?semester={$semester}&course={$course}&assignment_id={$gradeable["gradeable_id"]}'">Submit</button></td>
HTML;
			if($gradeable['ta-grading'] == "yes") {	// ta-grading enabled
				$disabled = ($gradeable['date_grade'] > new DateTime('NOW')) ? "disabled" : "";
				echo <<<HTML
				<td class="date">{$gradeable["date_grade"]->format("M j H:i ")} - {$gradeable["date_released"]->format("M j H:i ")}</td>
				<td class="option"><button class="{$button_class}" {$disabled} style="width:100%;" onclick="location.href='#'">Grade</button></td>
				</tr>
HTML;
			}
			else {	// ta-grading disabled
				echo <<<HTML
				<td class="date"></td>
				<td class="option"></td>
				</tr>
HTML;
			}
		}
		// display itmes of checkpoint type or numeric/text type
		else{
			echo <<<HTML
			<tr class="ta">
			<td class="gradeable_title"><label>{$gradeable["gradeable_title"]}</label></td>
			<td class="date"></td>
			<td class="option"></td>
			<td class="date">{$gradeable["date_grade"]->format("M j H:i ")} - {$gradeable["date_released"]->format("M j H:i ")}</td>
			<td class="option"><button class="btn btn-primary" style="width:100%;" onclick="location.href='#'">Grade</button></td>
			</tr>
HTML;
		}
	}
}
else if (isStudent($username)) {
	foreach (array_merge(array("Open Items"), $all_gradeables['submission_open'], array("Closed Items"), $all_gradeables['submission_closed'], $all_gradeables['grading_open'], array("Graded Items"), $all_gradeables['graded']) as $gradeable) {
		// check if is a separator
		if(is_string($gradeable)) {
			echo '<tr class="bar"><td colspan="3"></td></tr>';
			echo '<tr class="colspan"><td colspan="3" style="border-bottom:2px black solid;">'.$gradeable.'</td></tr>';
		}
		// display only gradeables of eletronic file type 
		else if(isElectronic($gradeable)){
			// display buttons according to number of submissions students made
			$num_submissions = get_highest_assignment_version($username, $semester,$course, $gradeable["gradeable_id"]);
			$button_class = ($num_submissions > 0) ? "btn" : "btn btn-danger";
			$submission_message = (($num_submissions > 0) ? $num_submissions : "No")." Submission";
			$submission_message = ($num_submissions == 1) ? $submission_message : $submission_message."s";
			if($gradeable['date_released'] <= new DateTime('NOW')) {
				$submission_message = "View Grades";
				$button_class = "btn btn-success";
			}

			// add link if instructions-url provided
			$instructions_url = ($gradeable['instructions-url'] != "" ? "href=\"{$gradeable['instructions-url']}\"" : "");
			$url = ($gradeable['instructions-url'] != "" ? "{$gradeable['instructions-url']}" : "");
			$title_class = $gradeable['instructions-url'] != "" ? "class='has-url'" : "";

			echo <<<HTML
			<tr class="student">
			<td class="gradeable_title"><a {$instructions_url} onclick="goToPage('{$url}'); return false;"><label {$title_class}>{$gradeable["gradeable_title"]}</label></a></td>
			<td class="date">{$gradeable["date_due"]->format("M j H:i ")}</td>
			<td class="option"><button class="{$button_class}" style="width:100%;" onclick="location.href='?semester={$semester}&course={$course}&assignment_id={$gradeable["gradeable_id"]}'">{$submission_message}</button></td>
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

	function goToPage(url){
		if(url)
			window.location.href = url;
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

    foreach($gradeable_addresses as $address){
		$gradeable = json_decode(file_get_contents($address), true);

		// convert strings to dates
		if(isset($gradeable['date_submit'])) {
    		$gradeable['date_submit'] = new DateTime($gradeable['date_submit']);
    	}
    	if(isset($gradeable['date_due'])) {
    		$gradeable['date_due'] = new DateTime($gradeable['date_due']);
    	}
    	if(isset($gradeable['date_grade'])) {
    		$gradeable['date_grade'] = new DateTime($gradeable['date_grade']);
    	}
    	if(isset($gradeable['date_released'])) {
    		$gradeable['date_released'] = new DateTime($gradeable['date_released']);
    	}

    	// categorize into future/open/closed/being graded/graded items
	    if(isset($gradeable['date_submit']) && $gradeable['date_submit'] > $now) {
	    	$future[] = $gradeable;
	    	?><script>console.log("is future");</script><?php
	    }
	    else if (isset($gradeable['date_submit']) && $gradeable['date_due'] > $now) {
	    	$submission_open[] = $gradeable;
	    	?><script>console.log("is open");</script><?php
	    }
	    else if (isset($gradeable['date_grade']) && $gradeable['date_grade'] > $now) {
	    	$submission_closed[] = $gradeable;
	    	?><script>console.log("is closed");</script><?php
	    }
	    else if (isset($gradeable['date_released']) && $gradeable['date_released'] > $now) {
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