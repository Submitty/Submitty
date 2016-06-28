<?php
include "../../toolbox/functions.php";

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$g_id = $_GET["id"];

$params = array($test);
$db->query("SELECT * FROM gradeable WHERE g_id=?", $params);
$test_details = $db->row();

$rcs = $_GET["rcs"];
$grade = floatval($_GET["grade"]);
$db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='false'", $params);
$num_numeric = $db->row()['cnt'];
$db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='true'", $params);
$num_text = $db->row()['cnt'];

$questions = array();
for ($i = 0; $i < $num_numeric; $i++) {
    $questions[] = floatval($_GET['q'.$i]);
}

$text = array();
for ($i = 0; $i < $num_text; $i++) {
    $text[] = htmlentities($_GET['t'.$i]);
}

$question_grades = phpToPgArray($questions);
$text_fields = phpToPgArray($text);

$params = array($g_id, $rcs);

//INSERT the grades and values for each of the things

//FIRST check if there is gradeable data for this gradeable and rcs
$db->query("SELECT gd_id, COUNT(*) AS cnt FROM gradeable AS g INNER JOIN gradeable_component AS gc WHERE g.g_id=? AND gd_user_id=? GROUP BY gd_id", $params);
$row = $db->row();
if ($row['cnt'] === 0){
   // GRADEABLE DATA DOES NOT EXIST 
   //TODO FILL IN THE CORRECT STATUS?
   $params = array($g_id, $rcs, $user_id, '', 0,0,1); 
   $db->query("INSERT INTO gradeable_data (g_id, gd_user_id, gd_grader_id, gd_overall_comment, gd_status, gd_late_days_used, gd_active_version)
                VALUES(?,?,?,?,?,?,?)", $params);
   $gd_id = \lib\Database::getLastInsertId('gradeable_data_gd_id_seq');
}
else{
   // GRADEABLE DATA EXISTS
   $gd_id = $row['gd_id'];
}

for ($i=1; $i<=num_numeric+num_text; ++$i){
    // If the score exists, just update it
    $params = array($g_id, $rcs, $i);
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
    if (isset($tmp["gcd_score"])){
        //Numeric item - update score
        if ($i<=$num_numeric){
            $params = array($question_grades[$i],$tmp['gc_id'],$tmp['gd_id']);
            $db->query('UPDATE gradeable_component_data SET gcd_score=? WHERE gc_id=? AND gd_id=?', $params);
        }else{ // text item update comment
            $params = array($text_fields[$i-$num_numeric], $tmp['gc_id'],$tmp['gd_id']);
            $db->query('UPDATE gradeable_component_data SET gcd_comment=? WHERE gc_id=? AND gd_id=?', $params);
        }
    } 
    else{
        // CREATE THE new score
         //Numeric item - update score
        if ($i<=$num_numeric){
            $params = array($tmp['gc_id'],$tmp['gd_id'], $question_grades[$i],'');
        }else{ // text item update comment
            $params = array($tmp['gc_id'],$tmp['gd_id'], 0,$text_fields[$i-$num_numeric]);
        }
        $db->query("INSERT INTO gradeable_component_data (gc_id, gd_id, gcd_score, gcd_comment) VALUES (?,?,?,?)",$params);
    }
}

echo "updated";