<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\models\User;
use app\controllers\GradingController;

class SimpleGraderController extends GradingController  {
    public function run() {
        if(!$this->core->getUser()->accessGrading()) {
            $this->core->getOutput()->showError("This account doesn't have access to grading");
        }
        switch ($_REQUEST['action']) {
            case 'lab':
                $this->grade('lab');
                break;
            case 'save_lab':
                $this->save('lab');
                break;
            case 'numeric':
                $this->grade('numeric');
                break;
            case 'save_numeric':
                $this->save('numeric');
                break;
            case 'upload_csv_numeric':
                $this->UploadCSV('numeric');
                break;
            case 'print_lab':
                $this->printLab();
                break;
            default:
                break;
        }
    }

    public function printLab(){
        $g_id = $section = $sort_by = $sectionType = "";

        //Get the id for the current gradeable. Later used to get gradeable object from db.
        if (!isset($_REQUEST['g_id'])) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable');
        }
        else{
            $g_id = $_REQUEST['g_id'];
        }

        //Figure out what order we are supposed to be sorting the students in.
        if (isset($_REQUEST['sort'])) {
          $sort_by = $_REQUEST['sort'];
        }
        else{
            $sort_by = "registration_section";
        }

        //convert from id --> u.user_id etc for use by the database.
        if($sort_by === "id"){
            $sort_by = "u.user_id";
        }
        else if($sort_by === "first"){
            $sort_by = "u.user_firstname";
        }
        else if($sort_by === "last"){
            $sort_by = "u.user_lastname";
        }

        //Figure out what section we are supposed to print
        if (isset($_REQUEST['section'])) {
            $section = $_REQUEST['section'];
        }
        else{
            $this->core->addErrorMessage("ERROR: Section not set; You did not select a section to print.");
            return;    
        }

