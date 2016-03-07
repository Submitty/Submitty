<?php

include "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf']) {
    die("invalid csrf token");
}

$action = $_GET['action'];

switch($action) {
    case 'new':
        $id = strtolower($_POST['id']);
        $name = $_POST['name'];
        $score  = intval($_POST['score']) > 0 ? intval($_POST['score']) : 0;
        $due_date = $_POST['due_date'];

        // don't allow duplicate lab numbers?
        $db->query("SELECT * FROM other_grades WHERE other_id=?",array($id));
        if (count($db->rows()) > 0) {
            print "other with that exists already";
            exit("failure");
        }

        $params = array($id, $name, $score, $due_date);
        $db->query("INSERT INTO other_grades(other_id, other_name, other_score, other_due_date) VALUES (?, ?, ?, ?)",$params);
        $db->query("SELECT oid from other_grades WHERE other_id=? AND other_name=?", array($id, $name));
        $row = $db->row();
        print "success|".$row['oid'];
        break;
    case 'edit':
        $oid = intval($_POST['oid']);
        $id = strtolower($_POST['id']);
        $name = $_POST['name'];
        $score  = intval($_POST['score']) > 0 ? intval($_POST['score']) : 0;
        $due_date = $_POST['due_date'];

        $db->query("SELECT * FROM other_grades WHERE oid=?",array($oid));
        if (count($db->rows()) == 0) {
            print "no other with that id exists";
            exit("failure");
        }

        $params = array($id, $name, $score, $due_date, $oid);
        $db->query("UPDATE other_grades SET other_id=?, other_name=?, other_score=?, other_due_date=? WHERE oid=?",$params);

        print "success|".$oid;
        break;
    case 'delete':
        $id = intval($_GET['oid']);
        $db->query("DELETE FROM other_grades WHERE oid=?",array($id));

        print "success|".$id;
        break;
    default:
        print "invalid action";
        exit();
}

?>