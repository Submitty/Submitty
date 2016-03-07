<?php

include "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf']) {
    die("invalid csrf token");
}

$action = $_GET['action'];

switch($action) {
    case 'sequence':

        $db->query("SELECT setval('rubric_sequence',(SELECT GREATEST(MAX(rubric_id)+1,
                    nextval('rubric_sequence'))-1 FROM rubrics))");
        $db->query("SELECT setval('question_sequence',(SELECT GREATEST(MAX(question_id)+1,
                    nextval('question_sequence'))-1 FROM questions))");
        $db->query("SELECT setval('hw_grading_sec_seq',(SELECT GREATEST(MAX(hgs_id)+1,
                    nextval('hw_grading_sec_seq'))-1 FROM homework_grading_sections))");

        print "success|";
        break;
    case 'delete':
        $id = intval($_GET['id']);
        $db->query("DELETE FROM rubrics WHERE rubric_id=?",array($id));

        print "success|".$id;
        break;
    default:
        print "invalid action";
        exit();
}

?>