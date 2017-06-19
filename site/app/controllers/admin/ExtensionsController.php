<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
// use app\libraries\FileUtils;

class ExtensionsController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'view':
                $this->viewExtensions();
                break;
            case 'update':
                $this->updateExtensions();
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller");
                break;
        }
    }

    public function viewExtensions() {
        //Examine drop-down and get $g_id (gradeable_id)
        if (isset($_POST['selected_gradeable'])) {
            $g_id = $_POST['selected_gradeable'];
        } else {
            $g_id = $this->core->getQueries()->retrieve_newest_gradeable_id_from_db();
            foreach($g_id as $index => $value) {
                $g_id=$value[0];
            }
        }

        $user_table_db_data = $this->core->getQueries()->retrieve_users_from_db2($g_id);
        // $user_table_db_data=array();
        $g_ids = $this->core->getQueries()->getAllElectronicGradeablesIds();
        $this->core->getOutput()->renderOutput(array('admin', 'Extensions'), 'displayExtensions', $g_id, $g_ids, $user_table_db_data);

    }

    public function updateExtensions() {

        //Check to see if a CSV file was submitted.
        if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {
            $data = array();
            if (!$this->parse_and_validate_csv($_FILES['csv_upload']['tmp_name'], $data)) {
                $_SESSION['messages']['error'][] = "Something is wrong with the CSV. Try again.";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'extension', 'action' => 'view')));
                // $state = 'bad_upload';
            } else {
                // upsert($data);
                // $this->core->getQueries()->myupdateExtensions($data[$i][0], $data[$i][1], $data[$i][2]);
                // $state = 'upsert_done';
                for($i = 0; $i < count($data); $i++){
                    $this->core->getQueries()->myupdateExtensions($data[$i][0], $data[$i][1], $data[$i][2]);
                }
            }

        //if no file upload, examine Student ID and Late Day input fields.
        } 
        else{
            if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
                $_SESSION['messages']['error'][] = "Invalid CSRF token. Try again.";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'extension', 'action' => 'view')));
            }
            if (!isset($_POST['user_id']) || count($this->core->getQueries()->getUserById($_POST['user_id'])) !== 1) {
                $_SESSION['messages']['error'][] = "Invalid Student ID";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'extension', 'action' => 'view')));
            }
            if (!isset($_POST['selected_gradeable']) || !$this->validate_homework($_POST['selected_gradeable'])) {
                $_SESSION['messages']['error'][] = "Invalid Gradeable ID";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'extension', 'action' => 'view')));
            }
            if (!isset($_POST['late_days']) || !ctype_digit($_POST['late_days'])) {
                $_SESSION['messages']['error'][] = "Late Days must be a nonnegative integer";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'extension', 'action' => 'view')));
            }
            // echo($_POST['user_id']);
            // echo($_POST['selected_gradeable']);
            // echo($_POST['late_days']);

            $this->core->getQueries()->myupdateExtensions($_POST['user_id'], $_POST['selected_gradeable'], $_POST['late_days']);
            // $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'extension', 'action' => 'view')));





                // //upsert argument requires 2D array.
                // upsert(array(array($_POST['user_id'], $g_id, intval($_POST['late_days']))));
                // $state = 'upsert_done';

        }
            $user_table_db_data = $this->core->getQueries()->retrieve_users_from_db2($_POST['selected_gradeable']);
            $g_ids = $this->core->getQueries()->getAllElectronicGradeablesIds();
            $this->core->getOutput()->renderOutput(array('admin', 'Extensions'), 'displayExtensions', $_POST['selected_gradeable'], $g_ids, $user_table_db_data);
    }







// function myfunction($vartoecho) {
//     echo("GOT HERE");
//     echo($vartoecho);
// }





function parse_and_validate_csv($csv_file, &$data) {
//IN:  * csv file name and path
//     * (by reference) empty data array that will be filled.
//OUT: TRUE should csv file be properly validated and data array filled.
//     FALSE otherwise.
//PURPOSE:  (1) validate uploaded csv file so it may be parsed.
//          (2) create data array of csv information that may be batch upserted.

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
        // if (!verify_user_in_db($fields[0])) {
            $data = null;
            return false;
        }

        //$fields[1] represents timestamp in the format (MM/DD/YY),
        //(MM/DD/YYYY), (MM-DD-YY), or (MM-DD-YYYY).
        if (!$this->validate_homework($fields[1])) {
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



/* END FUNCTION parse_and_validate_csv() ==================================== */

function validate_homework($id) {
    $g_ids = $this->core->getQueries()->getAllElectronicGradeablesIds();
        foreach($g_ids as $index => $value) {
            // echo($value[0]);
            if($id == $value[0]){
                return true;
            }
        }
    return false;
}

/* END FUNCTION validate_homework() ==================================== */




}