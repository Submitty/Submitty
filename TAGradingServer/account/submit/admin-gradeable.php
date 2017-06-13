<?php

// TODO MORE error checking
// TODO functionalize more

include "../../toolbox/functions.php";


check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

 class Gradeable{
     protected $g_id;
     protected $g_title;
     protected $g_instructions_url;
     protected $g_overall_ta_instr;
     protected $g_team_assignment;
     protected $g_gradeable_type;
     protected $g_min_grading_group;
     protected $g_grade_by_registration;
     protected $g_ta_view_start_date;
     protected $g_grade_start_date;
     protected $g_grade_released_date;
     protected $g_syllabus_bucket;
     
    function __construct($params){
         $this->g_id = $params['gradeable_id'];
         $this->g_title = $params['gradeable_title'];
         $this->g_instructions_url = $params['instructions_url'];
         $this->g_overall_ta_instr = $params['ta_instructions'];
         $this->g_team_assignment = $params['team_assignment'];
         $this->g_gradeable_type = $params['gradeable_type'];
         $this->g_min_grading_group= $params['min_grading_group'];
         $this->g_grade_by_registration = $params['section_type'];
         $this->g_ta_view_start_date = $params['date_ta_view'];
         $this->g_grade_start_date = $params['date_grade'];
         $this->g_grade_released_date = $params['date_released'];
         $this->g_syllabus_bucket = $params['bucket'];
    }
    
     /**
      * @param \lib\Database $db
      */
    function updateGradeable($db){
        $params = array($this->g_title, $this->g_overall_ta_instr, $this->g_team_assignment, $this->g_gradeable_type,
                        $this->g_grade_by_registration, $this->g_grade_start_date, $this->g_grade_released_date,
                        $this->g_syllabus_bucket, $this->g_min_grading_group, $this->g_instructions_url,
                        $this->g_ta_view_start_date , $this->g_id);
        $db->query("UPDATE gradeable SET g_title=?, g_overall_ta_instructions=?, g_team_assignment=?, g_gradeable_type=?, 
                    g_grade_by_registration=?, g_grade_start_date=?, g_grade_released_date=?, g_syllabus_bucket=?, 
                    g_min_grading_group=?, g_instructions_url=?, g_ta_view_start_date=? WHERE g_id=?", $params);
    }
    
     /**
      * @param \lib\Database $db
      */
    function createGradeable($db){
        $params = array(
            $this->g_id,
            $this->g_title,
            $this->g_instructions_url,
            $this->g_overall_ta_instr,
            $this->g_team_assignment,
            $this->g_gradeable_type,
            $this->g_grade_by_registration,
            $this->g_ta_view_start_date,
            $this->g_grade_start_date,
            $this->g_grade_released_date,
            $this->g_min_grading_group,
            $this->g_syllabus_bucket);

        $db->query("
INSERT INTO gradeable(
  g_id, 
  g_title, 
  g_instructions_url,
  g_overall_ta_instructions, 
  g_team_assignment, 
  g_gradeable_type, 
  g_grade_by_registration,
  g_ta_view_start_date,
  g_grade_start_date, 
  g_grade_released_date, 
  g_min_grading_group,
  g_syllabus_bucket
  ) 
  VALUES (
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?, 
    ?
  )", $params);
    }
    
     /**
      * @param \lib\Database $db
      * @param $lb
      * @param $ub
      */
     function deleteComponents($db,$lb,$ub){
         // TODO REWRITE THIS could be done in one query
        for($i=$lb; $i<=$ub; ++$i){
            //DELETE all grades associated with these gcs
            $params = array($this->g_id,$i);
            $db->query("SELECT gc_id FROM gradeable_component WHERE g_id=? AND gc_order=?",$params);
            $row = $db->row();
            if (!isset($row['gc_id'])) {
                continue;
            }

            $gc_id = $row['gc_id'];
            $db->query("DELETE FROM gradeable_component_data AS gcd WHERE gc_id=?",array($gc_id));
            $db->query("DELETE FROM gradeable_component WHERE gc_id=?", array($gc_id));
        }
    }
    
    //Overridden function, polymorphism
    function createComponents($db, $action, $add_args){}
    
    function get_GID(){
        return $this->g_id;
    }

    function getType(){
        return $this->g_gradeable_type;
    }
    
     /**
      * @param \lib\Database $db
      * @param $graders
      */
    function setupRotatingSections($db, $graders){
        if ($this->g_grade_by_registration === 'true') return;
        
        // delete all exisiting rotating sections
        $db->query("DELETE FROM grading_rotating WHERE g_id=?", array($this->g_id));
        foreach ($graders as $grader=>$sections){
            foreach($sections as $i=>$section){
                $db->query("INSERT INTO grading_rotating(g_id, user_id, sections_rotating_id) VALUES(?,?,?)", array($this->g_id,$grader,$section));
            }
        }
    }
 }
 
 class ElectronicGradeable extends Gradeable{
    private $date_submit;
    private $date_due;
    private $is_repo;
    private $subdirectory;
    private $ta_grading;
    private $config_path;
    private $late_days;
    private $point_precision;

     function __construct($params){
         parent::__construct($params);
         $this->date_submit = $params['date_submit'];
         $this->date_due =$params['date_due'];
         $this->is_repo = $params['is_repo'];
         $this->subdirectory =$params['subdirectory'];
         $this->ta_grading = $params['ta_grading'];
         $this->config_path = $params['config_path'];
         $this->late_days = $params['late_days'];
         $this->point_precision = $params['point_precision'];
     }
     
     //TODO extract to multiple functions
     function createComponents($db, $action, $add_args){
        if ($action=='edit'){
            $params = array($this->date_submit, $this->date_due, $this->is_repo,
                            $this->subdirectory, $this->ta_grading, $this->config_path, $this->late_days, $this->point_precision, $this->g_id);
            $db->query("UPDATE electronic_gradeable SET eg_submission_open_date=?,eg_submission_due_date=?, 
                        eg_is_repository=?, eg_subdirectory=?, eg_use_ta_grading=?, eg_config_path=?, eg_late_days=?, eg_precision=? WHERE g_id=?", $params);
        }
        else{
            $params = array($this->g_id, $this->date_submit, $this->date_due,
                            $this->is_repo, $this->subdirectory, $this->ta_grading, $this->config_path, $this->late_days, $this->point_precision);
            $db->query("INSERT INTO electronic_gradeable(g_id, eg_submission_open_date, eg_submission_due_date, 
                eg_is_repository, eg_subdirectory, eg_use_ta_grading, eg_config_path, eg_late_days, eg_precision) VALUES(?,?,?,?,?,?,?,?,?)", $params);
        }

        $num_questions = 0;
        foreach($add_args as $k=>$v){
            if(strpos($k,'comment_title_') !== false){
                ++$num_questions;
            }
        }
        $db->query("SELECT COUNT(*) as cnt FROM gradeable_component WHERE g_id=?", array($this->g_id));
        $num_old_questions = intval($db->row()['cnt']);
        //insert the questions
        for ($i=1; $i<=$num_questions; ++$i){
            $gc_title = $add_args["comment_title_".strval($i)];
            $gc_ta_comment = $add_args["ta_comment_".strval($i)];
            $gc_student_comment = $add_args["student_comment_".strval($i)];
            $gc_max_value = $add_args['points_'.strval($i)];
            $gc_is_text = "false";
            $gc_is_ec = (isset($add_args['eg_extra_'.strval($i)]) && $add_args['eg_extra_'.strval($i)]=='on')? "true" : "false";
            if($action=='edit' && $i<=$num_old_questions){
                //update the values for the electronic gradeable
                $params = array($gc_title, $gc_ta_comment, $gc_student_comment, $gc_max_value, $gc_is_text, $gc_is_ec, $this->g_id,$i);
                $db->query("UPDATE gradeable_component SET gc_title=?, gc_ta_comment=?,gc_student_comment=?, gc_max_value=?, 
                            gc_is_text=?, gc_is_extra_credit=? WHERE g_id=? AND gc_order=?", $params);
            }
            else{
                $params = array($this->g_id, $gc_title, $gc_ta_comment, $gc_student_comment, $gc_max_value, $gc_is_text, $gc_is_ec,$i);
                $db->query("INSERT INTO gradeable_component(g_id, gc_title, gc_ta_comment, gc_student_comment, gc_max_value, 
                            gc_is_text, gc_is_extra_credit, gc_order) VALUES(?,?,?,?,?,?,?,?)",$params);
            }
        }
        $this->deleteComponents($db, $num_questions+1,$num_old_questions);
     }
 }
 
 class CheckpointGradeable extends Gradeable{
    function __construct($params){
        parent::__construct($params);
    }
    //TODO EXTRACT TO MULTIPLE FUNCTIONS
    function createComponents($db, $action, $add_args){
         // create a gradeable component for each checkpoint
        $num_checkpoints = -1; // remove 1 for the template
        foreach($add_args as $k=>$v){
            if(strpos($k, 'checkpoint_label') !== false){
                ++$num_checkpoints;
            }
        }
        $db->query("SELECT COUNT(*) as cnt FROM gradeable_component WHERE g_id=?", array($this->g_id));
        $num_old_checkpoints = intval($db->row()['cnt']);
        
        // insert the checkpoints
        for($i=1; $i<=$num_checkpoints; ++$i){
            $gc_is_extra_credit = (isset($add_args["checkpoint_extra_".strval($i)])) ? "true" : "false";
            $gc_title = $add_args['checkpoint_label_'. strval($i)];
            
            if($action=='edit' && $i <= $num_old_checkpoints){
                $params = array($gc_title, '', '', 1, "false", $gc_is_extra_credit, $this->g_id, $i);
                $db->query("UPDATE gradeable_component SET gc_title=?, gc_ta_comment=?, gc_student_comment=?,
                            gc_max_value=?, gc_is_text=?, gc_is_extra_credit=? WHERE g_id=? AND gc_order=?", $params);
            }
            else{
                $params = array($this->g_id, $gc_title, '','',1,"false",$gc_is_extra_credit,$i);
                $db->query("INSERT INTO gradeable_component(g_id, gc_title, gc_ta_comment, gc_student_comment,
                            gc_max_value,gc_is_text,gc_is_extra_credit,gc_order) VALUES (?,?,?,?,?,?,?,?)", $params);
            }
        }
        // remove deleted checkpoints
        $this->deleteComponents($db, $num_checkpoints+1,$num_old_checkpoints);
    }
 }

 class NumericGradeable extends Gradeable{
    function __construct($params){
        parent::__construct($params);
    }
    
    //TODO split into multiple functions
     /**
      * @param \lib\Database $db
      * @param $action
      * @param $add_args
      */
    function createComponents($db, $action, $add_args){
        $db->query("SELECT COUNT(*) as cnt FROM gradeable_component WHERE g_id=?", array($this->g_id));
        $num_old_numerics = intval($db->row()['cnt']);
        
        $num_numeric = intval($add_args['num_numeric_items']);
        $num_text= intval($add_args['num_text_items']);
        
        for($i=1; $i<=$num_numeric+$num_text; ++$i){
            //CREATE the numeric items in gradeable component
            $gc_is_text = ($i > $num_numeric)? "true" : "false";
            if($i > $num_numeric){
                $gc_title = (isset($add_args['text_label_'. strval($i-$num_numeric)]))? $add_args['text_label_'. strval($i-$num_numeric)] : '';
                $gc_max_value = 0;
                $gc_is_extra_credit ="false";
            }
            else{
                $gc_title = (isset($add_args['numeric_label_'. strval($i)]))? $add_args['numeric_label_'. strval($i)] : '';
                $gc_max_value = (isset($add_args['max_score_'. strval($i)]))? $add_args['max_score_'. strval($i)] : 0;
                $gc_is_extra_credit = (isset($add_args['numeric_extra_'.strval($i)]))? "true" : "false";
                if ($gc_max_value==0){
                    die('Max score cannot be 0 [Question '.$i.']');
                }
            }
            
            if($action=='edit' && $i<=$num_old_numerics){
                $params = array($gc_title, '','',$gc_max_value, $gc_is_text, $gc_is_extra_credit,$this->g_id,$i);
                $db->query("UPDATE gradeable_component SET gc_title=?, gc_ta_comment=?, gc_student_comment=?, 
                            gc_max_value=?, gc_is_text=?, gc_is_extra_credit=? WHERE g_id=? AND gc_order=?", $params);
            }
            else{
                $params = array($this->g_id, $gc_title,'','',$gc_max_value,$gc_is_text,$gc_is_extra_credit,$i);
                $db->query("INSERT INTO gradeable_component(g_id, gc_title, gc_ta_comment, gc_student_comment, gc_max_value,
                            gc_is_text, gc_is_extra_credit, gc_order) VALUES (?,?,?,?,?,?,?,?)",$params);
            }
        }
        //remove deleted numerics
        $this->deleteComponents($db, $num_numeric+$num_text+1, $num_old_numerics);
    }
 }
 
 function writeFormJSON($g_id, $gradeableJSON){
    $fp = fopen(__SUBMISSION_SERVER__ . '/config/form/form_'.$g_id.'.json', 'w');
    if (!$fp){
        die('failed to open file');
    }
    #decode for pretty print

    fwrite($fp, json_encode(json_decode(urldecode($gradeableJSON)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fclose($fp);
 }

abstract class GradeableType{
    const electronic_file = 0;
    const checkpoints = 1;
    const numeric = 2;
}
 
function constructGradeable ($db, $request_args){
    $g_id = $request_args['gradeable_id'];
    if ($g_id === null or $g_id === "") {
     throw new Exception("Gradeable id cannot be blank or null");
    }

    if ($_GET['action'] != 'edit'){
     $db->query("SELECT COUNT(*) AS cnt FROM gradeable WHERE g_id=?", array($g_id));
     if ($db->row()['cnt'] > 0){
        throw new Exception('Gradeable already exists');
     }
    }

    $g_title = $request_args['gradeable_title'];
    $g_instructions_url = $request_args['instructions_url'];
    $g_overall_ta_instr = $request_args['ta_instructions'];
    $g_team_assignment = (isset($request_args['team_assignment']) && $request_args['team_assignment'] === 'yes') ? "true" : "false";
    $g_min_grading_group=(isset($request_args['minimum_grading_group'])) ? intval($request_args['minimum_grading_group']) : 1;
    $g_grade_by_registration = (isset($request_args['section_type']) && $request_args['section_type'] === 'reg_section') ? "true" : "false";
    $g_ta_view_start_date = $request_args['date_ta_view'];
    $g_grade_start_date = $request_args['date_grade'];
    $g_grade_released_date = $request_args['date_released'];
    $g_syllabus_bucket = $request_args['gradeable_buckets'];

    $g_constructor_params = array(
        'gradeable_id' => $g_id,
        'gradeable_title' => $g_title,
        'instructions_url' => $g_instructions_url,
        'ta_instructions' => $g_overall_ta_instr,
        'team_assignment' => $g_team_assignment,
        'min_grading_group' => $g_min_grading_group,
        'section_type' => $g_grade_by_registration,
        'date_grade' => $g_grade_start_date,
        'date_released' => $g_grade_released_date,
        'bucket' => $g_syllabus_bucket,
        'date_ta_view' => $g_ta_view_start_date
    );

    if ($request_args['gradeable_type'] === "Electronic File") {
        $g_constructor_params['gradeable_type'] = GradeableType::electronic_file;

        $ta_grading = ($request_args['ta_grading'] == "true") ? "true" : "false";
        $date_submit = $request_args['date_submit'];
        $date_due = $request_args['date_due'];
        if (strtotime($date_submit) < strtotime($g_ta_view_start_date)) {
            throw new Exception('DATE CONSISTENCY:  Submission Open Date must be >= TA Beta Testing Date');
        }
        if (strtotime($date_due) < strtotime($date_submit)) {
            throw new Exception('DATE CONSISTENCY:  Due Date must be >= Submission Open Date');
        }
        if ($ta_grading === "true") {
            if (strtotime($g_grade_start_date) < strtotime($date_due)) {
                throw new Exception('DATE CONSISTENCY:  TA Grading Open Date must be >= Due Date');
            }
            if(strtotime($g_grade_released_date) < strtotime($g_grade_start_date)) {
                throw new Exception('DATE CONSISTENCY:  Grades Released Date must be >= TA Grading Open Date');
            }
        }
        else {
            if(strtotime($g_grade_released_date) < strtotime($date_due)) {
                throw new Exception('DATE CONSISTENCY:  Grades Released Date must be >= Due Date');
            }
            $g_constructor_params['date_grade'] = $g_grade_released_date;
        }

        //$is_repo = ($request_args['upload_type'] == 'Repository')? "true" : "false";
        $is_repo = "false";
        $subdirectory = (isset($request_args['subdirectory']) && $is_repo == "true")? $request_args['subdirectory'] : '';

        $config_path = $request_args['config_path'];
        $eg_late_days = intval($request_args['eg_late_days']);
        $eg_pt_precision = floatval($request_args['point_precision']);

        $g_constructor_params['date_submit'] = $date_submit;
        $g_constructor_params['date_due'] = $date_due;
        $g_constructor_params['is_repo'] = $is_repo;
        $g_constructor_params['subdirectory'] = $subdirectory;
        $g_constructor_params['ta_grading'] = $ta_grading;
        $g_constructor_params['config_path'] = $config_path;
        $g_constructor_params['late_days'] = $eg_late_days;
        $g_constructor_params['point_precision'] = $eg_pt_precision;
        
        $gradeable = new ElectronicGradeable($g_constructor_params);
    }
    else if ($request_args['gradeable_type'] === "Checkpoints"){
        $g_constructor_params['gradeable_type'] = GradeableType::checkpoints;
        $gradeable = new CheckpointGradeable($g_constructor_params);
        if (strtotime($g_grade_start_date) < strtotime($g_ta_view_start_date)) {
          throw new Exception('DATE CONSISTENCY:  TA Grading Open Date must be >= TA Beta Testing Date');
        }
        if(strtotime($g_grade_released_date) < strtotime($g_grade_start_date)){
          throw new Exception('DATE CONSISTENCY:  Grade Released Date must be >= TA Grading Open Date');
        }
    }
    else if ($request_args['gradeable_type'] === "Numeric"){
        $g_constructor_params['gradeable_type'] = GradeableType::numeric;
        $gradeable = new NumericGradeable($g_constructor_params);
        if (strtotime($g_grade_start_date) < strtotime($g_ta_view_start_date)) {
          throw new Exception('DATE CONSISTENCY:  TA Grading Open Date must be >= TA Beta Testing Date');
        }
        if(strtotime($g_grade_released_date) < strtotime($g_grade_start_date)){
          throw new Exception('DATE CONSISTENCY:  Grade Released Date must be >= TA Grading Open Date');
        }
    }
    return $gradeable;
 }
  
function getGraders($var){
    $graders = array();
    foreach ($var as $k => $v ) {
        if (substr($k,0,7) === 'grader_' && !empty(trim($v))) {
            $graders[explode('_', $k)[1]]=explode(',',trim($v));
        }
    }
    return $graders;
}
 

$action = $_GET['action'];

// single update or create
if ($action != 'import'){
    try {
        $gradeable = constructGradeable($db, $_POST);
    }
    catch (Exception $e) {
        die($e->getMessage());
    }
    
    $db->beginTransaction();

    if ($action=='edit'){
        $gradeable->updateGradeable($db);
    }
    else{
        $db->query("SELECT COUNT(*) AS cnt FROM gradeable WHERE g_id=?", array($_POST['gradeable_id']));
        if ($db->row()['cnt'] == 1){
            die("gradeable with g_id ". $_POST['gradeable_id'] . " already exists");
        }
        $gradeable->createGradeable($db);
    }
    $gradeable->createComponents($db, $action, $_POST);
    
    $graders = getGraders($_POST);
    $gradeable->setupRotatingSections($db, $graders);
    
    $db->commit();
    writeFormJSON($_POST['gradeable_id'],$_POST['gradeableJSON']);


}
// batch update or create
else{
    // open each of the form files and import them
    //open each of the json configs
    $files = glob(__SUBMISSION_SERVER__ . '/config/form/form_*.json');
    $num_files = count($files);
    $success_gids = array();
    $failed_files = array();

    foreach($files as $file){
        try{
            $db->beginTransaction();
            $fp = fopen($file, 'r');
            if (!$fp){
                array_push($failed_files, $file);
                continue;
            }
            $form_json = fread($fp,filesize($file));
            $request_args = json_decode($form_json, true);
            $gradeable = constructGradeable($db, $request_args);
            $gradeable->createGradeable($db);
            
            foreach($request_args AS $k=>$v){
                if($k == 'checkpoints'){
                    for($i=1; $i<=count($v);++$i){
                       $request_args['checkpoint_label_' + $i] = $v[$i-1]['label']; 
                       $request_args['checkpoint_extra_' + $i] = $v[$i-1]['extra_credit'];
                    }
                }
                else if ($k == 'text_questions'){
                    $request_args['num_text_items'] = count($v);
                    for($i=1; $i<=count($v);++$i){
                        $request_args['text_label_'+$i] = $v[$i-1]['label'];
                    }
                }
                else if ($k == 'numeric_questions'){
                    $request_args['num_numeric_items'] = count($v);
                    for($i=1; $i<=count($v);++$i){
                        $request_args['numeric_label_' + $i] = $v[$i-1]['label'];
                        $request_args['max_score_' + $i] = $v[$i-1]['max_score'];
                        $request_args['numeric_extra_' + $i] = $v[$i-1]['extra_credit'];
                    }
                }
                else if (is_array($v)){
                    if (strpos($k, '_extra') !== false){
                        for($i=1; $i<= count($v); ++$i){
                           $request_args[$k.'_'.intval($v[$i-1])] = "";
                        }
                    }
                    else if (strpos($k, 'grader') !== false){
                        foreach($v as $k2 => $v2){
                            foreach($v2 as $k3 => $v3){
                                $request_args['grader_'.$k3] =$v3;
                            }
                        }
                    }
                    else{
                        for($i=1; $i<= count($v); ++$i){
                           $request_args[$k.'_'.intval($i)] = $v[$i-1];
                        }
                    }
                }
            }
            
            $gradeable->createComponents($db, $action, $request_args);
            $graders = getGraders($request_args);
            $gradeable->setupRotatingSections($db, $graders);
            $db->commit();
            
            array_push($success_gids, $gradeable->get_GID());
        } catch (Exception $e){
            array_push($failed_files, $file);
        } finally{
            fclose($fp);
        }
    }

    print count($success_gids).' of '.$num_files." successfully imported.\n";
    if(count($failed_files) > 0){
        print "Files failed:\n";
        foreach($failed_files as $failed_file){
            print "\t".$failed_file."\n";
        }
    }

}


// -------------------------------------------------------
// Create the a file to launch a rebuild of this course/gradeable...

if ($gradeable->getType() == 0) {  // is an electronic gradeable

  // FIXME:  should use a variable intead of hardcoded top level path
  $config_build_file = "/var/local/submitty/to_be_built/".__COURSE_SEMESTER__."__".__COURSE_CODE__."__".$_POST['gradeable_id'].".json";

  $config_build_data = array("semester" => __COURSE_SEMESTER__,
                             "course" => __COURSE_CODE__,
                             "gradeable" =>  $_POST['gradeable_id']);

  if (file_put_contents($config_build_file, json_encode($config_build_data, JSON_PRETTY_PRINT)) === false) {
    die("Failed to write file {$config_build_file}");
  }

}


// -------------------------------------------------------



if($action != 'import'){
  header('Location: '.__SUBMISSION_URL__.'/index.php?semester='.__COURSE_SEMESTER__.'&course='.__COURSE_CODE__);
}

?>
