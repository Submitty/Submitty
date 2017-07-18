<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\Output;
use app\libraries\Utils;
use app\libraries\FileUtils;
use app\models\User;

class UsersController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'get_user_details':
                $this->ajaxGetUserDetails();
                break;
            case 'update_student':
                $this->updateUser('students');
                break;
            case 'update_grader':
                $this->updateUser('graders');
                break;
            case 'graders':
                $this->core->getOutput()->addBreadcrumb("Graders");
                $this->listGraders();
                break;
            case 'rotating_sections':
                $this->rotatingSectionsForm();
                break;
            case 'update_rotating_sections':
                $this->updateRotatingSections();
                break;
            case 'upload_grader_list':
                $this->uploadGraderList();
                break;
            case 'upload_class_list':
                $this->uploadClassList();
                break;
            case 'students':
            default:
                $this->core->getOutput()->addBreadcrumb("Students");
                $this->listStudents();
                break;
        }
    }

    public function listStudents() {
        $students = $this->core->getQueries()->getAllUsers();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'listStudents', $students);
        $this->renderUserForm('update_student');
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'classListForm');
    }

    public function listGraders() {
        $graders = $this->core->getQueries()->getAllGraders();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'listGraders', $graders);
        $this->renderUserForm('update_grader');
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'graderListForm');
    }

    private function renderUserForm($action) {
        $reg_sections = $this->core->getQueries()->getRegistrationSections();
        $rot_sections = $this->core->getQueries()->getRotatingSections();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'userForm', $reg_sections, $rot_sections, $action);
    }

    public function ajaxGetUserDetails() {
        $user_id = $_REQUEST['user_id'];
        $user = $this->core->getQueries()->getUserById($user_id);
        $this->core->getOutput()->renderJson(array(
            'user_id' => $user->getId(),
            'user_firstname' => $user->getFirstName(),
            'user_lastname' => $user->getLastName(),
            'user_preferred_firstname' => $user->getPreferredFirstName(),
            'user_email' => $user->getEmail(),
            'user_group' => $user->getGroup(),
            'registration_section' => $user->getRegistrationSection(),
            'rotating_section' => $user->getRotatingSection(),
            'manual_registration' => $user->isManualRegistration(),
            'grading_registration_sections' => $user->getGradingRegistrationSections()
        ));
    }

    public function updateUser($action='students') {
        $return_url = $this->core->buildUrl(array('component' => 'admin', 'page' => 'users',
            'action' => $action), 'user-'.$_POST['user_id']);
        if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $this->core->addErrorMessage("Invalid CSRF token.");
            $this->core->redirect($return_url);
        }

        if ($_POST['edit_user'] == "true") {
            $user = $this->core->getQueries()->getSubmittyUser($_POST['user_id']);
            if ($user === null) {
                $this->core->addErrorMessage("No user found with that user id");
                $this->core->redirect($return_url);
            }
        }
        else {
            $user = $this->core->loadModel(User::class);
            $user->setId($_POST['user_id']);
        }

        $user->setFirstName($_POST['user_firstname']);
        if (isset($_POST['user_preferred_firstname'])) {
            $user->setPreferredFirstName($_POST['user_preferred_firstname']);
        }

        $user->setLastName($_POST['user_lastname']);
        $user->setEmail($_POST['user_email']);
        if (isset($_POST['user_password'])) {
            $user->setPassword($_POST['user_password']);
        }

        if ($_POST['registered_section'] === "null") {
            $user->setRegistrationSection(null);
        }
        else {
            $user->setRegistrationSection(intval($_POST['registered_section']));
        }

        if ($_POST['rotating_section'] == "null") {
            $user->setRotatingSection(null);
        }
        else {
            $user->setRotatingSection(intval($_POST['rotating_section']));
        }

        $user->setGroup(intval($_POST['user_group']));
        $user->setManualRegistration(isset($_POST['manual_registration']));
        if (isset($_POST['grading_registration_section'])) {
            $user->setGradingRegistrationSections(array_map("intval", $_POST['grading_registration_section']));
        }
        else {
            $user->setGradingRegistrationSections(array());
        }

        if ($_POST['edit_user'] == "true") {
            $this->core->getQueries()->updateUser($user, $this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse());
            $this->core->addSuccessMessage("User '{$user->getId()}' updated");
        }
        else {
            if ($this->core->getQueries()->getSubmittyUser($_POST['user_id']) === null) {
                $this->core->getQueries()->insertSubmittyUser($user);
            }
            $this->core->getQueries()->insertCourseUser($user, $this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse());
            $this->core->addSuccessMessage("User '{$user->getId()}' created");
        }
        $this->core->redirect($return_url);
    }

    public function rotatingSectionsForm() {
        $non_null_counts = $this->core->getQueries()->getCountUsersRotatingSections();
        $null_counts = $this->core->getQueries()->getCountNullUsersRotatingSections();
        $max_section = $this->core->getQueries()->getMaxRotatingSection();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'rotatingUserForm',
            $non_null_counts, $null_counts, $max_section);
    }

    public function updateRotatingSections() {
        $return_url = $this->core->buildUrl(
            array('component' => 'admin',
                  'page' => 'users',
                  'action' => 'rotating_sections')
        );

        if (!$this->core->checkCsrfToken()) {
            $this->core->addErrorMessage("Invalid CSRF token. Try again.");
            $this->core->redirect($return_url);
        }

        if (!isset($_REQUEST['sort_type'])) {
            $this->core->addErrorMessage("Must select one of the three options for setting up rotating sections");
            $this->core->redirect($return_url);
        }
        else if ($_REQUEST['sort_type'] === "drop_null") {
            $this->core->getQueries()->setNonRegisteredUsersRotatingSectionNull();
            $this->core->addSuccessMessage("Non registered students removed from rotating sections");
            $this->core->redirect($return_url);
        }

        if (isset($_REQUEST['rotating_type']) && in_array($_REQUEST['rotating_type'], array('random', 'alphabetically'))) {
            $type = $_REQUEST['rotating_type'];
        }
        else {
            $type = 'random';
        }

        $section_count = intval($_REQUEST['sections']);
        if ($section_count < 1) {
            $this->core->addErrorMessage("You must have at least one rotating section");
            $this->core->redirect($return_url);
        }

        if (in_array($_REQUEST['sort_type'], array('redo', 'fewest')) && $type == "random") {
            $sort = $_REQUEST['sort_type'];
        }
        else {
            $sort = 'redo';
        }

        $section_counts = array_fill(0, $section_count, 0);
        if ($sort === 'redo') {
            $users = $this->core->getQueries()->getRegisteredUserIds();
            if ($type === 'random') {
                shuffle($users);
            }
            $this->core->getQueries()->setAllUsersRotatingSectionNull();
            $this->core->getQueries()->deleteAllRotatingSections();
            for ($i = 1; $i <= $section_count; $i++) {
                $this->core->getQueries()->insertNewRotatingSection($i);
            }

            for ($i = 0; $i < count($users); $i++) {
                $section = $i % $section_count;
                $section_counts[$section]++;
            }
        }
        else {
            $this->core->getQueries()->setNonRegisteredUsersRotatingSectionNull();
            $max_section = $this->core->getQueries()->getMaxRotatingSection();
            if ($max_section === null) {
                $this->core->addErrorMessage("No rotating sections have been added to the system, cannot use fewest");
            }
            else if ($max_section != $section_count) {
                $this->core->addErrorMessage("Cannot use a different number of sections when setting up via fewest");
                $this->core->redirect($return_url);
            }
            $users = $this->core->getQueries()->getRegisteredUserIdsWithNullRotating();
            // only random sort can use 'fewest' type
            shuffle($users);
            $sections = $this->core->getQueries()->getCountUsersRotatingSections();
            $use_section = 0;
            $max = $sections[0]['count'];
            foreach ($sections as $section) {
                if ($section['count'] < $max) {
                    $use_section = $section['rotating_section'] - 1;
                    break;
                }
            }

            for ($i = 0; $i < count($users); $i++) {
                $section_counts[$use_section]++;
                $use_section = ($use_section + 1) % $section_count;
            }
        }

        for ($i = 0; $i < $section_count; $i++) {
            $update_users = array_splice($users, 0, $section_counts[$i]);
            if (count($update_users) == 0) {
                continue;
            }
            $this->core->getQueries()->updateUsersRotatingSection($i + 1, $update_users);
        }

        $this->core->addSuccessMessage("Rotating sections setup");
        $this->core->redirect($return_url);
    }

    private function getCsvOrXlsxData($filename, $tmp_name, $return_url) {
        $content_type = FileUtils::getContentType($filename);
        $mime_type = FileUtils::getMimeType($tmp_name);

        if ($content_type === 'spreadsheet/xlsx' && $mime_type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            $csv_file = sys_get_temp_dir() . '/' . Utils::generateRandomString();
            $xlsx_file = sys_get_temp_dir() . '/' . Utils::generateRandomString();
            $old_umask = umask(0007);
            file_put_contents($csv_file, "");
            umask($old_umask);

            if (move_uploaded_file($tmp_name, $xlsx_file)) {
                $xlsx_tmp = basename($xlsx_file);
                $csv_tmp = basename($csv_file);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->core->getConfig()->getCgiUrl()."xlsx_to_csv.cgi?xlsx_file={$xlsx_tmp}&csv_file={$csv_tmp}");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $output = curl_exec($ch);

                if ($output === false) {
                    $this->core->addErrorMessage("Error parsing xlsx to csv");
                    $this->core->redirect($return_url);
                }

                $output = json_decode($output, true);
                if ($output === null) {
                    $this->core->addErrorMessage("Error parsing JSON response: ".json_last_error_msg());
                    $this->core->redirect($return_url);
                } else if ($output['error'] === true) {
                    $this->core->addErrorMessage("Error parsing xlsx to csv: ".$output['error_message']);
                    $this->core->redirect($return_url);
                } else if ($output['success'] !== true) {
                    $this->core->addErrorMessage("Error on response on parsing xlsx: ".curl_error($ch));
                    $this->core->redirect($return_url);
                }

                curl_close($ch);
            } else {
                $this->core->addErrorMessage("Error isolating uploaded XLSX. Contact your sysadmin.");
                $this->core->redirect($return_url);
            }

        } else if ($content_type === 'text/csv' && $mime_type === 'text/plain') {
            $csv_file = $tmp_name;
            $xlsx_file = null;
        } else {
            $this->core->addErrorMessage("Must upload xlsx or csv");
            $this->core->redirect($return_url);
        }

        register_shutdown_function(
            function() use ($csv_file, $xlsx_file) {
                if (file_exists($xlsx_file)) {
                    unlink($xlsx_file);
                }
                if (file_exists($csv_file)) {
                    unlink($csv_file);
                }
            }
        );

        //Set environment config to allow '\r' EOL encoding. (Used by older versions of Microsoft Excel on Macintosh)
        ini_set("auto_detect_line_endings", true);

        $contents = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($contents === false) {
            $this->core->addErrorMessage("File was not properly uploaded. Contact your sysadmin.");
            $this->core->redirect($return_url);
        }

        if ($content_type === 'spreadsheet/xlsx' && $mime_type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            unset($contents[0]); //xlsx2csv will add a row to the top of the spreadsheet
        }

        return $contents;
    }

    public function uploadGraderList() {
        $return_url = $this->core->buildUrl(array('component'=>'admin', 'page'=>'users', 'action'=>'graders'));

        if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $this->core->addErrorMessage("Invalid CSRF token");
            $this->core->redirect($return_url);
        }

        if ($_FILES['upload']['name'] == "") {
            $this->core->addErrorMessage("No input file specified");
            $this->core->redirect($return_url);
        }

        $contents = $this->getCsvOrXlsxData($_FILES['upload']['name'], $_FILES['upload']['tmp_name'], $return_url);

        //Validation and error checking.
        $error_message = "";
        $row_num = 0;
        $graders_data = array();
        foreach($contents as $content) {
            $row_num++;
            $vals = str_getcsv(trim($content));
            if (isset($vals[4])) $vals[4] = intval($vals[4]); //change float read from xlsx to int

            //No check on user_id (computing login ID) -- different Univeristies have different formats.

            //First and Last name must be alpha characters, white-space, or certain punctuation.
            $error_message .= preg_match("~^[a-zA-Z.'`\- ]+$~", $vals[1]) ? "" : "Error in first name column, row #{$row_num}: {$vals[1]}," . PHP_EOL;
            $error_message .= preg_match("~^[a-zA-Z.'`\- ]+$~", $vals[2]) ? "" : "Error in last name column, row #{$row_num}: {$vals[2]}," . PHP_EOL;

            //Check email address for format "address@domain".
            $error_message .= preg_match("~.+@{1}[a-zA-Z0-9:\.\-\[\]]+$~", $vals[3]) ? "" : "Error in email column, row #{$row_num}: {$vals[3]}," . PHP_EOL;

            //grader-level check is a digit between 1 - 4.
            $error_message .= preg_match("~[1-4]{1}~", $vals[4]) ? "" : "Error in grader-level column, row #{$row_num}: {$vals[4]}," . PHP_EOL;

            $graders_data[] = $vals;
        }

        //Display any accumulated errors.  Quit on errors, otherwise continue.
        if (!empty($error_message)) {
            $this->core->addErrorMessage($error_message." Contact your sysadmin if this should not cause an error.");
            $this->core->redirect($return_url);
        }

        //Existing graders are not updated.
        $existing_users = $this->core->getQueries()->getAllUsers();
        $graders_to_add = array();
        $graders_to_update = array();
        foreach($graders_data as $grader_data) {
            $exists = false;
            foreach($existing_users as $i => $existing_user) {
                if ($grader_data[0] === $existing_user->getId()) {
                    if ($grader_data[4] !== $existing_user->getGroup()) {
                        $graders_to_update[] = $grader_data;
                    }
                    unset($existing_users[$i]);
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $graders_to_add[] = $grader_data;
            }
        }

        //Insert new graders to database
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        foreach($graders_to_add as $grader_data) {
            $grader = new User($this->core);
            $grader->setId($grader_data[0]);
            $grader->setFirstName($grader_data[1]);
            $grader->setLastName($grader_data[2]);
            $grader->setEmail($grader_data[3]);
            $grader->setGroup($grader_data[4]);
            if ($this->core->getQueries()->getSubmittyUser($grader_data[0]) === null) {
                $this->core->getQueries()->insertSubmittyUser($grader);
            }
            $this->core->getQueries()->insertCourseUser($grader, $semester, $course);
        }
        foreach($graders_to_update as $grader_data) {
            $grader = $this->core->getQueries()->getUserById($grader_data[0]);
            $grader->setGroup($grader_data[4]);
            $this->core->getQueries()->updateUser($grader, $semester, $course);
        }

        $added = count($graders_to_add);
        $updated = count($graders_to_update);
        $this->core->addSuccessMessage("Uploaded {$_FILES['upload']['name']}: ({$added} added, {$updated} updated)");
        $this->core->redirect($return_url);
    }

    public function uploadClassList() {
        $return_url = $this->core->buildUrl(array('component'=>'admin', 'page'=>'users', 'action'=>'students'));

        if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $this->core->addErrorMessage("Invalid CSRF token");
            $this->core->redirect($return_url);
        }

        if ($_FILES['upload']['name'] == "") {
            $this->core->addErrorMessage("No input file specified");
            $this->core->redirect($return_url);
        }

        $contents = $this->getCsvOrXlsxData($_FILES['upload']['name'], $_FILES['upload']['tmp_name'], $return_url);

        //Validation and error checking.
        $num_reg_sections = count($this->core->getQueries()->getRegistrationSections());
        $error_message = "";
        $row_num = 0;
        $students_data = array();
        foreach($contents as $content) {
            $row_num++;
            $vals = str_getcsv(trim($content));
            if (isset($vals[4])) $vals[4] = intval($vals[4]); //change float read from xlsx to int

            //No check on user_id (computing login ID) -- different Univeristies have different formats.

            //First and Last name must be alpha characters, white-space, or certain punctuation.
            $error_message .= preg_match("~^[a-zA-Z.'`\- ]+$~", $vals[1]) ? "" : "Error in first name column, row #{$row_num}: {$vals[1]}," . PHP_EOL;
            $error_message .= preg_match("~^[a-zA-Z.'`\- ]+$~", $vals[2]) ? "" : "Error in last name column, row #{$row_num}: {$vals[2]}," . PHP_EOL;

            //Check email address for format "address@domain".
            $error_message .= preg_match("~.+@{1}[a-zA-Z0-9:\.\-\[\]]+$~", $vals[3]) ? "" : "Error in email column, row #{$row_num}: {$vals[3]}," . PHP_EOL;

            //Student section must be greater than zero (intval($str) returns zero when $str is not integer)
            $error_message .= (($vals[4] > 0) && ($vals[4] <= $num_reg_sections)) ? "" : "Error in student section column, row #{$row_num}: {$vals[4]}," . PHP_EOL;

            $students_data[] = $vals;
        }

        //Display any accumulated errors.  Quit on errors, otherwise continue.
        if (!empty($error_message)) {
            $this->core->addErrorMessage($error_message." Contact your sysadmin if this should not cause an error.");
            $this->core->redirect($return_url);
        }

        //Existing students are not updated.
        $existing_users = $this->core->getQueries()->getAllUsers();
        $students_to_add = array();
        $students_to_update = array();
        foreach($students_data as $student_data) {
            $exists = false;
            foreach($existing_users as $i => $existing_user) {
                if ($student_data[0] === $existing_user->getId()) {
                    if ($student_data[4] !== $existing_user->getRegistrationSection()) {
                        $students_to_update[] = $student_data;
                    }
                    unset($existing_users[$i]);
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $students_to_add[] = $student_data;
            }
        }

        //Insert new students to database
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        foreach($students_to_add as $student_data) {
            $student = new User($this->core);
            $student->setId($student_data[0]);
            $student->setFirstName($student_data[1]);
            $student->setLastName($student_data[2]);
            $student->setEmail($student_data[3]);
            $student->setRegistrationSection($student_data[4]);
            $student->setGroup(4);
            if ($this->core->getQueries()->getSubmittyUser($student_data[0]) === null) {
                $this->core->getQueries()->insertSubmittyUser($student);
            }
            $this->core->getQueries()->insertCourseUser($student, $semester, $course);
        }
        foreach($students_to_update as $student_data) {
            $student = $this->core->getQueries()->getUserById($student_data[0]);
            $student->setRegistrationSection($student_data[4]);
            $this->core->getQueries()->updateUser($student, $semester, $course);
        }

        $added = count($students_to_add);
        $updated = count($students_to_update);

        if (isset($_POST['move_missing'])) {
            foreach($existing_users as $user) {
                if ($user->getRegistrationSection() != null) {
                    $user->setRegistrationSection(null);
                    $this->core->getQueries()->updateUser($user, $semester, $course);
                    $updated++;
                }
            }
        }

        $this->core->addSuccessMessage("Uploaded {$_FILES['upload']['name']}: ({$added} added, {$updated} updated)");
        $this->core->redirect($return_url);
    }
}
