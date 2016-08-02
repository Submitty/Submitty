<?php

// TODO MORE error checking
// TODO Make sure transactions are in the right spots
// TODO functionalize more

include "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

 class Gradeable{
     protected $g_id;
     protected $g_title;
     protected $g_overall_ta_instr;
     protected $g_use_teams;
     protected $g_gradeable_type;
     protected $g_min_grading_group;
     protected $g_grade_by_registration;
     protected $g_grade_start_date;
     protected $g_grade_released_date;
     protected $g_syllabus_bucket;
     
    function __construct($params){
         $this->g_id = $params['gradeable_id'];
         $this->g_title = $params['gradeable_title'];
         $this->g_overall_ta_instr = $params['ta_instructions'];
         $this->g_use_teams = $params['team_assignment'];
         $this->g_gradeable_type = $params['gradeable_type'];
         $this->g_min_grading_group= $params['min_grading_group'];
         $this->g_grade_by_registration = $params['section_type'];
         $this->g_grade_start_date = $params['date_grade'];
         $this->g_grade_released_date = $params['date_released'];
         $this->g_syllabus_bucket = $params['bucket'];
    }
    
     /**
      * @param \lib\Database $db
      */
    function updateGradeable($db){
        $params = array($this->g_title, $this->g_overall_ta_instr, $this->g_use_teams, $this->g_gradeable_type,
                        $this->g_grade_by_registration, $this->g_grade_start_date, $this->g_grade_released_date,
                        $this->g_syllabus_bucket, $this->g_min_grading_group, $this->g_id);
        $db->query("UPDATE gradeable SET g_title=?, g_overall_ta_instructions=?, g_team_assignment=?, g_gradeable_type=?, 
                    g_grade_by_registration=?, g_grade_start_date=?, g_grade_released_date=?, g_syllabus_bucket=?, 
                    g_min_grading_group=? WHERE g_id=?", $params);
    }
    
     /**
      * @param \lib\Database $db
      */
    function createGradeable($db){
        $params = array($this->g_id,$this->g_title, $this->g_overall_ta_instr, $this->g_use_teams,
                        $this->g_gradeable_type, $this->g_grade_by_registration, $this->g_grade_start_date,
                        $this->g_grade_released_date, $this->g_syllabus_bucket, $this->g_min_grading_group);
        $db->query("INSERT INTO gradeable(g_id,g_title, g_overall_ta_instructions, g_team_assignment, 
                    g_gradeable_type, g_grade_by_registration, g_grade_start_date, g_grade_released_date,
                    g_syllabus_bucket,g_min_grading_group) VALUES (?,?,?,?,?,?,?,?,?,?)", $params);
    }
    
     /**
      * @param \lib\Database $db
      * @param $lb
      * @param $ub
      */
     function deleteComponents($db,$lb,$ub){
         // TODO REWRITE THIS, multiple queries here are bad
        for($i=$lb; $i<=$ub; ++$i){
            //DELETE all grades associated with these gcs
            $params = array($this->g_id,$i);
            $db->query("SELECT gc_id FROM gradeable_component WHERE g_id=? AND gc_order=?",$params);
            $row = $db->row();
            if (!isset($row['gc_id'])) {
                continue;
            }
            $db->query("DELETE FROM gradeable_component_data AS gcd WHERE gc_id=?",array($gc_id));
            $db->query("DELETE FROM gradeable_component WHERE gc_id=?", array($gc_id));
        }
    }
    
    //Overridden function, polymorphism
    function createComponents($db, $action, $add_args){}
    
    function get_GID(){
        return $this->g_id;
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
                //print "ENTRY CREATED";
                $db->query("INSERT INTO grading_rotating(g_id, user_id, sections_rotating) VALUES(?,?,?)", array($this->g_id,$grader,$section));
            }
        }
    }
 }
 
 class ElectronicGradeable extends Gradeable{
    private $instructions_url;
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
         $this->instructions_url = $params['instructions_url'];
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
            $params = array($this->instructions_url, $this->date_submit, $this->date_due, $this->is_repo,
                            $this->subdirectory, $this->ta_grading, $this->config_path, $this->late_days, $this->point_precision, $this->g_id);
            $db->query("UPDATE electronic_gradeable SET eg_instructions_url=?, eg_submission_open_date=?,eg_submission_due_date=?, 
                        eg_is_repository=?, eg_subdirectory=?, eg_use_ta_grading=?, eg_config_path=?, eg_late_days=?, eg_precision=? WHERE g_id=?", $params);
        }
        else{
            $params = array($this->g_id, $this->instructions_url, $this->date_submit, $this->date_due,
                            $this->is_repo, $this->subdirectory, $this->ta_grading, $this->config_path, $this->late_days, $this->point_precision);
            $db->query("INSERT INTO electronic_gradeable(g_id, eg_instructions_url, eg_submission_open_date, eg_submission_due_date, 
                eg_is_repository, eg_subdirectory, eg_use_ta_grading, eg_config_path, eg_late_days, eg_precision) VALUES(?,?,?,?,?,?,?,?,?,?)", $params);
        }

        $num_questions = 0;
        foreach($add_args as $k=>$v){
            if(strpos($k,'comment') !== false){
                ++$num_questions;
            }
        }
        $db->query("SELECT COUNT(*) as cnt FROM gradeable_component WHERE g_id=?", array($this->g_id));
        $num_old_questions = intval($db->row()['cnt']);
        //insert the questions
        for ($i=0; $i<$num_questions; ++$i){
            $gc_title = $add_args["comment-".strval($i)];
            $gc_ta_comment = $add_args["ta-".strval($i)];
            $gc_student_comment = $add_args["student-".strval($i)];
            $gc_max_value = $add_args['point-'.strval($i)];
            $gc_is_text = "false";
            $gc_is_ec = (isset($add_args['ec-'.strval($i)]) && $add_args['ec-'.strval($i)]=='on')? "true" : "false";
            if($action=='edit' && $i<$num_old_questions){
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
        $this->deleteComponents($db, $num_questions,$num_old_questions);
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
            if(strpos($k, 'checkpoint-label') !== false){
                ++$num_checkpoints;
            }
        }
        $db->query("SELECT COUNT(*) as cnt FROM gradeable_component WHERE g_id=?", array($this->g_id));
        $num_old_checkpoints = intval($db->row()['cnt']);
        
        // insert the checkpoints
        for($i=1; $i<=$num_checkpoints; ++$i){
            $gc_is_extra_credit = (isset($add_args["checkpoint-extra-".strval($i)])) ? "true" : "false";
            $gc_title = $add_args['checkpoint-label-'. strval($i)];
            
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
        
        $num_numeric = intval($add_args['num-numeric-items']);
        $num_text= intval($add_args['num-text-items']);
        
        for($i=1; $i<=$num_numeric+$num_text; ++$i){
            //CREATE the numeric items in gradeable component
            $gc_is_text = ($i > $num_numeric)? "true" : "false";
            if($i > $num_numeric){
                $gc_title = (isset($add_args['text-label-'. strval($i-$num_numeric)]))? $add_args['text-label-'. strval($i-$num_numeric)] : '';
                $gc_max_value = 0;
                $gc_is_extra_credit ="false";
            }
            else{
                $gc_title = (isset($add_args['numeric-label-'. strval($i)]))? $add_args['numeric-label-'. strval($i)] : '';
                $gc_max_value = (isset($add_args['max-score-'. strval($i)]))? $add_args['max-score-'. strval($i)] : 0;
                $gc_is_extra_credit = (isset($add_args['numeric-extra-'.strval($i)]))? "true" : "false";
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
 
// TODO MAKE SURE THE TRANSACTIONS are in the right spot!
 
abstract class GradeableType{
    const electronic_file = 0;
    const checkpoints = 1;
    const numeric = 2;
}
 
 function constructGradeable ($request_args){
     $g_id = $request_args['gradeable_id'];
     $g_title = $request_args['gradeable_title'];
     $g_overall_ta_instr = $request_args['ta_instructions'];
     $g_use_teams = ($request_args['team-assignment'] === 'yes') ? "true" : "false";
     $g_min_grading_group=intval($request_args['minimum-grading-group']);
     $g_grade_by_registration = ($request_args['section-type'] === 'reg-section') ? "true" : "false";
     $g_grade_start_date = $request_args['date_grade'];
     $g_grade_released_date = $request_args['date_released'];
     $g_syllabus_bucket = $request_args['gradeable-buckets'];
     
     $g_constructor_params = array('gradeable_id' => $g_id, 'gradeable_title' => $g_title, 'ta_instructions' => $g_overall_ta_instr,
                                   'team_assignment' => $g_use_teams, 'min_grading_group' => $g_min_grading_group,
                                   'section_type' => $g_grade_by_registration, 'date_grade' => $g_grade_start_date,
                                   'date_released' =>$g_grade_released_date, 'bucket' => $g_syllabus_bucket);
     
     if ($request_args['gradeable-type'] === "Electronic File"){
        $g_constructor_params['gradeable_type'] = GradeableType::electronic_file;
        
        $instructions_url = $request_args['instructions-url'];
        $date_submit = $request_args['date_submit'];
        $date_due = $request_args['date_due'];
        $is_repo = ($request_args['upload-type'] == 'Repository')? "true" : "false";
        $subdirectory = (isset($request_args['subdirectory']) && $is_repo == "true")? $request_args['subdirectory'] : '';
        $ta_grading = ($request_args['ta-grading'] == 'yes')? "true" : "false";
        $config_path = $request_args['config-path'];
        $eg_late_days = intval($request_args['eg_late_days']);
        $eg_pt_precision = floatval($request_args['point-precision']);
        
        $g_constructor_params['instructions_url'] = $instructions_url;
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
     else if ($request_args['gradeable-type'] === "Checkpoints"){
        $g_constructor_params['gradeable_type'] = GradeableType::checkpoints;
        $gradeable = new CheckpointGradeable($g_constructor_params);
     }
     else if ($request_args['gradeable-type'] === "Numeric"){
        $g_constructor_params['gradeable_type'] = GradeableType::numeric;
        $gradeable = new NumericGradeable($g_constructor_params);
     }
     return $gradeable;
 }
  
function getGraders($var){
    $graders = array();
    foreach ($var as $k => $v ) {
        if (substr($k,0,7) === 'grader-' && !empty(trim($v))) {
            $graders[explode('-', $k)[1]]=explode(',',$v);
        }
    }
    return $graders;
}
 
 /* FIXME update the ASSIGNMENTS.txt file */
 // FIXME make member function of gradeable THIS IS BAD
 // FIXME NAIVE IMPLEMENTATION
function addAssignmentsTxt($db, $assignments){
    $fp = fopen(__SUBMISSION_SERVER__.'/ASSIGNMENTS.txt', 'a');
    if (!$fp){
        die('failed to open'. __SUBMISSION_SERVER__.'/ASSIGNMENTS.txt');
    }
    //THIS IS BAD
    foreach ($assignments as $assignment){
        $db->query("SELECT * FROM electronic_gradeable WHERE g_id=?", array($assignment));
        $eg = $db->row();
        if(!empty($eg)){
            fwrite($fp, "build_homework" . "  " . $eg['eg_config_path'] . "  ". __COURSE_SEMESTER__. " ".__COURSE_CODE__. " ". $assignment ."\n");
        }
    }
    fclose($fp);
}

$action = $_GET['action'];

// single update or create
if ($action != 'import'){
    $gradeable = constructGradeable($_POST);
    $db->beginTransaction();
    if ($action=='edit'){
        $gradeable->updateGradeable($db);
    }
    else{
        $gradeable->createGradeable($db);
    }
    $gradeable->createComponents($db, $action, $_POST);
    
    $graders = getGraders($_POST);
    $gradeable->setupRotatingSections($db, $graders);
    
    $db->commit();
    if($action != 'edit'){
        addAssignmentsTxt($db,array($gradeable->get_GID()));
    }
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
            $fp = fopen($file, 'r');
            if (!$fp){
                array_push($failed_files, $file);
                continue;
            }
            $form_json = fread($fp,filesize($file));
            $request_args = json_decode($form_json, true);
            $gradeable = constructGradeable($request_args);
            $gradeable->createGradeable($db);
            $gradeable->createComponents($db, $action, $request_args);
            // TODO pass in filtered array here
            $graders = getGraders($request_args);
            $gradeable->setupRotatingSections($db, $graders);
            
            array_push($success_gids, $gradeable->getGID());
        } catch (Exception $e){
          array_push($failed_files, $file);
        } finally{
            fclose($fp);
        }
    }
    addAssignmentsTxt($db,$success_gids);
    print count($success_gids).' of '.$num_files." successfully imported.\n";
    if(count($failed_files) > 0){
        print "Files failed:\n";
        foreach($failed_files as $failed_file){
            print "\t".$failed_file."\n";
        }
    }

}

if($action != 'import'){
    header('Location: '.__BASE_URL__.'/account/admin-gradeables.php?course='.$_GET['course']."&semester=".$_GET['semester']);
}

?>