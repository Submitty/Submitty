<?php
namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\Output;
use app\libraries\FileUtils;

class PlagiarismController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'compare':
                $this->plagiarismCompare();
                break;
            case 'index':
                $this->plagiarismIndex();
                break;
            case 'plagiarism_form':
                $this->plagiarismForm();
                break;    
            case 'run_plagiarism':
                $this->runPlagiarism();
                break; 
            case 'get_plagiarism_ranking_for_gradeable':
                $this->ajaxGetPlagiarismRankingForGradeable();
                break; 
            case 'get_user_submission':
            	$this->ajaxGetUserSubmission();
            	break;
            case 'get_matching_users':
            	$this->ajaxGetMatchingUsers();
            	break;      
            default:
                $this->core->getOutput()->addBreadcrumb('Plagiarism Detection');
                $this->plagiarismTree();
                break;
        }
    }

    public function plagiarismCompare() {
        $semester = $_REQUEST['semester'];
        $course = $_REQUEST['course'];
        $assignment = $_REQUEST['assignment'];
        $studenta = $_REQUEST['studenta'];
        $studentb = $_REQUEST['studentb'];
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismCompare', $semester, $course, $assignment, $studenta, $studentb);
    }

    public function plagiarismIndex() {
        $semester = $_REQUEST['semester'];
        $course = $_REQUEST['course'];
        $assignment = $_REQUEST['assignment'];
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismIndex', $semester, $course, $assignment);
    }

    public function plagiarismTree() {
        $semester = $_REQUEST['semester'];
        $course = $_REQUEST['course'];
        if (file_exists("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/")) {
            $assignments = array_diff(scandir("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/"), array('.', '..'));
        } else {
            $assignments = array();
        }
        $gradeable_ids_titles= $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        foreach($gradeable_ids_titles as $i => $gradeable_id_title) {
        	if(!file_exists("/var/local/submitty/courses/".$semester."/".$course."/lichen/ranking/".$gradeable_id_title['g_id'].".txt")) {
        		unset($gradeable_ids_titles[$i]);
        	}
        }
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismTree', $semester, $course, $assignments, $gradeable_ids_titles);  
    }

    public function plagiarismForm() {
        $semester = $_REQUEST['semester'];
        $course = $_REQUEST['course'];
        $gradeable_ids = array_diff(scandir("/var/local/submitty/courses/$semester/$course/submissions/"), array('.', '..'));
        $gradeable_ids_titles= $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        foreach($gradeable_ids_titles as $i => $gradeable_id_title) {
            if(!in_array($gradeable_id_title['g_id'], $gradeable_ids)) {
                unset($gradeable_ids_titles[$i]);
            }
        }
        $prior_term_gradeables = FileUtils::getGradeablesFromPriorTerm();

        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismForm', $gradeable_ids_titles, $prior_term_gradeables);
    }

    public function runPlagiarism() {

        $semester = $_REQUEST['semester'];
        $course = $_REQUEST['course'];
        $return_url = $this->core->buildUrl(array('component'=>'admin', 'page' => 'plagiarism', 'course' => $course, 'semester' => $semester, 'action' => 'plagiarism_form'));
        
        if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $this->core->addErrorMessage("Invalid CSRF token");
            $this->core->redirect($return_url);
        }

        $prev_gradeable_number = $_POST['prior_term_gradeables_number'];
        $ignore_submission_number = $_POST['ignore_submission_number'];
        $gradeable = $_POST['gradeable_id'];
        $version_option = $_POST['version_option'];
        if ($version_option == "active_version") {
            $version_option = "active_version";
        }
        else {
            $version_option = "all_version";
        }

        $file_option = $_POST['file_option'];
        if ($file_option == "regrex_matching_files") {
            $file_option = "matching_regrex";
        }
        else {
            $file_option = "all";
        }
        if($file_option == "matching_regrex") {
            if( isset($_POST['regrex_to_select_files']) && $_POST['regrex_to_select_files'] !== '') {
                $regrex_for_selecting_files = $_POST['regrex_to_select_files'];
            }
            else {
                $this->core->addErrorMessage("No regrex provided for selecting files");
                $this->core->redirect($return_url);
            }    
        }

        $language= $_POST['language'];
        if( isset($_POST['threshold']) && $_POST['threshold'] !== '') {
            $threshold = $_POST['threshold'];
        }
        else {
            $this->core->addErrorMessage("No input provided for threshold");
            $this->core->redirect($return_url);
        } 
        if( isset($_POST['sequence_length']) && $_POST['sequence_length'] !== '') {
            $sequence_length = $_POST['sequence_length'];
        }
        else {
            $this->core->addErrorMessage("No input provided for sequence length");
            $this->core->redirect($return_url);
        } 

        $prev_term_gradeables = array();
        for( $i = 0; $i < $prev_gradeable_number; $i++ ) {
            if($_POST['prev_sem_'.$i]!= "" && $_POST['prev_course_'.$i]!= "" && $_POST['prev_gradeable_'.$i]!= "") {
                array_push($prev_term_gradeables, "/var/local/submitty/course/".$_POST['prev_sem_'.$i]."/".$_POST['prev_course_'.$i]."/submissions/".$_POST['prev_gradeable_'.$i]);
            }
        }

        $ignore_submissions = array();
        $ignore_submission_option = $_POST['ignore_submission_option'];
        if ($ignore_submission_option == "ignore") {
            for( $i = 0; $i < $ignore_submission_number; $i++ ) {
                if(isset($_POST['ignore_submission_'.$i]) && $_POST['ignore_submission_'.$i] !== '') {
                    array_push($ignore_submissions, $_POST['ignore_submission_'.$i]);
                }
            }    
        }
        
        $gradeable_path =  FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable);
        $provided_code_option = $_POST['provided_code_option'];
        if($provided_code_option == "code_provided") {
            $instructor_provided_code= true;
        }
        else {
            $instructor_provided_code= false;
        }

        if($instructor_provided_code == true) {
            if (empty($_FILES) || !isset($_FILES['provided_code_file'])) {
                $this->core->addErrorMessage("Upload failed: Instructor code not provided");
                $this->core->redirect($return_url);
            }
            if (!isset($_FILES['provided_code_file']['tmp_name']) || $_FILES['provided_code_file']['tmp_name'] == "") {
                $this->core->addErrorMessage("Upload failed: Instructor code not provided");
                $this->core->redirect($return_url);
            }

            else {
                $upload = $_FILES['provided_code_file'];
                $target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen/provided_code", $gradeable);
                if (!is_dir($target_dir)) {
                    FileUtils::createDir($target_dir);    
                }
                $target_dir = FileUtils::joinPaths($target_dir, count(scandir($target_dir))-1);
                FileUtils::createDir($target_dir);
                $instructor_provided_code_path = $target_dir;

                if (FileUtils::getMimeType($upload["tmp_name"]) == "application/zip") {
                    $zip = new \ZipArchive();
                    $res = $zip->open($upload['tmp_name']);
                    if ($res === true) {
                        $zip->extractTo($target_dir);
                        $zip->close();
                    }
                    else {
                        FileUtils::recursiveRmdir($target_dir);
                        $error_message = ($res == 19) ? "Invalid or uninitialized Zip object" : $zip->getStatusString();
                        $this->core->addErrorMessage("Upload failed: {$error_message}");
                        $this->core->redirect($return_url);
                    }
                }
                else {
                    if (!@copy($upload['tmp_name'], FileUtils::joinPaths($target_dir, $upload['name']))) {
                        FileUtils::recursiveRmdir($target_dir);
                        $this->core->addErrorMessage("Upload failed: Could not copy file");
                        $this->core->redirect($return_url);
                    }
                }
            }
        }

        $config_dir = "/var/local/submitty/courses/".$semester."/".$course."/lichen/config/";
        $number_of_undone_jobs = count(array_diff(scandir($config_dir), array(".","..")));
        $json_file = "/var/local/submitty/courses/".$semester."/".$course."/lichen/config/".$gradeable."_".$number_of_undone_jobs.".json";
        $json_data = array("semester" =>    $semester,
                            "course" =>     $course,
                            "gradeable" =>  $gradeable,
                            "version" =>    $version_option,
                            "file_option" =>$file_option,
                            "language" =>   $language,
                            "threshold" =>  $threshold,
                            "sequence_length"=> $sequence_length,
                            "prev_term_gradeables" => $prev_term_gradeables,
                            "ignore_submissions" =>   $ignore_submissions,
                            "instructor_provided_code" =>   $instructor_provided_code,
                                        );

        if($file_option == "matching_regrex") {
            $json_data["regrex"] = $regrex_for_selecting_files;
        }
        if($instructor_provided_code == true) {
            $json_data["instructor_provided_code_path"] = $instructor_provided_code_path;   
        }

        if (file_put_contents($json_file, json_encode($json_data, JSON_PRETTY_PRINT)) === false) {
          die("Failed to write file {$json_file}");
        }

        $this->core->redirect($this->core->buildUrl(array('component'=>'admin', 'page' => 'plagiarism', 'course' => $course, 'semester' => $semester)));
    }

    public function ajaxGetPlagiarismRankingForGradeable(){
    	$gradeable_id = $_REQUEST['gradeable_id'];
        $course_path = $this->core->getConfig()->getCoursePath();
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        $return="";
        $file_path= $course_path."/lichen/ranking/".$gradeable_id.".txt";
    	if(($this->core->getUser()->accessAdmin()) && (file_exists($file_path))) {
        	$content =file_get_contents($file_path);
        	$content = trim(str_replace(array("\r", "\n"), '', $content));
        	$rankings = preg_split('/ +/', $content);
    		$rankings = array_chunk($rankings,3);
    		foreach($rankings as $i => $ranking) {
    			array_push($rankings[$i], $this->core->getQueries()->getUserById($ranking[1])->getDisplayedFirstName());  
    		}
    	    $return = json_encode($rankings);
        }
    	echo($return);
    }

    public function ajaxGetUserSubmission() {
    	$gradeable_id = $_REQUEST['gradeable_id'];
    	$user_id =$_REQUEST['user_id'];
    	$version = $_REQUEST['version'];
        $course_path = $this->core->getConfig()->getCoursePath();
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        $return="";
        $active_version = (string)$this->core->getQueries()->getGradeable($gradeable_id, $user_id)->getActiveVersion();
        $file_path= $course_path."/lichen/ranking/".$gradeable_id.".txt";
    	$content =file_get_contents($file_path);
    	$content = trim(str_replace(array("\r", "\n"), '', $content));
    	$rankings = preg_split('/ +/', $content);
		$rankings = array_chunk($rankings,3);
		foreach($rankings as $ranking) {
			if($ranking[1] == $user_id) {
				$max_matching_version = $ranking[2];
			}
		}
        if($version == "max_matching") {
        	$version = $max_matching_version;
        }
        $all_versions = array_diff(scandir($course_path."/submissions/".$gradeable_id."/".$user_id), array(".", "..", "user_assignment_settings.json"));

        $file_name= $course_path."/lichen/concatenated/".$gradeable_id."/".$user_id."/".$version."/submission.concatenated";
 
    	if(($this->core->getUser()->accessAdmin()) && (file_exists($file_name))) {
  			$data= array('file_content'=> htmlentities(file_get_contents($file_name)), 'code_version' => $version, 'max_matching_version' => $max_matching_version, 'active_version' => $active_version, 'all_versions' => $all_versions);
       	    $return = json_encode($data);
        }
    	echo($return);	
    }

    public function ajaxGetMatchingUsers() {
    	$gradeable_id = $_REQUEST['gradeable_id'];
    	$user_id =$_REQUEST['user_id'];
    	$version = $_REQUEST['version'];
        $course_path = $this->core->getConfig()->getCoursePath();
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        $return = array();
        $file_path= $course_path."/lichen/ranking/".$gradeable_id.".txt";
    	$content =file_get_contents($file_path);
    	$content = trim(str_replace(array("\r", "\n"), '', $content));
    	$rankings = preg_split('/ +/', $content);
		$rankings = array_chunk($rankings,3);
		foreach($rankings as $ranking) {
			if($ranking[1] == $user_id) {
				$max_matching_version = $ranking[2];
			}
		}
        if($version == "max_matching") {
        	$version = $max_matching_version;
        }
        $file_path= $course_path."/lichen/matches/".$gradeable_id."/".$user_id."/".$version."/matches.json";
        if (!file_exists($file_path)) {
        	echo("no_match_for_this_version");
        }
        else {
	        $content = json_decode(file_get_contents($file_path), true);
	    	foreach($content as $match) {
	    		if($match["type"] == "match") {
	    			foreach ($match["others"] as $match_info) {
	    				array_push($return, array($match_info["username"],$match_info["version"]));
	    			}
	    		}
	    	}
	    	foreach($return as $i => $match_user) {
    			array_push($return[$i], $this->core->getQueries()->getUserById($match_user[0])->getDisplayedFirstName());  
    		}
	    	$return = json_encode($return);
	        echo($return);
	    }    
    }

}
