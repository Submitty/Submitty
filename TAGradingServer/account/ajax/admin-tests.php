<?php

include "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
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
    case 'upload':
        $id = intval($_GET['id']);
        $db->query("SELECT * FROM tests WHERE test_id=?", array($id));
        if (count($db->rows()) == 0) {
            exit("no test with that number exists. failure");
        }
        $test = $db->row();
        $file = $_FILES['file'];
        if ($_FILES["file"]["error"] > 0) {
            exit("Return Code: " . $_FILES["file"]["error"]);
        }
        else {
            $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fh = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fh, $file['tmp_name']);
            if ($fileType == 'csv' && $mimeType == 'text/plain') {
                $contents = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (count($contents) == 0) {
                    exit("Empty file...");
                }
                unset($contents[0]);
                foreach($contents as $row) {
                    $row = explode(",", $row);
                    $rcs = explode("@", $row[1]);
                    $rcs = $rcs[0];
                    $total = 0;
                    $questions = array();
                    $text = array();
                    if ($test['test_questions'] > 0) {
                        for ($i = 2; $i < (2 + $test['test_questions']); $i++) {
                            if(isset($row[$i])) {
                                $questions[] = floatval($row[$i]);
                                $total += floatval($row[$i]);
                            } else {
                                $questions[] = 0;
                            }
                        }
                    }
                    if ($test['test_text_fields'] > 0) {
                        for ($i = (2 + $test['test_questions']); $i < (2 + $test['test_questions'] + $test['test_text_fields']); $i++) {
                            if (isset($row[$i])) {
                                $text[] = $row[$i];
                            }
                            else {
                                $text[] = "";
                            }
                        }
                    }

                    $question_grades = phpToPgArray($questions);
                    $text_fields = phpToPgArray($text);

                    $db->query("SELECT * FROM grades_tests WHERE test_id=? AND student_rcs=?", array($id, $rcs));
                    $temp = $db->row();

                    $db->query("SELECT student_id FROM students WHERE student_rcs=?", array($rcs));
                    if (count($db->rows()) == 0) {
                        continue;
                    }
                    $student = $db->row();
                    $student_id = $student['student_id'];

                    if(isset($temp["grade_test_value"])) {
                        // UPDATE
                        $params = array(\app\models\User::$user_id, $total, $question_grades, $text_fields, $temp["grade_test_id"]);
                        $db->query("UPDATE grades_tests SET grade_test_user_id=?, grade_test_value=?, grade_test_questions=?, grade_test_text=? WHERE grade_test_id=?", $params);
                    }
                    else {
                        // INSERT
                        $params = array($id, $student_id, $total, $question_grades, $text_fields, \app\models\User::$user_id, $rcs);
                        $db->query("INSERT INTO grades_tests (test_id, student_id, grade_test_value, grade_test_questions, grade_test_text, grade_test_user_id, student_rcs) VALUES (?,?,?,?,?,?,?)", $params);
                    }
                }
                print "success|";

            }
            else {
                exit("Not a CSV file");
            }
        }
        break;
    default:
        print "invalid action";
        exit();
}

?>