        //Figure out if we are getting users by rotating or registration section.
        if (!isset($_REQUEST['sectionType'])) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable');
        }
        else{
            $sectionType = $_REQUEST['sectionType'];
        }

        //Grab the students in section, sectiontype.
        if($sectionType === "rotating_section"){
            $students = $this->core->getQueries()->getUsersByRotatingSections(array($section), $sort_by);
        }
        else if($sectionType === "registration_section"){
            $students = $this->core->getQueries()->getUsersByRegistrationSections(array($section), $sort_by);
        }
        else{
            $this->core->addErrorMessage("ERROR: You did not select a valid section type to print.");
            return;
        }

        $gradeable = $this->core->getQueries()->getGradeable($g_id);
        
        //Turn off header/footer so that we are using simple html.
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        //display the lab to be printed (in SimpleGraderView's displayPrintLab function)
        $this->core->getOutput()->renderOutput(array('grading', 'SimpleGrader'), 'displayPrintLab', $gradeable, $sort_by, $section, $students);
    }

    public function grade($action) {
        if (!isset($_REQUEST['g_id'])) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable');
        }
        $g_id = $_REQUEST['g_id'];
        $gradeable = $this->core->getQueries()->getGradeable($g_id);
        if ($gradeable === null) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable', $g_id);
        }
        $this->core->getOutput()->addBreadcrumb("Grading {$gradeable->getName()}");

        if ($this->core->getUser()->getGroup() > $gradeable->getMinimumGradingGroup()) {
            $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $students = array();
        if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if (!isset($_GET['view']) || $_GET['view'] !== "all") {
                $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(),
                $this->core->getUser()->getId());
            if (!isset($_GET['view']) || $_GET['view'] !== "all") {
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable->getId(), $sections);
        }
        if(!isset($_GET['sort']) || $_GET['sort'] === "id"){
            $sort_key = "u.user_id";
        }
        else if($_GET['sort'] === "first"){
            $sort_key = "u.user_firstname";
        }
        else{
            $sort_key = "u.user_lastname";
        }
        if(count($sections) === 0 && (!isset($_GET['view']) || $_GET['view'] !== "all") && !$this->core->getUser()->accessAdmin()){
            $this->core->getOutput()->renderOutput(array('grading', 'SimpleGrader'), 'simpleDisplay', $gradeable, $sections, $graders, $section_key);
            return;
        }
        if ((isset($_GET['view']) && $_GET['view'] === "all") || (count($sections) === 0 && $this->core->getUser()->accessAdmin())) {
            //Checks to see if the Grader has access to all users in the course,
            //Will only show the sections that they are graders for if not TA or Instructor
            if($this->core->getUser()->getGroup() < 3) {
                $students = $this->core->getQueries()->getAllUsers($section_key);
            } else {
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
        }
        $student_ids = array_map(function(User $user) { return $user->getId(); }, $students);
        $rows = $this->core->getQueries()->getGradeables($gradeable->getId(), $student_ids, $section_key, $sort_key);
        $this->core->getOutput()->renderOutput(array('grading', 'SimpleGrader'), 'simpleDisplay', $gradeable, $rows, $graders, $section_key);
    }

    public function save($action) {
        if (!isset($_REQUEST['g_id']) || !isset($_REQUEST['user_id'])) {
            $response = array('status' => 'fail', 'message' => 'Did not pass in g_id or user_id');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }
        $g_id = $_REQUEST['g_id'];
        $user_id = $_REQUEST['user_id'];
        $gradeable = $this->core->getQueries()->getGradeable($g_id, $user_id);

        if ($this->core->getUser()->getGroup() > $gradeable->getMinimumGradingGroup()) {
            $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $user = $this->core->getQueries()->getUserById($user_id);
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $this->core->getCsrfToken()) {
            $response = array('status' => 'fail', 'message' => 'Invalid CSRF token');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }
        else if ($gradeable === null) {
            $response = array('status' => 'fail', 'message' => 'Invalid gradeable ID');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }
        else if ($user === null) {
            $response = array('status' => 'fail', 'message' => 'Invalid user ID');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }
        else if (!isset($_POST['scores']) || empty($_POST['scores'])) {
            $response = array('status' => 'fail', 'message' => "Didn't submit any scores");
            $this->core->getOutput()->renderJson($response);
            return $response;
        }

        foreach ($gradeable->getComponents() as $component) {
            if (isset($_POST['scores'][$component->getId()])) {
                if ($component->getIsText()){
                    $component->setComment($_POST['scores'][$component->getId()]);
                }
                else {
                    if($component->getUpperClamp() < $_POST['scores'][$component->getId()] || !is_numeric($_POST['scores'][$component->getId()])){
                        $response = array('status' => 'fail', 'message' => "Save error: score must be a number less than the upper clamp");
                        $this->core->getOutput()->renderJson($response);
                        return $response;
                    }
                    $component->setScore($_POST['scores'][$component->getId()]);
                }
                $component->setGrader($this->core->getUser());
                $component->setGradeTime(new \DateTime('now', $this->core->getConfig()->getTimezone()));
            }
        }

        $gradeable->setOverallComment("");
        $gradeable->saveData();

        $response = array('status' => 'success', 'data' => null);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    public function UploadCSV($action) {

        $users = $_POST['users'];
        $g_id = $_POST['g_id'];
        $gradeable = $this->core->getQueries()->getGradeable($g_id, $username);
        if ($this->core->getUser()->getGroup() > $gradeable->getMinimumGradingGroup()) {
            $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $num_numeric = $_POST['num_numeric'];
        $num_text = $_POST['num_text'];
        $component_ids = $_POST['component_ids'];
        $csv_array = preg_split("/\r\n|\n|\r/", $_POST['big_file']);
        $arr_length = count($csv_array);
        $return_data = array();

        $data_array = array();
        for ($i = 0; $i < $arr_length; $i++) {
            $temp_array = explode(',', $csv_array[$i]);
            $data_array[] = $temp_array;
        }

        foreach($users as $username) {
            for ($j = 0; $j < $arr_length; $j++) {
                if($username === $data_array[$j][0]) {
                    $temp_array = array();
                    $temp_array['username'] = $username;
                    $index1 = 0;
                    $index2 = 3; //3 is the starting index of the grades in the csv
                    $value_str = "value_";
                    $status_str = "status_";
                    $gradeable = $this->core->getQueries()->getGradeable($g_id, $username);
                    //Makes an array with all the values and their status.
                    foreach ($gradeable->getComponents() as $component) {
                        $value_temp_str = $value_str . $index1;
                        $status_temp_str = $status_str . $index1;
                        if (isset($data_array[$j][$index2])) {
                            if ($component->getIsText()){
                                $component->setComment($data_array[$j][$index2]);
                                $component->setGrader($this->core->getUser());
                                $component->setGradeTime(new \DateTime('now', $this->core->getConfig()->getTimezone()));
                                $temp_array[$value_temp_str] = $data_array[$j][$index2];
                                $temp_array[$status_temp_str] = "OK";
                            }
                            else{
                                if($component->getUpperClamp() < $data_array[$j][$index2]){
                                    $temp_array[$value_temp_str] = $data_array[$j][$index2];
                                    $temp_array[$status_temp_str] = "ERROR";
                                } else {
                                    $component->setScore($data_array[$j][$index2]);
                                    $component->setGrader($this->core->getUser());
                                	$component->setGradeTime(new \DateTime('now', $this->core->getConfig()->getTimezone()));
                                    $temp_array[$value_temp_str] = $data_array[$j][$index2];
                                    $temp_array[$status_temp_str] = "OK";
                                }

                            }
                        }
                        $index1++;
                        $index2++;
                        //skips the index of the total points in the csv file
                        if($index1 == $num_numeric) {
                            $index2++;
                        }
                    }

                    $user = $this->core->getQueries()->getUserById($username);
                    $gradeable->setUser($user);
                    $gradeable->setOverallComment("");
                    $gradeable->saveData();
                    $return_data[] = $temp_array;
                    $j = $arr_length; //stops the for loop early to not waste resources
                }
            }
        }

        $response = array('status' => 'success', 'data' => $return_data);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    
}
