<?php

include "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

 $gradeableJSON = $_POST['gradeableJSON'];
 
 $fp = fopen(__SUBMISSION_SERVER__ . '/config/gradeable.json', 'w');
 if (!$fp){
    die('failed to open file');
 }
 
 #decode for pretty print
 fwrite($fp, json_encode(json_decode($gradeableJSON), JSON_PRETTY_PRINT));
 fclose($fp);

 # for debugging
 echo print_r($_POST);
 
 
 $g_id = $_POST['gradeable_id'];
 $g_title = $_POST['gradeable_title'];
 $g_overall_ta_instr = $_POST['ta_instructions'];
 $g_use_teams = ($_POST['team-assignment'] === 'yes') ? "true" : "false";
 $g_gradeable_type = null; 
 $g_min_grading_group=intval($_POST['minimum-grading-group']);

 abstract class GradeableType{
    const electronic_file = 0;
    const checkpoints = 1;
    const numeric = 2;
}
 
 if ($_POST['gradeable-type'] === "Electronic File"){
    $g_gradeable_type = GradeableType::electronic_file;
 }
 else if ($_POST['gradeable-type'] === "Checkpoints"){
    $g_gradeable_type = GradeableType::checkpoints;
 }
 else if ($_POST['gradeable-type'] === "Numeric"){
    $g_gradeable_type = GradeableType::numeric;
 }
 
 $g_grade_by_registration = ($_POST['section-type'] === 'reg-section') ? "true" : "false";
 $g_grade_start_date = ($_POST['date_grade']);
 $g_grade_released_date = ($_POST['date_released']);
 $g_syllabus_bucket = ($_POST['gradeable-buckets']);
 
# TODO figure out why this doesn't work
# and check to make sure that the fields are set appropriately
 
function justSpaces($str){
    return (ctype_space($str) || $str == '');
}
 

if(justSpaces($g_title)){
    die('No title given for gradeable.');
}

###############################################################
 
$action = $_GET['action'];

