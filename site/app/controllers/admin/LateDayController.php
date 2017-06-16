<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
// use app\libraries\FileUtils;

class LateDayController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'view':
                $this->viewLateDays();
                break;
            case 'update':
                $this->updateLateDays();
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller");
                break;
        }
    }

    public function viewLateDays() {



        $user_table_db_data = $this->core->getQueries()->retrieve_users_from_db();

        $this->core->getOutput()->renderOutput(array('admin', 'LateDay'), 'displayLateDays', $user_table_db_data);
    }

    public function updateLateDays() {
        //Check to see if a CSV file was submitted.

        // if (isset($_FILES['csv_upload'])) {
        //     $this->myfunction($_FILES['csv_upload']);
        //     $bool = $this->file_exists($_FILES['csv_upload']['tmp_name']);
        //     if($bool){

        if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {
                $data = array();
                if (!($this->parse_and_validate_csv($_FILES['csv_upload']['tmp_name'], $data))) {
                    $this->myfunction("IF");
                    $state = 'bad_upload';
                } else {
                    $this->myfunction("ELSE");
                    $this->upsert($data);
                    $state = 'upsert_done';
                }
        $this->myfunction($state);
            // }

        //if no file upload, examine Student ID and Late Day input fields.
        } else{
            if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
                $_SESSION['messages']['error'][] = "Invalid CSRF token. Try again.";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'late_day', 'action' => 'view')));
            }

            if (!isset($_POST['user_id']) || count($this->core->getQueries()->getUserById($_POST['user_id'])) !== 1) {
                $_SESSION['messages']['error'][] = "Invalid Student ID";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'late_day', 'action' => 'view')));
            }
            if (!isset($_POST['datestamp']) || !$this->validate_timestamp($_POST['datestamp'])) {
                $_SESSION['messages']['error'][] = "Datestamp must be mm/dd/yy";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'late_day', 'action' => 'view')));
            }
            if (!isset($_POST['late_days']) || !ctype_digit($_POST['late_days'])) {
                $_SESSION['messages']['error'][] = "Late Days must be a nonnegative integer";
                $_SESSION['request'] = $_POST;
                $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'late_day', 'action' => 'view')));
            }

        }



//     //Process upsert if no errors were flagged.
//     if (empty($state)) {

//         //upsert argument requires 2D array.
//         upsert(array(array($_POST['user_id'], $_POST['datestamp'], intval($_POST['late_days']))));
//         $state = 'upsert_done';
//     }
// }

    }






function myfunction($vartoecho) {
    echo("GOT HERE");
    echo($vartoecho);
}





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
        if (!$this->validate_timestamp($fields[1])) {
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



function upsert(array $data) {
//IN:  Data to be "upserted"
//OUT: No return.  This is assumed to work.  (Server should throw an exception
//     if this process fails)
//PURPOSE:  "Update/Insert" data into the database.  This can handle large
//          "batch" upserts in a single transaction (for when upserting via CSV)

/* -----------------------------------------------------------------------------
 * This SQL code was adapted from upsert discussion on Stack Overflow and is
 * meant to be compatible with PostgreSQL prior to v9.5.
 *
 *  q.v. http://stackoverflow.com/questions/17267417/how-to-upsert-merge-insert-on-duplicate-update-in-postgresql
 * -------------------------------------------------------------------------- */

    $sql = array();

    //TEMPORARY table to hold all new values that will be "upserted"
    $sql['temp_table'] = <<<SQL
CREATE TEMPORARY TABLE temp
    (user_id VARCHAR(255),
    date TIMESTAMP,
    late_days INTEGER)
ON COMMIT DROP;
SQL;

    //INSERT new data into temporary table -- prepares all data to be upserted
    //in a single DB transaction.
    for ($i=0; $i<count($data); $i++) {
        $sql["data_{$i}"] = <<<SQL
INSERT INTO temp VALUES (?,?,?);
SQL;
    }

    //LOCK will prevent sharing collisions while upsert is in process.
    $sql['lock'] = <<<SQL
LOCK TABLE late_days IN EXCLUSIVE MODE;
SQL;

    //This portion ensures that UPDATE will only occur when a record already exists.
    $sql['update'] = <<<SQL
UPDATE late_days
SET allowed_late_days=temp.late_days
FROM temp
WHERE late_days.user_id=temp.user_id
    AND late_days.since_timestamp=temp.date
SQL;

    //This portion ensures that INSERT will only occur when data record is new.
    $sql['insert'] = <<<SQL
INSERT INTO late_days
    (user_id,
    since_timestamp,
    allowed_late_days)
SELECT
    temp.user_id,
    temp.date,
    temp.late_days
FROM temp
LEFT OUTER JOIN late_days
    ON late_days.user_id=temp.user_id
    AND late_days.since_timestamp=temp.date
WHERE late_days.user_id IS NULL
SQL;

    //Begin DB transaction
    \lib\Database::beginTransaction();
    \lib\Database::query($sql['temp_table']);

    foreach ($data as $index => $record) {
        \lib\Database::query($sql["data_{$index}"], $record);
    }

    \lib\Database::query($sql['lock']);
    \lib\Database::query($sql['update']);
    \lib\Database::query($sql['insert']);
    \lib\Database::commit();
    //All Done!
    //Server will throw exception if there is a problem with DB access.
}

/* END FUNCTION upsert() ==================================================== */



/* END FUNCTION parse_and_validate_csv() ==================================== */

function validate_timestamp($timestamp) {
// //IN:  $timestamp is actually a date string, not a Unix timestamp.
// //OUT: TRUE when date string conforms to an accetpable pattern
// //      FALSE otherwise.
// //PURPOSE: Validate string to (1) be a valid date and (2) conform to specific
// //         date patterns.
// //         'm-d-Y' -> mm-dd-yyyy
// //         'm-d-y' -> mm-dd-yy
// //         'm/d/Y' -> mm/dd/yyyy
// //         'm/d/y' -> mm/dd/yy

//     //This bizzare/inverted switch-case block actually does work in PHP.
//     //This operates as a form of "white list" of valid patterns.
//     //This checks to ensure a date pattern is acceptable AND the date actually
//     //exists.  e.g. "02-29-2016" is valid, while "06-31-2016" is not.
//     //That is, 2016 is a leap year, but June has only 30 days.
//     $tmp = array(DateTime::createFromFormat('m-d-Y', $timestamp),
//                  DateTime::createFromFormat('m/d/Y', $timestamp),
//                  DateTime::createFromFormat('m-d-y', $timestamp),
//                  DateTime::createFromFormat('m/d/y', $timestamp));

//     switch (true) {
//     case ($tmp[0] && $tmp[0]->format('m-d-Y') === $timestamp):
//     case ($tmp[1] && $tmp[1]->format('m/d/Y') === $timestamp):
//     case ($tmp[2] && $tmp[2]->format('m-d-y') === $timestamp):
//     case ($tmp[3] && $tmp[3]->format('m/d/y') === $timestamp):
//         return true;
//     default:
//         return false;
//     }
    return true;
}

/* END FUNCTION validate_timestamp() ==================================== */




}