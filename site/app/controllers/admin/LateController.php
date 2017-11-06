<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\DateUtils;

class LateController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'view_late':
                $this->core->getOutput()->addBreadcrumb('Late Days Allowed');
                $this->viewLateDays();
                break;
            case 'view_extension':
                $this->core->getOutput()->addBreadcrumb('Excused Absense Extensions');
                $this->viewExtensions();
                break;
            case 'update_late':
                $this->update("late");
                break;
            case 'update_extension':
                $this->update("extension");
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
        $g_ids = $this->core->getQueries()->getAllElectronicGradeablesIds();
        $this->core->getOutput()->renderOutput(array('admin', 'Extensions'), 'displayExtensions', $g_ids);
    }

    public function update($type) {
        //Check to see if a CSV file was submitted.
        $data = array();
        if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {
            if (!($this->parseAndValidateCsv($_FILES['csv_upload']['tmp_name'], $data, $type))) {
                $error = "Something is wrong with the CSV you have chosen. Try again.";
                $this->core->getOutput()->renderJson(array('error' => $error));
                return;
            }
            else {
                for ($i = 0; $i < count($data); $i++){
                    if ($type == "late"){
                        $this->core->getQueries()->updateLateDays($data[$i][0], $data[$i][1], $data[$i][2]);
                    }
                    else {
                        $this->core->getQueries()->updateExtensions($data[$i][0], $data[$i][1], $data[$i][2]);
                    }
                }
                if ($type == "late"){
                    $this->getLateDays();
                }
                else {
                    $this->getExtensions($data[0][1]);
                }
            }
        }
        else{ // not CSV, it's an individual
            if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
                $error = "Invalid CSRF token. Try again.";
                $this->core->getOutput()->renderJson(array('error' => $error));
                return;
            }
            if ((!isset($_POST['g_id']) || $_POST['g_id'] == "" ) && $type == 'extension') {
                $error = "Please choose a gradeable_id";
                $this->core->getOutput()->renderJson(array('error' => $error));
                return;
            }
            if (!isset($_POST['user_id']) || $_POST['user_id'] == "" || $this->core->getQueries()->getUserById($_POST['user_id'])->getId() !== $_POST['user_id']) {
                $error = "Invalid Student ID";
                $this->core->getOutput()->renderJson(array('error' => $error));
                return;
            }
            if ((!isset($_POST['datestamp']) || !DateUtils::validateTimestamp($_POST['datestamp'])) && $type == 'late') {
                $error = "Datestamp must be mm/dd/yy";
                $this->core->getOutput()->renderJson(array('error' => $error));
                return;
            }
            if ((!isset($_POST['late_days'])) || $_POST['late_days'] == "" || (!ctype_digit($_POST['late_days'])) ) {
                $error = "Late Days must be a nonnegative integer";
                $this->core->getOutput()->renderJson(array('error' => $error));
                return;
            }
            if($type == "late"){
                $this->core->getQueries()->updateLateDays($_POST['user_id'], $_POST['datestamp'], $_POST['late_days']);
                $this->getLateDays();
            }
            else{
                $this->core->getQueries()->updateExtensions($_POST['user_id'], $_POST['g_id'], $_POST['late_days']);
                $this->getExtensions($_POST['g_id']);
            }
        }

    }

    function getLateDays(){
        $users = $this->core->getQueries()->getUsersWithLateDays();
        $user_table = array();
        foreach($users as $user){
            $user_table[] = array('user_id' => $user->getId(),'user_firstname' => $user->getDisplayedFirstName(), 'user_lastname' => $user->getLastName(), 'late_days' => $user->getAllowedLateDays(), 'datestamp' => $user->getSinceTimestamp(), 'late_day_exceptions' => $user->getLateDayExceptions());
        }
        $this->core->getOutput()->renderJson(array(
            'users' => $user_table
        ));
    }

    public function getExtensions($g_id) {
        $users = $this->core->getQueries()->getUsersWithExtensions($g_id);
        $user_table = array();
        foreach($users as $user) {
            $user_table[] = array('user_id' => $user->getId(),'user_firstname' => $user->getDisplayedFirstName(), 'user_lastname' => $user->getLastName(), 'late_day_exceptions' => $user->getLateDayExceptions());
        }
        $this->core->getOutput()->renderJson(array(
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
