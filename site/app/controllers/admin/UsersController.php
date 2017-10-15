<?php

namespace app\controllers\admin;

use app\authentication\DatabaseAuthentication;
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
                $this->core->getOutput()->addBreadcrumb('View Graders');
                $this->listGraders();
                break;
            case 'rotating_sections':
                $this->core->getOutput()->addBreadcrumb('Setup Rotating Sections');
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
                $this->core->getOutput()->addBreadcrumb('View Students');
                $this->listStudents();
                break;
        }
    }

    public function listStudents() {
        $students = $this->core->getQueries()->getAllUsers();
        $use_database = $this->core->getAuthentication() instanceof DatabaseAuthentication;
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'listStudents', $students);
        $this->renderUserForm('update_student', $use_database);
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'classListForm', $use_database);
    }

    public function listGraders() {
        $graders = $this->core->getQueries()->getAllGraders();
        $use_database = $this->core->getAuthentication() instanceof DatabaseAuthentication;
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'listGraders', $graders);
        $this->renderUserForm('update_grader', $use_database);
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'graderListForm', $use_database);
    }

    private function renderUserForm($action, $use_database) {
        $reg_sections = $this->core->getQueries()->getRegistrationSections();
        $rot_sections = $this->core->getQueries()->getRotatingSections();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'userForm', $reg_sections, $rot_sections, $action, $use_database);
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
            'user_updated' => $user->isUserUpdated(),
            'instructor_updated' => $user->isInstructorUpdated(),
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
        $use_database = $this->core->getAuthentication() instanceof DatabaseAuthentication;
        $_POST['user_id'] = trim($_POST['user_id']);

        if (empty($_POST['user_id'])) {
            $this->core->addErrorMessage("User ID cannot be empty");
        }

        $user = $this->core->getQueries()->getSubmittyUser($_POST['user_id']);
        if ($_POST['edit_user'] == "true" && $user === null) {
            $this->core->addErrorMessage("No user found with that user id");
            $this->core->redirect($return_url);
        }
        elseif ($_POST['edit_user'] != "true" && $user !== null) {
            $user->setRegistrationSection($_POST['registered_section'] === "null" ? null : intval($_POST['registered_section']));
            $user->setRotatingSection($_POST['rotating_section'] === "null" ? null : intval($_POST['rotating_section']));
            $user->setGroup(intval($_POST['user_group']));
            $user->setManualRegistration(isset($_POST['manual_registration']));
            $user->setGradingRegistrationSections(!isset($_POST['grading_registration_section']) ? array() : array_map("intval", $_POST['grading_registration_section']));
			//Instructor updated flag tells auto feed to not clobber some of the users data.
            $user->setInstructorUpdated(true);
            $this->core->getQueries()->insertCourseUser($user, $this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse());
            $this->core->addSuccessMessage("Added {$_POST['user_id']} to {$this->core->getConfig()->getCourse()}");
            $this->core->redirect($return_url);
        }

        $error_message = "";
        //Username must contain only lowercase alpha, numbers, underscores, hyphens
        $error_message .= User::validateUserData('user_id', trim($_POST['user_id'])) ? "" : "Error in username: \"".strip_tags($_POST['user_id'])."\"<br>";
        //First and Last name must be alpha characters, white-space, or certain punctuation.
        $error_message .= User::validateUserData('user_firstname', trim($_POST['user_firstname'])) ? "" : "Error in first name: \"".strip_tags($_POST['user_firstname'])."\"<br>";
        $error_message .= User::validateUserData('user_lastname', trim($_POST['user_lastname'])) ? "" : "Error in last name: \"".strip_tags($_POST['user_lastname'])."\"<br>";
		//Check email address for appropriate format. e.g. "user@university.edu", "user@cs.university.edu", etc.
		$error_message .= User::validateUserData('user_email', trim($_POST['user_email'])) ? "" : "Error in email: \"".strip_tags($_POST['user_email'])."\"<br>";
        //Preferred first name must be alpha characters, white-space, or certain punctuation.
        if (!empty($_POST['user_preferred_firstname']) && trim($_POST['user_preferred_firstname']) != "") {
            $error_message .= User::validateUserData('user_preferred_firstname', trim($_POST['user_preferred_firstname'])) ? "" : "Error in preferred first name: \"".strip_tags($_POST['user_preferred_firstname'])."\"<br>";
        }
        //Database password cannot be blank, no check on format
        if ($use_database) {
            $error_message .= User::validateUserData('user_password', $_POST['user_password']) ? "" : "Error must enter password for user<br>";
        }

        if (!empty($error_message)) {
            $this->core->addErrorMessage($error_message." Contact your sysadmin if this should not cause an error.");
            $this->core->redirect($return_url);
        }

        if ($_POST['edit_user'] == "true") {
            if ($user === null) {
                $this->core->addErrorMessage("No user found with that user id");
                $this->core->redirect($return_url);
            }
        }
        else {
            if ($user !== null) {
                $this->core->addErrorMessage("A user with that ID already exists");
                $this->core->redirect($return_url);
            }
            $user = $this->core->loadModel(User::class);
            $user->setId(trim($_POST['user_id']));
        }

        $user->setFirstName(trim($_POST['user_firstname']));
        if (isset($_POST['user_preferred_firstname']) && trim($_POST['user_preferred_firstname']) != "") {
            $user->setPreferredFirstName(trim($_POST['user_preferred_firstname']));
        }

        $user->setLastName(trim($_POST['user_lastname']));
        $user->setEmail(trim($_POST['user_email']));
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
		//Instructor updated flag tells auto feed to not clobber some of the users data.
        $user->setInstructorUpdated(true);
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
        $use_database = $this->core->getAuthentication() instanceof DatabaseAuthentication;

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
        $pref_name_idx = $use_database ? 6 : 5;
        $error_message = "";
        $row_num = 0;
        $graders_data = array();
        foreach($contents as $content) {
            $row_num++;
            $vals = str_getcsv($content);
            $vals = array_map('trim', $vals);
            if (isset($vals[4])) $vals[4] = intval($vals[4]); //change float read from xlsx to int

            //Username must contain only lowercase alpha, numbers, underscores, hyphens
            $error_message .= User::validateUserData('user_id', $vals[0]) ? "" : "ERROR on row {$row_num}, User Name \"".strip_tags($vals[0])."\"<br>";

            //First and Last name must be alpha characters, white-space, or certain punctuation.
            $error_message .= User::validateUserData('user_firstname', $vals[1]) ? "" : "ERROR on row {$row_num}, First Name \"".strip_tags($vals[1])."\"<br>";
            $error_message .= User::validateUserData('user_lastname', $vals[2]) ? "" : "ERROR on row {$row_num}, Last Name \"".strip_tags($vals[2])."\"<br>";

            //Check email address for appropriate format. e.g. "grader@university.edu", "grader@cs.university.edu", etc.
            $error_message .= User::validateUserData('user_email', $vals[3]) ? "" : "ERROR on row {$row_num}, email \"".strip_tags($vals[3])."\"<br>";

            //grader-level check is a digit between 1 - 4.
            $error_message .= User::validateUserData('user_group', $vals[4]) ? "" : "ERROR on row {$row_num}, Grader Group \"".strip_tags($vals[4])."\"<br>";

            //Preferred first name must be alpha characters, white-space, or certain punctuation.
            if (isset($vals[$pref_name_idx]) && ($vals[$pref_name_idx] != "")) {
                $error_message .= User::validateUserData('user_preferred_firstname', $vals[$pref_name_idx]) ? "" : "ERROR on row {$row_num}, Preferred First Name \"".strip_tags($vals[$pref_name_idx])."\"<br>";
            }

            //Database password cannot be blank, no check on format
            if ($use_database) {
                $error_message .= User::validateUserData('user_password', $vals[5]) ? "" : "ERROR on row {$row_num}, password cannot be blank<br>";
            }

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
            if (isset($grader_data[$pref_name_idx]) && ($grader_data[$pref_name_idx] != "")) {
                $grader->setPreferredFirstName($grader_data[$pref_name_idx]);
            }
            if ($use_database) {
                $grader->setPassword($grader_data[5]);
            }
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
        $use_database = $this->core->getAuthentication() instanceof DatabaseAuthentication;

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
        $pref_name_idx = $use_database ? 6 : 5;
        $error_message = "";
        $row_num = 0;
        $students_data = array();
        foreach($contents as $content) {
            $row_num++;
            $vals = str_getcsv($content);
            $vals = array_map('trim', $vals);

            if (isset($vals[4])) {
                if (is_numeric($vals[4])) {
                    $vals[4] = intval($vals[4]);
                }
                else if (strtolower($vals[4]) === "null") {
                    $vals[4] = null;
                }
            }

            //Username must contain only lowercase alpha, numbers, underscores, hyphens
            $error_message .= User::validateUserData('user_id', $vals[0]) ? "" : "ERROR on row {$row_num}, User Name \"".strip_tags($vals[0])."\"<br>";

            //First and Last name must be alpha characters, white-space, or certain punctuation.
            $error_message .= User::validateUserData('user_firstname', $vals[1]) ? "" : "ERROR on row {$row_num}, First Name \"{$vals[1]}\"<br>";
            $error_message .= User::validateUserData('user_lastname', $vals[2]) ? "" : "ERROR on row {$row_num}, Last Name \"".strip_tags($vals[2])."\"<br>";

            //Check email address for appropriate format. e.g. "student@university.edu", "student@cs.university.edu", etc.
            $error_message .= User::validateUserData('user_email', $vals[3]) ? "" : "ERROR on row {$row_num}, email \"".strip_tags($vals[3])."\"<br>";

            //Student section must be greater than zero (intval($str) returns zero when $str is not integer)
            $error_message .= (($vals[4] > 0 && $vals[4] <= $num_reg_sections) || $vals[4] === null) ? "" : "ERROR on row {$row_num}, Registration Section \"".strip_tags($vals[4])."\"<br>";

            //Preferred first name must be alpha characters, white-space, or certain punctuation.
            if (isset($vals[$pref_name_idx]) && ($vals[$pref_name_idx] != "")) {
                $error_message .= User::validateUserData('user_preferred_firstname', $vals[$pref_name_idx]) ? "" : "ERROR on row {$row_num}, Preferred First Name \"".strip_tags($vals[$pref_name_idx])."\"<br>";
            }

            //Database password cannot be blank, no check on format
            if ($use_database) {
                $error_message .= User::validateUserData('user_password', $vals[5]) ? "" : "ERROR on row {$row_num}, password cannot be blank<br>";
            }

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
            if (isset($student_data[$pref_name_idx]) && ($student_data[$pref_name_idx] != "")) {
                $student->setPreferredFirstName($student_data[$pref_name_idx]);
            }
            if ($use_database) {
                $student->setPassword($student_data[5]);
            }
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
