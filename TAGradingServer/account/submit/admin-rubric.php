<?php 

include "../../toolbox/functions.php";

check_administrator();

    $rubric_parts_sep = (isset($_POST['rubric_parts_sep']) && $_POST['rubric_parts_sep'] == 1) ? "true" : "false";
    $rubric_late_days = intval($_POST['rubric_late_days']);
	$params = array($_POST['rubric'], $_POST['date_submit'], $rubric_parts_sep, $rubric_late_days);

    $action = $_GET['action'];
    $id = intval($_GET['id']);

    if ($action == 'edit') {
        $db->query("UPDATE rubrics SET rubric_due_date=?, rubric_parts_sep=?, rubric_late_days=? WHERE rubric_id=?", 
                   array($_POST['date_submit'], $rubric_parts_sep, $rubric_late_days, $id));        
    }
    else {
        $db->query("INSERT INTO rubrics (rubric_number, rubric_due_date, rubric_parts_sep, rubric_late_days) VALUES (?,?,?,?)", $params);
    }

	$params = array($_POST['rubric']);
	$db->query("SELECT rubric_id FROM rubrics WHERE rubric_number=?", $params);
	$row = $db->row();
	if(isset($row['rubric_id']))
	{
		$rubric = intval($row['rubric_id']);	
	}
	else
	{
		$rubric = 1;
	}
	
	$part = (__USE_AUTOGRADER__) ? 0 : 1;

    $questions = array();
    if ($action == 'edit') {
        $db->query("SELECT * FROM questions WHERE rubric_id=? ORDER BY question_part_number, question_number", array($id));
        foreach ($db->rows() as $row) {
            $questions[$row['question_part_number']][$row['question_number']] = $row;
        }
    }

	while(true) {
		$question = 1;
		
		if(!isset($_POST["comment-" . $part . "-" . $question])) {
			break;
		}
		
		while(true) {
			if(!isset($_POST["comment-" . $part . "-" . $question])) {
				break;
			}

			if(!isset($_POST["ec-" . $part . "-" . $question])) {
				$extra_credit = 0;
			}
			else {
				$extra_credit = 1;
			}
            
            if (isset($questions[$part][$question])) {
                $params = array($_POST["comment-" . $part . "-" . $question], $_POST["ta-" . $part . "-" . $question], $_POST["point-" . $part . "-" . $question], $extra_credit, $questions[$part][$question]['question_id']);
                $db->query("UPDATE questions SET question_message=?, question_grading_note=?, question_total=?, question_extra_credit=? WHERE question_id=?", $params);
                if (intval($_POST['point-'.$part.'-'.$question]) < $questions[$part][$question]['question_total']) {
                    $db->query("
UPDATE grades_questions 
SET 
    grade_question_score=case when grade_question_score > ? then ? else grade_question_score end 
WHERE 
    question_id=?", array(intval($_POST['point-'.$part.'-'.$question]), intval($_POST['point-'.$part.'-'.$question]), $questions[$part][$question]['question_id']));
                }
            }
            else {
                // TODO: we should bundle this together as just one insert using following format:
                // INSERT INTO questions (...) VALUES (...), (...), ...;
                $params = array($rubric, $part, $question, $_POST["comment-" . $part . "-" . $question], $_POST["ta-" . $part . "-" . $question], $_POST["point-" . $part . "-" . $question], $extra_credit);
                $db->query("INSERT INTO questions (rubric_id, question_part_number, question_number, question_message, question_grading_note, question_total, question_extra_credit) VALUES (?,?,?,?,?,?,?)", $params);
            }
			$question++;
		}
		$part++;
	}
    
    $db->query("SELECT student_grading_id FROM students GROUP BY student_grading_id");
    $valid = array();
    foreach($db->rows() as $row) {
        $valid[] = $row['student_grading_id'];
    }
    if ($action == 'edit') {
        $db->query("DELETE FROM homework_grading_sections WHERE rubric_id=?", array($rubric));
    }
	$db->query("SELECT * FROM users");
	foreach ($db->rows() as $user) {
		if(isset($_POST["{$user['user_id']}-section"])) {
            $sections = explode(",",$_POST["{$user['user_id']}-section"]);
            $sections = array_map(function($n) { return intval($n); }, $sections);
            foreach ($sections as $section) {
			    if (in_array($section, $valid)) {
                    $params = array($user['user_id'], $rubric, $section);
                    $db->query("INSERT INTO homework_grading_sections (user_id, rubric_id, grading_section_id) VALUES (?,?,?)", $params);
                }
			}
	    }
    }

	header('Location: '.__BASE_URL__.'/account/admin-rubrics.php?course='.$_GET['course']);

?>