<?php
include "../../toolbox/functions.php";

use \models\User;

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}
if(isset($_POST['action']) && !empty($_POST['action'])){
    $action = $_POST['action'];

    switch ($action){
        case 'csv': csvupload(); break;
        case 'standard':
        default: standard(); break;
    }
}
else{
    standard();
}

function standard()
{
    $g_id = $_GET["id"];
    $user_id = $_GET["user_id"];
    $grade = floatval($_GET["grade"]);

    global $db;

    $params = array($g_id);
    $db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='false'", $params);
    $num_numeric = $db->row()['cnt'];
    $db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='true'", $params);
    $num_text = $db->row()['cnt'];


    $questions = array();
    for ($i = 0; $i < $num_numeric; $i++) {
        $questions[$i] = floatval($_GET['q' . $i]);
    }

    $text = array();
    $j = 0;
    for ($i = $num_numeric; $i < $num_numeric + $num_text; ++$i) {
        $text[$j] = htmlentities($_GET['t' . $i]);
        $text[$j] = urldecode($text[$j]); //decodes the & and possibly other things
        ++$j;
    }

    $params = array($g_id, $user_id);

//FIRST check if there is gradeable data for this gradeable and user_id
    $db->query("SELECT gd_id FROM gradeable AS g INNER JOIN gradeable_data AS gd ON g.g_id=gd.g_id WHERE g.g_id=? AND gd_user_id=?", $params);
    $row = $db->row();

    if (empty($row)) {
        // GRADEABLE DATA DOES NOT EXIST
        //TODO FILL IN THE CORRECT STATUS?
        $params = array($g_id, $user_id, User::$user_id, '', 0, 0, 1);
        $db->query("INSERT INTO gradeable_data (g_id, gd_user_id, gd_grader_id, gd_overall_comment, gd_status, gd_late_days_used, gd_active_version)
                VALUES(?,?,?,?,?,?,?)", $params);
        $gd_id = \lib\Database::getLastInsertId('gradeable_data_gd_id_seq');
    } else {
        $gd_id = $row['gd_id'];
    }

    for ($i = 1; $i <= $num_numeric + $num_text; ++$i) {
        $params = array($g_id, $user_id, $i);
        $db->query("SELECT 
        gc.gc_id
        ,gd.gd_id
        ,gcd.gcd_score
    FROM gradeable AS g 
        INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id
        INNER JOIN gradeable_data AS gd ON g.g_id=gd.g_id
        INNER JOIN gradeable_component_data AS gcd ON gcd.gc_id=gc.gc_id AND gcd.gd_id=gd.gd_id
    WHERE g.g_id=?
    AND gd_user_id=?
    AND gc_order=?
        ", $params);
        $tmp = $db->row();

        //UPDATE the existing score
        if (isset($tmp["gcd_score"])) {
            //Numeric item - update score
            if ($i <= $num_numeric) {
                $params = array($questions[$i - 1], $tmp['gc_id'], $tmp['gd_id']);
                $db->query('UPDATE gradeable_component_data SET gcd_score=? WHERE gc_id=? AND gd_id=?', $params);
            } else { // text item update comment
                $params = array($text[$i - $num_numeric - 1], $tmp['gc_id'], $tmp['gd_id']);
                $db->query('UPDATE gradeable_component_data SET gcd_component_comment=? WHERE gc_id=? AND gd_id=?', $params);
            }
        } else {
            // CREATE THE new score
            //Numeric item - update score
            //find the gradeable component id
            $params = array($g_id, $i);
            $db->query("SELECT gc_id FROM gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc.gc_order=?", $params);
            $gc_id = $db->row()['gc_id'];
            if ($i <= $num_numeric) {
                $params = array($gc_id, $gd_id, $questions[$i - 1], '');
            } else { // text item update comment
                $params = array($gc_id, $gd_id, 0, $text[$i - $num_numeric - 1]);
            }
            $db->query("INSERT INTO gradeable_component_data (gc_id, gd_id, gcd_score, gcd_component_comment) VALUES (?,?,?,?)", $params);
        }
    }
}

function csvupload(){

    global $db;

    $csv = $_POST['parsedCsv'];
    $csv_array = array();
    foreach ($csv as $line){
        $line = addslashes($line);
        $csv_array[] = $line;
    }

    $params = array('{"'.implode('","', $csv_array).'"}', $_GET["g_id"], User::$user_id);

    $db->query("SELECT csv_To_Numeric_Gradeable(?, ?, ?)", $params);

}
echo "updated";