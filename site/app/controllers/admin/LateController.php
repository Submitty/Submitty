<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
// use app\libraries\FileUtils;

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
                $this->update("view_late");
                break;
            case 'update_extension':
                $this->update("view_extension");
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller");
                break;
        }
    }

    public function viewLateDays() {
        $user_table = $this->core->getQueries()->getUsersWithLateDays();
        $this->setPreferedName($user_table);
        $this->core->getOutput()->renderOutput(array('admin', 'LateDay'), 'displayLateDays', $user_table);
    }

    public function viewExtensions() {
        if (isset($_POST['selected_gradeable'])) {
            $g_id = $_POST['selected_gradeable'];
        } else {
            $g_id = $this->core->getQueries()->getNewestElectronicGradeableId();
            foreach($g_id as $index => $value) {
                $g_id=$value[0];
            }
        }
        $user_table = $this->core->getQueries()->getUsersWithExtensions($g_id);
        $this->setPreferedName($user_table);
        $g_ids = $this->core->getQueries()->getAllElectronicGradeablesIds();
        $this->core->getOutput()->renderOutput(array('admin', 'Extensions'), 'displayExtensions', $g_id, $g_ids, $user_table);
    }

    public function update($nextStep) {
        //Check to see if a CSV file was submitted.
        $data = array();
        if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {
            if (!($this->parse_and_validate_csv($_FILES['csv_upload']['tmp_name'], $data, $nextStep))) {
                $_SESSION['messages']['error'][] = "Something is wrong with the CSV. Try again.";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => $nextStep)));
            } else {
                for($i = 0; $i < count($data); $i++){
                    if($nextStep == "view_late"){
                        $this->core->getQueries()->updateLateDays($data[$i][0], $data[$i][1], $data[$i][2]);
                    }
                    else{
                        $this->core->getQueries()->updateExtensions($data[$i][0], $data[$i][1], $data[$i][2]);
                    }
                }
            }
        }
        else{
            if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
                $_SESSION['messages']['error'][] = "Invalid CSRF token. Try again.";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => $nextStep)));
            }
            else if (!isset($_POST['user_id']) || $_POST['user_id'] == "" || $this->core->getQueries()->getUserById($_POST['user_id'])->getId() !== $_POST['user_id']) {
                $_SESSION['messages']['error'][] = "Invalid Student ID";
                $_SESSION['request'] = $_POST;
                $this->reloadPage($nextStep);
            }
            else if ((!isset($_POST['datestamp']) || !$this->validate_timestamp($_POST['datestamp'])) && $nextStep == 'view_late') {
                $_SESSION['messages']['error'][] = "Datestamp must be mm/dd/yy";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => 'view_late')));
            }
            else if ((!isset($_POST['late_days'])) || $_POST == "" || (!ctype_digit($_POST['late_days'])) ) {
                $_SESSION['messages']['error'][] = "Late Days must be a nonnegative integer";
                $_SESSION['request'] = $_POST;
                $this->reloadPage($nextStep);
            }
            else{
                if($nextStep == "view_late"){
                    $this->core->getQueries()->updateLateDays($_POST['user_id'], $_POST['datestamp'], $_POST['late_days']);
                }
                else{
                    $this->core->getQueries()->updateExtensions($_POST['user_id'], $_POST['selected_gradeable'], $_POST['late_days']);
                }   
            }
        }
        $this->reloadPage($nextStep);
    }

    // for use during update
    function reloadPage($nextStep){
        if($nextStep == 'view_late'){
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => 'view_late')));
        }
        else{
            $user_table = $this->core->getQueries()->getUsersWithExtensions($_POST['selected_gradeable']);
            $this->setPreferedName($user_table);
            $g_ids = $this->core->getQueries()->getAllElectronicGradeablesIds();
            $this->core->getOutput()->renderOutput(array('admin', 'Extensions'), 'displayExtensions', $_POST['selected_gradeable'], $g_ids, $user_table);
        }
    }

    function setPreferedName(&$user_table){
        foreach ($user_table as $index => &$value) {
            if(isset($value['user_preferred_firstname']) && $value['user_preferred_firstname'] !== null && $value['user_preferred_firstname'] !== ""){
                $value['user_firstname']=$value['user_preferred_firstname'];
            }
        }
    }

    function parse_and_validate_csv($csv_file, &$data, $nextStep) {
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
            foreach($fields as &$field) {
                $field = trim($field);
            } unset($field);
            //Each row has three fields
            if (count($fields) !== 3) {
                $data = null;
                return false;
            }
            //$fields[0]: Verify student exists in class (check by student user ID)
            if(count($this->core->getQueries()->getUserById($fields[0])) !== 1){
                $data = null;
                return false;
            }
            //$fields[1] represents timestamp in the format (MM/DD/YY) for late days
            //(MM/DD/YYYY), (MM-DD-YY), or (MM-DD-YYYY).
            if ($nextStep == "view_late" && !$this->validate_timestamp($fields[1])) {
                $data = null;
                return false;
            }
            //$fields[1] represents the gradeable id for extensions
            if ($nextStep == "view_extension" && !$this->validate_homework($fields[1])) {
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

    function validate_timestamp($timestamp) {
    //IN:  $timestamp is actually a date string, not a Unix timestamp.
    //OUT: TRUE when date string conforms to an accetpable pattern
    //      FALSE otherwise.
    //PURPOSE: Validate string to (1) be a valid date and (2) conform to specific
    //         date patterns.
    //         'm-d-Y' -> mm-dd-yyyy
    //         'm-d-y' -> mm-dd-yy
    //         'm/d/Y' -> mm/dd/yyyy
    //         'm/d/y' -> mm/dd/yy

        //This bizzare/inverted switch-case block actually does work in PHP.
        //This operates as a form of "white list" of valid patterns.
        //This checks to ensure a date pattern is acceptable AND the date actually
        //exists.  e.g. "02-29-2016" is valid, while "06-31-2016" is not.
        //That is, 2016 is a leap year, but June has only 30 days.
        $tmp = array(date_create_from_format('m-d-Y', $timestamp),
                     date_create_from_format('m/d/Y', $timestamp),
                     date_create_from_format('m-d-y', $timestamp),
                     date_create_from_format('m/d/y', $timestamp));

        switch (true) {
        case ($tmp[0] && $tmp[0]->format('m-d-Y') === $timestamp):
        case ($tmp[1] && $tmp[1]->format('m/d/Y') === $timestamp):
        case ($tmp[2] && $tmp[2]->format('m-d-y') === $timestamp):
        case ($tmp[3] && $tmp[3]->format('m/d/y') === $timestamp):
            return true;
        default:
            return false;
        }
        return true;
    }

    function validate_homework($id) {
        $g_ids = $this->core->getQueries()->getAllElectronicGradeablesIds();
            foreach($g_ids as $index => $value) {
                if($id == $value[0]){
                    return true;
                }
            }
        return false;
    }
}