$db->beginTransaction();  
if ($action=='edit'){
    $params = array($g_title, $g_overall_ta_instr, $g_use_teams, $g_gradeable_type, 
                $g_grade_by_registration, $g_grade_start_date, $g_grade_released_date, 
                $g_syllabus_bucket,$g_min_grading_group, $g_id);
    $db->query("UPDATE gradeable SET g_title=?, g_overall_ta_instructions=?, g_team_assignment=?, g_gradeable_type=?, 
                g_grade_by_registration=?, g_grade_start_date=?, g_grade_released_date=?, g_syllabus_bucket=?, 
                g_min_grading_group=? WHERE g_id=?", $params);
}  
else{
    $params = array($g_id,$g_title, $g_overall_ta_instr, $g_use_teams, $g_gradeable_type, 
                $g_grade_by_registration, $g_grade_start_date, $g_grade_released_date, 
                $g_syllabus_bucket, $g_min_grading_group);
    $db->query("INSERT INTO gradeable(g_id,g_title, g_overall_ta_instructions, g_team_assignment, 
                g_gradeable_type, g_grade_by_registration, g_grade_start_date, g_grade_released_date,
                g_syllabus_bucket,g_min_grading_group) VALUES (?,?,?,?,?,?,?,?,?,?)", $params);
}
// Now that the assignment is specified create the checkpoints for checkpoint based stuffs
// The type of assignment will determine how the gradeable-component(s) are generated

function deleteComponents($lb,$ub, $g_id){
    for($i=$lb; $i<=$ub; ++$i){
        //DELETE all grades associated with these gcs
        $params = array($g_id,$i);
        $db->query("SELECT gc_id FROM gradeable_component WHERE g_id=? AND gc_order=?",$params);
        $gc_id = $db->row()['gc_id'];
        
        $db->query("DELETE FROM gradeable_component_data AS gcd WHERE gc_id=?",array($gc_id));
        $db->query("DELETE FROM gradeable_component WHERE gc_id=?", array($gc_id));
    } 
}

if ($g_gradeable_type === GradeableType::electronic_file){
    echo 'My dad is a computer';
}
else if($g_gradeable_type === GradeableType::checkpoints){
    // create a gradeable component for each checkpoint
    //figure out how many checkpoints there are
    $num_checkpoints = -1; // remove 1 for the template
    foreach($_POST as $k=>$v){
        if(strpos($k, 'checkpoint-label') !== false){
            ++$num_checkpoints;
        }    
    }
    $db->query("SELECT COUNT(*) as cnt FROM gradeable_component WHERE g_id=?", array($g_id));
    $num_old_checkpoints = intval($db->row()['cnt']);
    
    // insert the checkpoints
    for($i=1; $i<=$num_checkpoints; ++$i){
        $gc_is_extra_credit = (isset($_POST["checkpoint-extra-".strval($i)])) ? "true" : "false";
        $gc_title = $_POST['checkpoint-label-'. strval($i)];
        
        if($action=='edit' && $i <= $num_old_checkpoints){
            $params = array($gc_title, '', '', 1, "false", $gc_is_extra_credit, $g_id, $i);
            $db->query("UPDATE gradeable_component SET gc_title=?, gc_ta_comment=?, gc_student_comment=?,
                        gc_max_value=?, gc_is_text=?, gc_is_extra_credit=? WHERE g_id=? AND gc_order=?", $params);
        }
        else{
            $params = array($g_id, $gc_title, '','',1,"false",$gc_is_extra_credit,$i);
            $db->query("INSERT INTO gradeable_component(g_id, gc_title, gc_ta_comment, gc_student_comment,
                        gc_max_value,gc_is_text,gc_is_extra_credit,gc_order) VALUES (?,?,?,?,?,?,?,?)", $params);
        }
    }
    // remove deleted checkpoints 
    deleteComponents($num_checkpoints+1,$num_old_checkpoints,$g_id);
}
else if($g_gradeable_type === GradeableType::numeric){
    $db->query("SELECT COUNT(*) as cnt FROM gradeable_component WHERE g_id=?", array($g_id));
    $num_old_numerics = intval($db->row()['cnt']);
    
    $num_numeric = intval($_POST['num-numeric-items']);
    $num_text= intval($_POST['num-text-items']);
    
    for($i=1; $i<=$num_numeric+$num_text; ++$i){
        //CREATE the numeric items in gradeable component
        $gc_is_text = ($i > $num_numeric)? "true" : "false";
        if($i > $num_numeric){
            $gc_title = (isset($_POST['text-label-'. strval($i-$num_numeric)]))? $_POST['text-label-'. strval($i-$num_numeric)] : '';
            $gc_max_value = 0;
            $gc_is_extra_credit ="false";
        }
        else{
            $gc_title = (isset($_POST['numeric-label-'. strval($i)]))? $_POST['numeric-label-'. strval($i)] : '';
            $gc_max_value = (isset($_POST['max-score-'. strval($i)]))? $_POST['max-score-'. strval($i)] : 0;
            $gc_is_extra_credit = (isset($_POST['numeric-extra-'.strval($i)]))? "true" : "false";
        }
        
        if($action=='edit' && $i<=$num_old_numerics){
            $params = array($gc_title, '','',$gc_max_value, $gc_is_text, $gc_is_extra_credit,$g_id,$i);
            $db->query("UPDATE gradeable_component SET gc_title=?, gc_ta_comment=?, gc_student_comment=?, 
                        gc_max_value=?, gc_is_text=?, gc_is_extra_credit=? WHERE g_id=? AND gc_order=?", $params);
        }
        else{
            $params = array($g_id, $gc_title,'','',$gc_max_value,$gc_is_text,$gc_is_extra_credit,$i);
            $db->query("INSERT INTO gradeable_component(g_id, gc_title, gc_ta_comment, gc_student_comment, gc_max_value,
                        gc_is_text, gc_is_extra_credit, gc_order) VALUES (?,?,?,?,?,?,?,?)",$params);
        }
    }
    //remove deleted numerics
    deleteComponents($num_numeric+$num_text+1, $num_old_numerics,$g_id);
}

$db->commit();
echo 'TRANSACTION COMPLETED';

header('Location: '.__BASE_URL__.'/account/admin-gradeables.php?course='.$_GET['course']);

?>