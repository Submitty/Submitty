<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\DateUtils;

class LateController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'view_late':
                $this->viewLateDays();
                break;
            case 'view_extension':
                $this->viewExtensions();
                break;
            case 'update_late':
                $this->updateLateDays();
                break;
            case 'delete_late':
                $this->deleteLateDays();
                break;
            case 'update_extension':
                $this->updateExtension();
                break;
            case 'get_extension_details':
                $this->getExtensions($_REQUEST['g_id']);
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller");
                break;
        }
    }

    public function viewLateDays() {
        $user_table = $this->core->getQueries()->getUsersWithLateDays();
        $this->core->getOutput()->renderOutput(array('admin', 'LateDay'), 'displayLateDays', $user_table);
    }

    public function viewExtensions() {
        $e_gradeables = $this->core->getQueries()->getAllElectronicGradeablesIds();
        $this->core->getOutput()->renderOutput(array('admin', 'Extensions'), 'displayExtensions', $e_gradeables);
    }

    public function updateLateDays() {
        $data = array();
        if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {
            if (!($this->parseAndValidateCsv($_FILES['csv_upload']['tmp_name'], $data, "late"))) {
                $error = "Something is wrong with the CSV you have chosen. Try again.";
                return $this->core->getOutput()->renderJsonFail($error);
            }
            else {
                $csv_option = isset($_REQUEST['csv_option']) ? $_REQUEST['csv_option'] : null;
                for ($i = 0; $i < count($data); $i++){
                    $this->core->getQueries()->updateLateDays($data[$i][0], $data[$i][1], $data[$i][2], $csv_option);
                }
                $this->getLateDays();
            }
        }
        else { // not CSV, it's an individual
            $user = $this->core->getQueries()->getSubmittyUser($_POST['user_id']);
            $isUserNotInCourse = empty($this->core->getQueries()->getUsersById(array($_POST['user_id'])));
            if (!isset($_POST['user_id']) || $_POST['user_id'] == "" || $isUserNotInCourse || $user->getId() !== $_POST['user_id']) {
                $error = "Invalid Student ID";
                return $this->core->getOutput()->renderJsonFail($error);
            }
            if (!isset($_POST['datestamp']) || !DateUtils::validateTimestamp($_POST['datestamp'])) {
                $error = "Datestamp must be mm/dd/yy";
                return $this->core->getOutput()->renderJsonFail($error);
            }
            if (((!isset($_POST['late_days'])) || $_POST['late_days'] == "" || (!ctype_digit($_POST['late_days'])))) {
                $error = "Late Days must be a nonnegative integer";
                return $this->core->getOutput()->renderJsonFail($error);
            }
            $this->core->getQueries()->updateLateDays($_POST['user_id'], $_POST['datestamp'], $_POST['late_days']);
            return $this->getLateDays();
        }
    }

    public function deleteLateDays() {
        $user = $this->core->getQueries()->getSubmittyUser($_POST['user_id']);
        $isUserNotInCourse = empty($this->core->getQueries()->getUsersById(array($_POST['user_id'])));
        if (!isset($_POST['user_id']) || $_POST['user_id'] == "" || $isUserNotInCourse || $user->getId() !== $_POST['user_id']) {
            $error = "Invalid Student ID";
            return $this->core->getOutput()->renderJsonFail($error);
        }
        if ((!isset($_POST['datestamp']) || !DateUtils::validateTimestamp($_POST['datestamp']))) {
            $error = "Datestamp must be mm/dd/yy";
            return $this->core->getOutput()->renderJsonFail($error);
        }
        $this->core->getQueries()->deleteLateDays($_POST['user_id'], $_POST['datestamp']);

        return $this->getLateDays();
    }

    public function updateExtension() {
        //Check to see if a CSV file was submitted.
        $data = array();
        if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {
            //Validate
            if (!($this->parseAndValidateCsv($_FILES['csv_upload']['tmp_name'], $data, "extension"))) {
                $error = "Something is wrong with the CSV you have chosen. Try again.";
                return $this->core->getOutput()->renderJsonFail($error);
            }
            else {
                for ($i = 0; $i < count($data); $i++){
                    $this->core->getQueries()->updateExtensions($data[$i][0], $data[$i][1], $data[$i][2]);
                }

                $this->getExtensions($data[0][1]);
            }
        }
        else { // not CSV, it's an individual
            if ((!isset($_POST['g_id']) || $_POST['g_id'] == "" )) {
                $error = "Please choose a gradeable_id";
                return $this->core->getOutput()->renderJsonFail($error);
            }
            $user = $this->core->getQueries()->getSubmittyUser($_POST['user_id']);
            $isUserNotInCourse = empty($this->core->getQueries()->getUsersById(array($_POST['user_id'])));
            if (!isset($_POST['user_id']) || $_POST['user_id'] == "" || $isUserNotInCourse || $user->getId() !== $_POST['user_id']) {
                $error = "Invalid Student ID";
                return $this->core->getOutput()->renderJsonFail($error);
            }
            if ((!isset($_POST['late_days'])) || $_POST['late_days'] == "" || (!ctype_digit($_POST['late_days']))) {
                $error = "Late Days must be a nonnegative integer";
                return $this->core->getOutput()->renderJsonFail($error);
            }
            $team = $this->core->getQueries()->getTeamByGradeableAndUser($_POST['g_id'], $_POST['user_id']);
            //0 is for single submission, 1 is for team submission
            $option = isset($_POST['option']) ? $_POST['option'] : -1;
            if($team != NULL && $team->getSize() > 1){
                if($option == 0){
                    $this->core->getQueries()->updateExtensions($_POST['user_id'], $_POST['g_id'], $_POST['late_days']);
                    $this->getExtensions($_POST['g_id']);
                } else if($option == 1){
                    $team_member_ids = explode(", ", $team->getMemberList());
                    for($i = 0; $i < count($team_member_ids); $i++){
                        $this->core->getQueries()->updateExtensions($team_member_ids[$i], $_POST['g_id'], $_POST['late_days']);
                    }
                    $this->getExtensions($_POST['g_id']);
                } else {
                    $this->core->getOutput()->useHeader(false);
                    $this->core->getOutput()->useFooter(false);
                    $team_member_ids = explode(", ", $team->getMemberList());
                    $team_members = array();
                    for($i = 0; $i < count($team_member_ids); $i++){
                        $team_members[$team_member_ids[$i]] = $this->core->getQueries()->getUserById($team_member_ids[$i])->getDisplayedFirstName() . " " .
                            $this->core->getQueries()->getUserById($team_member_ids[$i])->getDisplayedLastName();
                    }
                    $return = $this->core->getOutput()->renderTwigTemplate("admin/users/MoreExtensions.twig",
                        ['g_id' => $_POST['g_id'],
                            'member_list' => $team_members]);
                    echo json_encode(array('is_team' => true, 'popup' => $return));
                }
            } else {
                $this->core->getQueries()->updateExtensions($_POST['user_id'], $_POST['g_id'], $_POST['late_days']);
                $this->getExtensions($_POST['g_id']);
            }
        }
    }

    function getLateDays(){
        $users = $this->core->getQueries()->getUsersWithLateDays();
        $user_table = array();
        foreach($users as $user){
            $user_table[] = array('user_id' => $user->getId(),'user_firstname' => $user->getDisplayedFirstName(), 'user_lastname' => $user->getDisplayedLastName(), 'late_days' => $user->getAllowedLateDays(), 'datestamp' => $user->getSinceTimestamp(), 'late_day_exceptions' => $user->getLateDayExceptions());
        }
        return $this->core->getOutput()->renderJsonSuccess(array(
            'users' => $user_table
        ));
    }

    public function getExtensions($g_id) {
        $users = $this->core->getQueries()->getUsersWithExtensions($g_id);
        $user_table = array();
        foreach($users as $user) {
            $user_table[] = array('user_id' => $user->getId(),'user_firstname' => $user->getDisplayedFirstName(), 'user_lastname' => $user->getDisplayedLastName(), 'late_day_exceptions' => $user->getLateDayExceptions());
        }
        return $this->core->getOutput()->renderJsonSuccess(array(
            'gradeable_id' => $g_id,
            'users' => $user_table
        ));
    }

    private function parseAndValidateCsv($csv_file, &$data, $type) {
    //IN:  * csv file name and path
    //     * (by reference) empty data array that will be filled.
    //OUT: TRUE should csv file be properly validated and data array filled.
    //     FALSE otherwise.
    //PURPOSE:  (1) validate uploaded csv file so it may be parsed.
    //          (2) create data array of csv information.

        //Validate file MIME type (needs to be "text/plain")
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['csv_upload']['tmp_name']);
        finfo_close($file_info);
        //MIME type must be text, but all subtypes are acceptable.
        if (substr($mime_type, 0, 5) !== "text/") {
            $data = null;
            return false;
        }
        $rows = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($rows === false) {
            $data = null;
            return false;
        }
        foreach($rows as $row) {
            $fields = explode(',', $row);
            //Remove any extraneous whitespace at beginning/end of all fields.
            $fields = array_map(function($k) { return trim($k); }, $fields);
            //Each row has three fields
            if (count($fields) !== 3) {
                $data = null;
                return false;
            }
            //$fields[0]: Verify student exists in class (check by student user ID)
            if ($this->core->getQueries()->getUserById($fields[0]) === null) {
                $data = null;
                return false;
            }
            //$fields[1] represents timestamp in the format (MM/DD/YY) for late days
            //(MM/DD/YYYY), (MM-DD-YY), or (MM-DD-YYYY).
            if ($type == "late" && !DateUtils::validateTimestamp($fields[1])) {
                $data = null;
                return false;
            }
            //$fields[1] represents the gradeable id for extensions
            if ($type == "extension" && !$this->validateHomework($fields[1])) {
                $data = null;
                return false;
            }
            //$fields[2]: Number of late days must be an integer >= 0
            if (!ctype_digit($fields[2])) {
                $data = null;
                return false;
            }
            //Fields information seems okay.  Push fields onto data array.
            $data[] = $fields;
        }
        //Validation successful.
        return true;
    }

    private function validateHomework($id) {
        $g_ids = $this->core->getQueries()->getAllElectronicGradeablesIds();
        foreach($g_ids as $index => $value) {
            if ($id === $value['g_id']) {
                return true;
            }
        }
        return false;
    }
}
