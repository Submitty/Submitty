<?php

include "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_token']) {
    die("invalid csrf token");
}
$action = $_GET['action'];

switch($action) {
    case 'new':
        $type = $_POST['type'];
        if (!in_array($type, array('Test', 'Quiz', 'Exam'))) {
            exit("failure, invalid type '{$type}'");
        }
        $number = intval($_POST['number']);
        $questions = intval($_POST['questions']) > 0 ? intval($_POST['questions']) : 0;
        $text   = intval($_POST['text']) > 0 ? intval($_POST['text']) : 0;
        $score  = intval($_POST['score']);
        $curve  = intval($_POST['curve']);
        $locked = intval($_POST['locked']);
        $locked = ($locked == 0) ? 'FALSE' : 'TRUE';

        if ($number <= 0) {
            exit("failure");
        }

        // don't allow duplicate lab numbers?
        $db->query("SELECT * FROM tests WHERE test_number=? and test_type=?", array($number, $type));
        if (count($db->rows()) > 0) {
            print "test with that number already exists";
            exit("failure");
        }

        $params = array($type, $number, $questions, $text, $score, $curve, $locked);
        $db->query("INSERT INTO tests(test_type, test_number,test_questions,test_text_fields,test_max_grade,test_curve,test_locked) VALUES (?, ?, ?, ?, ?, ?, ?)",$params);

        $db->query("SELECT test_id FROM tests WHERE test_number=?", array($number));
        $row = $db->row();
        print "success|".$row['test_id'];
        break;
    case 'edit':
        $id             = intval($_POST['id']);
        $number         = intval($_POST['number']);
        $questions      = intval($_POST['questions']) > 0 ? intval($_POST['questions']) : 0;
        $text           = intval($_POST['text']) > 0 ? intval($_POST['text']) : 0;
        $score          = intval($_POST['score']);
        $curve          = intval($_POST['curve']);
        $locked         = intval($_POST['locked']);
        $locked         = ($locked == 0) ? 'FALSE' : 'TRUE';

        if ($id <= 0) {
            print "id cannot be less than or equal to 0";
            exit("failure");
        }

        if ($questions <= 0) {
            print "You must have at least one question on the test.";
            exit("failure");
        }

        $db->query("SELECT * FROM tests WHERE test_id=?",array($id));
        if (count($db->rows()) != 1) {
            print "no id found";
            exit("failure");
        }

        // check if we're changing our number to something that already exists
        $test = $db->row();
        if ($test['test_number'] == $number) {
            $db->query("SELECT * FROM tests WHERE test_number=?", array($number));
            if (count($db->rows()) == 0) {
                print "no test with that number exists";
                exit("failure");
            }
        }

        $params = array($number, $questions, $text, $score, $curve, $locked, $id);
        $db->query("UPDATE tests SET test_number=?, test_questions=?, test_text_fields=?, test_max_grade=?, test_curve=?, test_locked=? WHERE test_id=?",$params);

        print "success|".$id;
        break;
    case 'delete':
        $id = intval($_GET['id']);
        $db->query("DELETE FROM tests WHERE test_id=?",array($id));

        print "success|".$id;
        break;
    default:
        print "invalid action";
        exit();
}

?>