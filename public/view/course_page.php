<?php

echo <<<HTML
<!DOCTYPE html>
<html>
	<head>
		<!-- CSS Styles and Scripts from submission page -->
		<link href='/resources/font/open_sans_condensed.css' rel='stylesheet'>
		<link href='/resources/font/sans_pro.css' rel='stylesheet'>
		<link href='/resources/font/pt_sans.css' rel='stylesheet'>
		<link href='/resources/font/inconsolata.css' rel='stylesheet'>
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
//==================================================
// FIXME: add code to sort the items chronologically
//==================================================
$all_gradeables = parse_json($gradeable_addresses);

//==============================================
// FIXME: links to grading/editing gradeable page yet to be added
//==============================================
if(isInstructor($username)) {
	foreach(array_merge(array("Future Items"), $all_gradeables['future'], array("Open Items"), $all_gradeables['submission_open'], array("Closed Items"), 
                        $all_gradeables['submission_closed'], array("Items Being Graded"), $all_gradeables['grading_open'], array("Graded Items"), 
                        $all_gradeables['graded']) as $gradeable) {
		if(is_string($gradeable)) {
			echo '<tr class="bar"><td colspan="6"></td></tr>';
			echo '<tr class="colspan"><td colspan="6" style="border-bottom:2px black solid;">'.$gradeable.'</td></tr>';
		}
		else {
			if(isElectronic($gradeable)) {
				if($gradeable['ta_grading'] == "yes" && $gradeable['instructions_url'] != "") {
					$gradeable_title = '<a href="'.$gradeable['instructions_url'].'" target="_blank"><label class="has-url">'.$gradeable['gradeable_title'].'</label></a>';
				}
				else {
					$gradeable_title = '<label>'.$gradeable['gradeable_title'].'</label>';
				}

				$submission_date = $gradeable["date_submit"]->format("M j H:i ").' - '.$gradeable["date_due"]->format("M j H:i ");
				$submission_option = <<<HTML
                <button class="btn btn-primary" style="width:100%;" onclick="location.href=\'?semester='{$semester}'&course='{$course}'\\
                &assignment_id='{$gradeable["gradeable_id"]}'\'">Submit</button></td>';
HTML;
				if($gradeable['ta_grading'] == "yes") {
					$grading_date = $gradeable["date_grade"]->format("M j H:i ").' - '.$gradeable["date_released"]->format("M j H:i ");
					$grading_option = '<button class="btn btn-primary" style="width:80%;" onclick="location.href=\"#\"">Grade</button>';
				}
				else{
					$grading_date = "";
					$grading_option = "";
				}
			}
			else {
				$gradeable_title = '<label>'.$gradeable['gradeable_title'].'</label>';
				$submission_date = '';
				$submission_option = '';
				$grading_date = $gradeable["date_grade"]->format("M j H:i ").' - '.$gradeable["date_released"]->format("M j H:i ");
				$grading_option = '<button class="btn btn-primary" style="width:80%;" onclick="location.href=\"#\"">Grade</button>';
			}
			//=======================================================================================
			// FIXME: shortcut to open grading for a closed item and releasing grades to be added
			//=======================================================================================
			$edit_option = '<button class="btn btn-primary" style="width:100%;" onclick="location.href=\"#\"">Edit</button>';
			echo <<<HTML
				<tr class="instructor">
					<td class="gradeable_title">{$gradeable_title}</td>
					<td class="date">{$submission_date}</td>
					<td class="option">{$submission_option}</td>
					<td class="date">{$grading_date}</td>
					<td class="option">{$grading_option}</td>
					<td class="two-options">{$edit_option}</td>
				</tr>
HTML;
		}
	}
}

