<?php

include "../../toolbox/functions.php";

check_administrator();

$action = $_GET['action'];

switch($action) {
    case 'new':
        $number         = intval($_GET['number']);
        $code           = intval($_GET['code']) > 0 ? intval($_GET['code']) : null;
        $checkpoints    = $_GET['checkpoints'];

        if ($number == 0) {
            exit("failure");
        }

        // don't allow duplicate lab numbers?
        $db->query("SELECT * FROM labs WHERE lab_number=?",array($number));
        if (count($db->rows()) > 0) {
            print "lab with that number already exists";
            exit("failure");
        }

        $params = array($number,"Lab ".$number,$checkpoints,$code);
        $db->query("INSERT INTO labs(lab_number, lab_title, lab_checkpoints, lab_code) VALUES (?, ?, ?, ?)",$params);

        $db->query("SELECT lab_id FROM labs WHERE lab_number=?",array($number));
        $row = $db->row();
        print "success|".$row['lab_id'];
        break;
    case 'edit':
        $id             = intval($_GET['id']);
        $number         = intval($_GET['number']);
        $code           = intval($_GET['code']) > 0 ? intval($_GET['code']) : null;
        $checkpoints    = $_GET['checkpoints'];

        if ($id == 0) {
            print "id failure";
            exit("failure");
        }

        $db->query("SELECT * FROM labs WHERE lab_id=?",array($id));
        if (count($db->rows()) != 1) {
            print "no id found";
            exit("failure");
        }

        // check if we're changing our number to something that already exists
        $lab = $db->row();
        if ($lab['lab_number'] != $number) {
            $db->query("SELECT * FROM labs WHERE lab_number=?", array($number));
            if (count($db->rows()) > 0) {
                print "lab with that number already exists";
                exit("failure");
            }
        }
        
        $params = array($number,"Lab ".$number,$checkpoints,$code,$id);
        $db->query("UPDATE labs SET lab_number=?, lab_title=?, lab_checkpoints=?, lab_code=? WHERE lab_id=?",$params);
        
        print "success|".$id;
        break;
    case 'delete':
        $id = intval($_GET['id']);
        $db->query("DELETE FROM labs WHERE lab_id=?",array($id));

        print "success|".$id;
        break;
    default:
        print "invalid action";
        exit();
}

?>