<?php

include "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$rubric_parts_sep = (isset($_POST['rubric_parts_sep']) && intval($_POST['rubric_parts_sep']) == 1) ? 1 : 0;
$rubric_late_days = intval($_POST['rubric_late_days']);
$rubric_parts = intval($_POST['part_count']);
$part_submission_ids = array();

if (empty($_POST['rubric_name']) || empty($_POST['rubric_submission_id'])) {
    die("You must fill out a rubric name and submission id");
}

for ($i = 1; $i <= $rubric_parts; $i++) {
    if ($rubric_parts_sep) {
        if(!isset($_POST["rubric_part_{$i}_id"]) || empty($_POST["rubric_part_{$i}_id"])) {
            die("Missing submission id for part {$i}.");
        }
        if(in_array($_POST["rubric_part_{$i}_id"], $part_submission_ids)) {
            die("The submission id '" . $_POST["rubric_part_{$i}_id"] . "' was already used for a different part.");
        }
        $part_submission_ids[] = $_POST["rubric_part_{$i}_id"];
    }
    else {
        $part_submission_ids[] = "_part{$i}";
    }
}

$params = array($_POST['date_submit'], $rubric_parts_sep, $rubric_late_days, $_POST['rubric_name'], $_POST['rubric_submission_id'], implode(",", $part_submission_ids));
$action = $_GET['action'];

if ($action == 'edit') {
    $rubric_id = intval($_GET['id']);
    $db->query("UPDATE rubrics SET rubric_due_date=?, rubric_parts_sep=?, rubric_late_days=?, rubric_name=?, rubric_submission_id=?, rubric_parts_submission_id=? WHERE rubric_id=?",
               array($_POST['date_submit'], $rubric_parts_sep, $rubric_late_days, $_POST['rubric_name'], $_POST['rubric_submission_id'], implode(",", $part_submission_ids), $rubric_id));
}
else {
    $db->query("INSERT INTO rubrics (rubric_due_date, rubric_parts_sep, rubric_late_days, rubric_name, rubric_submission_id, rubric_parts_submission_id) VALUES (?,?,?,?,?,?)", $params);
    $rubric_id = \lib\Database::getLastInsertId('rubric_sequence');
}

$part = (__USE_AUTOGRADER__) ? 0 : 1;

$questions = array();
if ($action == 'edit') {
    $db->query("SELECT COUNT(*) as cnt FROM grades WHERE rubric_id=?", array($rubric_id));
    if ($db->row()['cnt'] == 0) {
        $db->query("DELETE FROM questions WHERE rubric_id=?", array($rubric_id));
    }

    $db->query("SELECT * FROM questions WHERE rubric_id=? ORDER BY question_part_number, question_number", array($rubric_id));
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

        if(!isset($_POST["ec-" . $part . "-" . $question]) || $_POST["ec-{$part}-{$question}"] != "on") {
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
            $params = array($rubric_id, $part, $question, $_POST["comment-" . $part . "-" . $question], $_POST["ta-" . $part . "-" . $question], $_POST["point-" . $part . "-" . $question], $extra_credit);
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
    $db->query("DELETE FROM homework_grading_sections WHERE rubric_id=?", array($rubric_id));
}
$db->query("SELECT * FROM users");
foreach ($db->rows() as $user) {
    if(isset($_POST["{$user['user_id']}-section"])) {
        $sections = explode(",",$_POST["{$user['user_id']}-section"]);
        $sections = array_map(function($n) { return intval($n); }, $sections);
        foreach ($sections as $section) {
            if (in_array($section, $valid)) {
                $params = array($user['user_id'], $rubric_id, $section);
                $db->query("INSERT INTO homework_grading_sections (user_id, rubric_id, grading_section_id) VALUES (?,?,?)", $params);
            }
        }
    }
}

header('Location: '.__BASE_URL__.'/account/admin-rubrics.php?course='.$_GET['course']);

?>