<?php
include "../../toolbox/functions.php";

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    exit("invalid csrf token");
}

$g_id = $_GET["g_id"];
$check = intval($_GET["check"]);
$rcs = $_GET["rcs"];
$mode = intval($_GET["mode"]);
$db->query("SELECT student_id FROM students WHERE student_rcs=?",array($rcs));
$row = $db->row();
$id = $row['student_id'];

if($check == "all") {
    $params = array($g_id);
    $db->query("SELECT gc_title FROM gradeable_component WHERE g_id=?", $params);
    $checkpoints_gradeable_row = array();
    foreach($db->rows() as $row){
        array_push($checkpoints_gradeable_row, $row['gc_title']);
    }
    $i = 1;
    $checks = array();
    while($i <= count($checkpoints_gradeable_row)){
        array_push($checks, $i);
        ++$i;
    }
}
else {
    $checks = array($check);
}

foreach($checks as $check) {
    //find the grade entry for one checkpoint
    $params = array($g_id, $rcs, $check);
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

    $temp = $db->row();
    $old_mode = (isset($temp["gcd_score"]) ? $temp["gcd_score"] : 0);

    if($mode != $old_mode or true) {
        if(isset($temp["gcd_score"])) {
            // UPDATE EXISTING SCORE
            $params = array($user_id, $temp['gd_id']);
            $db->query("UPDATE gradeable_data SET gd_grader_id=? WHERE gd_id=?", $params);
            
            $params = array($mode,$temp["gc_id"], $temp["gd_id"]);
            $db->query("UPDATE gradeable_component_data SET gcd_score=? WHERE gc_id=? AND gd_id=?", $params);
        }
        else {
            
            // INSERT
            //CHECK if the gradeable data exists, if not create it first
            $params = array($g_id, $rcs);
            $db->query("SELECT gd_id, COUNT(*) AS cnt 
            FROM gradeable_data AS gd INNER JOIN gradeable g ON gd.g_id = g.g_id
            WHERE g.g_id =?
            AND  gd_user_id =?
            GROUP BY gd_id
            ",$params);
            $row = $db->row();
            if ($row['cnt']==0){
                //TODO FILL IN THE CORRECT STATUS?
               $params = array($g_id, $rcs, $user_id, '', 0,0,1); 
               $db->query("INSERT INTO gradeable_data(g_id,gd_user_id,gd_grader_id,gd_overall_comment, gd_status,gd_late_days_used,gd_active_version) VALUES(?,?,?,?,?,?,?)", $params); 
               //GET the gd_id and set it here
               $gd_id = \lib\Database::getLastInsertId('gradeable_data_gd_id_seq');
            }
            else{
                $gd_id = intval($row['gd_id']);
            }
            
            //THEN CREATE THE GRADE FOR THE CHECKPT
            
            //FIGURE OUT THE gc_id from gc_order and g_id
            $params = array($g_id,$check);
            $db->query("SELECT gc_id 
            FROM
            gradeable AS g INNER JOIN gradeable_component as gc ON g.g_id = gc.g_id
            WHERE g.g_id=?
            AND gc_order=?
            ",$params);
            
            $gc_id = $db->row()['gc_id'];
            $params = array($gc_id, $gd_id, $mode,'');                                  
            $db->query("INSERT INTO gradeable_component_data(gc_id, gd_id, gcd_score,gcd_component_comment) VALUES (?,?,?,?)", $params);
        }
}

echo "updated";