//==============================================
// FIXME: links to grading page yet to be added
//==============================================
else if (isTA($username)) {
	foreach (array_merge(array("Open Items"), $all_gradeables['submission_open'], array("Closed Items"), $all_gradeables['submission_closed'], 
                         array("Items Being Graded"), $all_gradeables['grading_open'], array("Graded Items"), $all_gradeables['graded']) as $gradeable) {
		if(is_string($gradeable)) {
			echo '<tr class="bar"><td colspan="5"></td></tr>';
			echo '<tr class="colspan"><td colspan="5" style="border-bottom:2px black solid;">'.$gradeable.'</td></tr>';
		}
		else{
			if(isElectronic($gradeable)) {
				if($gradeable['ta_grading'] == "yes" && $gradeable['instructions_url'] != "") {
					$gradeable_title = '<a href="'.$gradeable['instructions_url'].'" target="_blank"><label class="has-url">'.$gradeable['gradeable_title'].'</label></a>';
				}
				else {
					$gradeable_title = '<label>'.$gradeable['gradeable_title'].'</label>';
				}

				$submission_date = $gradeable["date_submit"]->format("M j H:i ").' - '.$gradeable["date_due"]->format("M j H:i ");
				$submission_option = <<<HTML
                <button class="btn btn-primary" style="width:100%;" onclick="location.href=\'?semester='{$semester}'&course='{$course}'\\
                &assignment_id='{$gradeable["gradeable_id"]}'\'">Submit</button></td>';
HTML;
				if($gradeable['ta_grading'] == "yes") {
					$grading_date = $gradeable["date_grade"]->format("M j H:i ").' - '.$gradeable["date_released"]->format("M j H:i ");
					if($gradeable['date_grade'] > new DateTime('NOW')) {
						$grading_option = '<button class="btn btn-primary" style="width:100%;" disabled>Grade</button>';
					}
					else {
						$grading_option = '<button class="btn btn-primary" style="width:100%;" onclick="location.href=\"#\"">Grade</button>';
					}
				}
				else{
				//==================================================================
				// NOTE: If ta_grading disabled and the item is closed, should TAs still be able to see the item and item listed in being graded?
					$grading_date = "";
					$grading_option = "";
				}
			}
			else {
				$gradeable_title = '<label>'.$gradeable['gradeable_title'].'</label>';
				$submission_date = '';
				$submission_option = '';
				$grading_date = $gradeable["date_grade"]->format("M j H:i ").' - '.$gradeable["date_released"]->format("M j H:i ");
				if($gradeable['date_grade'] > new DateTime('NOW')) {
					$grading_option = '<button class="btn btn-primary" style="width:100%;" disabled>Grade</button>';
				}
				else {
					$grading_option = '<button class="btn btn-primary" style="width:100%;" onclick="location.href=\"#\"">Grade</button>';
				}
			}
			echo <<<HTML
				<tr class="ta">
					<td class="gradeable_title">{$gradeable_title}</td>
					<td class="date">{$submission_date}</td>
					<td class="option">{$submission_option}</td>
					<td class="date">{$grading_date}</td>
					<td class="option">{$grading_option}</td>
				</tr>
HTML;
		}
	}
}
else if (isStudent($username)) {
	foreach (array_merge(array("Open Items"), $all_gradeables['submission_open'], array("Closed Items"), $all_gradeables['submission_closed'], 
             $all_gradeables['grading_open'], array("Graded Items"), $all_gradeables['graded']) as $gradeable) {
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

			// add link if instructions_url provided
			$instructions_url = ($gradeable['instructions_url'] != "" ? "href=\"{$gradeable['instructions_url']}\"" : "");
			$url = ($gradeable['instructions_url'] != "" ? "{$gradeable['instructions_url']}" : "");
			$title_class = $gradeable['instructions_url'] != "" ? "class='has-url'" : "";

			echo <<<HTML
			<tr class="student">
			<td class="gradeable_title"><a {$instructions_url} href='{$url}' target="_blank"><label {$title_class}>{$gradeable["gradeable_title"]}</label></a></td>
			<td class="date">{$gradeable["date_due"]->format("M j H:i ")}</td>
			<td class="option"><button class="{$button_class}" style="width:100%;" onclick="location.href='?semester={$semester}&course={$course} \\
                        &assignment_id={$gradeable["gradeable_id"]}'">{$submission_message}</button></td>
			</tr>
HTML;
		}
	}
}

echo <<<HTML
		</table>
	</body>
<<<<<<< HEAD
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
	    else if (isset($gradeable['date_due']) && $gradeable['date_due'] > $now) {
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
    $all_gradeables = array('future'=>$future, 'submission_open'=>$submission_open, 'submission_closed'=>$submission_closed, 
                            'grading_open'=>$grading_open, 'graded'=>$graded);
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
	return $gradeable["gradeable_type"] === "Electronic File";
}

//===============================================================================
// Functions not fully tested, might be useful for replacing date checks?
function isFutureItem($gradeable) {
	// $now = new DateTime("NOW");
	return isset($gradeable['date_submit']) && $gradeable['date_submit'] > new DateTime("NOW");
	?><script>console.log("in isFutureItem");</script><?php
}

function isOpenItem($gradeable) {
	return isFutureItem($gradeable) && isset($gradeable['date_due']) && $gradeable['date_due'] > new DateTime("NOW");
	?><script>console.log("in isOpenItem");</script><?php
}

function isClosedItem($gradeable) {
	return isOpenItem($gradeable) && isset($gradeable['date_grade']) && $gradeable['date_grade'] > new DateTime("NOW");
	?><script>console.log("in isClosedItem");</script><?php
}

function isBeingGradedItem($gradeable) {
	return isClosedItem($gradeable) && isset($gradeable['date_released']) && $gradeable['date_released'] > new DateTime("NOW");
	?><script>console.log("in isBeingGradedItem");</script><?php
}

function isGradedItem($gradeable) {
	return !isBeingGradedItem($gradeable);
	?><script>console.log("in isGradedItem");</script><?php
}
//===============================================================================

?>