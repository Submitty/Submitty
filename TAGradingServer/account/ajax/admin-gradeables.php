<?php

include "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$action = $_GET['action'];

switch($action) {
    case 'delete':
        $id = $_GET['id'];
        $db->query("DELETE FROM gradeable WHERE g_id=?",array($id));
        $json_config = __SUBMISSION_SERVER__ . '/config/form/form_'.$id.'.json';
        if (is_file($json_config)){
            unlink($json_config);
        }
        print "success|".$id;
        break;
    default:
        print "invalid action";
        exit();
}

?>