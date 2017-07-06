<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\models\User;

class SimpleGraderController extends AbstractController  {
    public function run() {
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
            default:
                break;
        }
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
            $this->core->getOutput()->renderOutput(array('grading', 'SimpleGrader'), 'simpleDisplay', $gradeable, $sections, $graders);
            return;
        }
        if ((isset($_GET['view']) && $_GET['view'] === "all") || (count($sections) === 0 && $this->core->getUser()->accessAdmin())) {
            $students = $this->core->getQueries()->getAllUsers($section_key);
        }
        $student_ids = array_map(function(User $user) { return $user->getId(); }, $students);
        $rows = $this->core->getQueries()->getGradeables($gradeable->getId(), $student_ids, $section_key, $sort_key);
        $this->core->getOutput()->renderOutput(array('grading', 'SimpleGrader'), 'simpleDisplay', $gradeable, $rows, $graders);
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
                    if($component->getMaxValue() < $_POST['scores'][$component->getId()]){
                        $response = array('status' => 'fail', 'message' => "Save error: score is greater than the max score");
                        $this->core->getOutput()->renderJson($response);
                        return $response;
                    }
                    $component->setScore($_POST['scores'][$component->getId()]);
                    $component->setGrader($this->core->getUser());
                    $component->setGradeTime(new \DateTime('now', $this->core->getConfig()->getTimezone()));
                }
            }
        }

        $gradeable->saveData();

        $response = array('status' => 'success', 'data' => null);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    public function UploadCSV($action) {

        $users = $_POST['users'];
        $g_id = $_POST['g_id'];
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
                                if($component->getMaxValue() < $data_array[$j][$index2]){
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
                    $gradeable->setGrader($this->core->getUser());
                    $gradeable->setStatus(1);
                    $gradeable->setActiveVersion(1);
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
