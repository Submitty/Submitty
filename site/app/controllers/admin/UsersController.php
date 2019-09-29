<?php

namespace app\controllers\admin;

use app\authentication\DatabaseAuthentication;
use app\controllers\AbstractController;
use app\controllers\admin\AdminGradeableController;
use app\libraries\FileUtils;
use app\libraries\response\JsonResponse;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use app\models\User;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;

//Enable us to throw, catch, and handle exceptions as needed.
use app\exceptions\ValidationException;
use app\exceptions\DatabaseException;

/**
 * Class UsersController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */
class UsersController extends AbstractController {
    /**
     * @Route("/{_semester}/{_course}/users", methods={"GET"})
     * @Route("/api/{_semester}/{_course}/users", methods={"GET"})
     * @return Response
     */
    public function getStudents() {
        $students = $this->core->getQueries()->getAllUsers();
        //Assemble students into sections
        $sorted_students = [];
        $download_info = [];
        foreach ($students as $student) {
            $rot_sec = ($student->getRotatingSection() === null) ? 'NULL' : $student->getRotatingSection();
            $reg_sec = ($student->getRegistrationSection() === null) ? 'NULL' : $student->getRegistrationSection();
            $sorted_students[$reg_sec][] = $student;
            switch ($student->getGroup()) {
                case User::GROUP_INSTRUCTOR:
                    $grp = 'Instructor';
                    break;
                case User::GROUP_FULL_ACCESS_GRADER:
                    $grp = 'Full Access Grader (Grad TA)';
                    break;
                case User::GROUP_LIMITED_ACCESS_GRADER:
                    $grp = 'Limited Access Grader (Mentor)';
                    break;
                default:
                    $grp = 'Student';
                    break;
            }
            array_push($download_info, [
                'first_name' => $student->getDisplayedFirstName(),
                'last_name' => $student->getDisplayedLastName(),
                'user_id' => $student->getId(),
                'email' => $student->getEmail(),
                'reg_section' => $reg_sec,
                'rot_section' => $rot_sec,
                'group' => $grp
            ]);
        }

        return new Response(
            JsonResponse::getSuccessResponse($download_info),
            new WebResponse(
                ['admin', 'Users'],
                'listStudents',
                $sorted_students,
                $this->core->getQueries()->getRegistrationSections(),
                $this->core->getQueries()->getRotatingSections(),
                $download_info,
                $this->core->getAuthentication() instanceof DatabaseAuthentication
            )
        );
    }

    /**
     * @Route("/{_semester}/{_course}/graders", methods={"GET"})
     * @Route("/api/{_semester}/{_course}/graders", methods={"GET"})
     * @return Response
     */
    public function getGraders() {
        $graders = $this->core->getQueries()->getAllGraders();
        $graders_sorted = [
            User::GROUP_INSTRUCTOR => [],
            User::GROUP_FULL_ACCESS_GRADER => [],
            User::GROUP_LIMITED_ACCESS_GRADER => []
        ];

        $download_info = [];

        foreach ($graders as $grader) {
            $rot_sec = ($grader->getRotatingSection() === null) ? 'NULL' : $grader->getRotatingSection();
            switch ($grader->getGroup()) {
                case User::GROUP_INSTRUCTOR:
                    $reg_sec = 'All';
                    $grp = 'Instructor';
                    $graders_sorted[User::GROUP_INSTRUCTOR][] = $grader;
                    break;
                case User::GROUP_FULL_ACCESS_GRADER:
                    $grp = 'Full Access Grader (Grad TA)';
                    $reg_sec = implode(',', $grader->getGradingRegistrationSections());
                    $graders_sorted[User::GROUP_FULL_ACCESS_GRADER][] = $grader;
                    break;
                case User::GROUP_LIMITED_ACCESS_GRADER:
                    $grp = 'Limited Access Grader (Mentor)';
                    $reg_sec = implode(',', $grader->getGradingRegistrationSections());
                    $graders_sorted[User::GROUP_LIMITED_ACCESS_GRADER][] = $grader;
                    break;
                default:
                    $grp = 'UNKNOWN';
                    $reg_sec = "";
                    break;
            }
            array_push($download_info, [
                'first_name' => $grader->getDisplayedFirstName(),
                'last_name' => $grader->getDisplayedLastName(),
                'user_id' => $grader->getId(),
                'email' => $grader->getEmail(),
                'reg_section' => $reg_sec,
                'rot_section' => $rot_sec,
                'group' => $grp
            ]);
        }

        return new Response(
            JsonResponse::getSuccessResponse($download_info),
            new WebResponse(
                ['admin', 'Users'],
                'listGraders',
                $graders_sorted,
                $this->core->getQueries()->getRegistrationSections(),
                $this->core->getQueries()->getRotatingSections(),
                $download_info,
                $this->core->getAuthentication() instanceof DatabaseAuthentication
            )
        );
    }

    /**
     * @Route("/{_semester}/{_course}/graders/assign_registration_sections", methods={"POST"})
     */
    public function reassignRegistrationSections() {
        $return_url = $this->core->buildCourseUrl(['graders']);
        $new_registration_information = array();

        foreach ($_POST as $key => $value) {
            $key_array = explode("_",$key,2);
            if (!array_key_exists($key_array[0],$new_registration_information)) {
                $new_registration_information[$key_array[0]] = array();
            }
            if ($key_array[1] != 'all') {
                $new_registration_information[$key_array[0]][] = $key_array[1];
            }
        }

        foreach($this->core->getQueries()->getAllGraders() as $grader) {
            $grader_id = $grader->getId();
            if (array_key_exists($grader_id,$new_registration_information)) {
                $grader->setGradingRegistrationSections($new_registration_information[$grader_id]);
            }
            else {
                $grader->setGradingRegistrationSections(array());
            }
            $this->core->getQueries()->updateUser($grader, $this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse());
        }

        $this->core->redirect($return_url);
    }

    /**
     * @Route("/{_semester}/{_course}/users/details", methods={"GET"})
     */
    public function ajaxGetUserDetails($user_id) {
        $user = $this->core->getQueries()->getUserById($user_id);
        $this->core->getOutput()->renderJsonSuccess(array(
            'user_id' => $user->getId(),
            'already_in_course' => true,
            'user_numeric_id' => $user->getNumericId(),
            'user_firstname' => $user->getLegalFirstName(),
            'user_lastname' => $user->getLegalLastName(),
            'user_preferred_firstname' => $user->getPreferredFirstName(),
            'user_preferred_lastname' => $user->getPreferredLastName(),
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

    /**
     * @Route("/{_semester}/{_course}/user_information", methods={"GET"})
     */
    public function ajaxGetSubmittyUsers() {
        $submitty_users = $this->core->getQueries()->getAllSubmittyUsers();
        $user_ids = array_keys($submitty_users);
        $course_users = $this->core->getQueries()->getUsersById($user_ids);

        //uses more thorough course information if it exists, if not uses database information
        $user_information = array();
        foreach ($user_ids as $user_id) {
            $already_in_course = array_key_exists($user_id,$course_users);
            $user = $already_in_course ? $course_users[$user_id] : $submitty_users[$user_id];
            $user_information[$user_id] = array(
                'already_in_course' => $already_in_course,
                'user_numeric_id' => $user->getNumericId(),
                'user_firstname' => $user->getLegalFirstName(),
                'user_lastname' => $user->getLegalLastName(),
                'user_preferred_firstname' => $user->getPreferredFirstName() ?? '',
                'user_preferred_lastname' => $user->getPreferredLastName() ?? '',
                'user_email' => $user->getEmail(),
                'user_group' => $user->getGroup(),
                'registration_section' => $user->getRegistrationSection(),
                'rotating_section' => $user->getRotatingSection(),
                'user_updated' => $user->isUserUpdated(),
                'instructor_updated' => $user->isInstructorUpdated(),
                'manual_registration' => $user->isManualRegistration(),
                'grading_registration_sections' => $user->getGradingRegistrationSections()
            );
        }
        $this->core->getOutput()->renderJsonSuccess($user_information);
    }

    /**
     * @Route("/{_semester}/{_course}/users", methods={"POST"})
     */
    public function updateUser($type='users') {
        $return_url = $this->core->buildCourseUrl([$type]) . '#user-' . $_POST['user_id'];
        $use_database = $this->core->getAuthentication() instanceof DatabaseAuthentication;
        $_POST['user_id'] = trim($_POST['user_id']);
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        if (empty($_POST['user_id'])) {
            $this->core->addErrorMessage("User ID cannot be empty");
        }

        $user = $this->core->getQueries()->getUserById($_POST['user_id']);

        $error_message = "";
        //Username must contain only lowercase alpha, numbers, underscores, hyphens
        $error_message .= User::validateUserData('user_id', trim($_POST['user_id'])) ? "" : "Error in username: \"".strip_tags($_POST['user_id'])."\"<br>";
        //First and Last name must be alpha characters, white-space, or certain punctuation.
        $error_message .= User::validateUserData('user_legal_firstname', trim($_POST['user_firstname'])) ? "" : "Error in first name: \"".strip_tags($_POST['user_firstname'])."\"<br>";
        $error_message .= User::validateUserData('user_legal_lastname', trim($_POST['user_lastname'])) ? "" : "Error in last name: \"".strip_tags($_POST['user_lastname'])."\"<br>";
        //Check email address for appropriate format. e.g. "user@university.edu", "user@cs.university.edu", etc.
        $error_message .= User::validateUserData('user_email', trim($_POST['user_email'])) ? "" : "Error in email: \"".strip_tags($_POST['user_email'])."\"<br>";
        //Preferred first name must be alpha characters, white-space, or certain punctuation.
        if (!empty($_POST['user_preferred_firstname']) && trim($_POST['user_preferred_firstname']) !== "") {
            $error_message .= User::validateUserData('user_preferred_firstname', trim($_POST['user_preferred_firstname'])) ? "" : "Error in preferred first name: \"".strip_tags($_POST['user_preferred_firstname'])."\"<br>";
        }
        if (!empty($_POST['user_preferred_lastname']) && trim($_POST['user_preferred_lastname']) !== "") {
            $error_message .= User::validateUserData('user_preferred_lastname', trim($_POST['user_preferred_lastname'])) ? "" : "Error in preferred last name: \"".strip_tags($_POST['user_preferred_lastname'])."\"<br>";
        }

        //Database password cannot be blank, no check on format
        if ($use_database && (($_POST['edit_user'] == 'true' && !empty($_POST['user_password'])) || $_POST['edit_user'] != 'true')) {
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
            $user = $this->core->loadModel(User::class);
            $user->setId(trim($_POST['user_id']));
        }

        $user->setNumericId(trim($_POST['user_numeric_id']));

        $user->setLegalFirstName(trim($_POST['user_firstname']));
        if (isset($_POST['user_preferred_firstname']) && trim($_POST['user_preferred_firstname']) != "") {
            $user->setPreferredFirstName(trim($_POST['user_preferred_firstname']));
        }

        $user->setLegalLastName(trim($_POST['user_lastname']));
        if (isset($_POST['user_preferred_lastname']) && trim($_POST['user_preferred_lastname']) != "") {
            $user->setPreferredLastName(trim($_POST['user_preferred_lastname']));
        }

        $user->setEmail(trim($_POST['user_email']));

        if (!empty($_POST['user_password'])) {
            $user->setPassword($_POST['user_password']);
        }

        if ($_POST['registered_section'] === "null") {
            $user->setRegistrationSection(null);
        }
        else {
            $user->setRegistrationSection($_POST['registered_section']);
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
            $user->setGradingRegistrationSections($_POST['grading_registration_section']);
        }
        else {
            $user->setGradingRegistrationSections(array());
        }

        if ($_POST['edit_user'] == "true") {
            $this->core->getQueries()->updateUser($user, $semester, $course);
            $this->core->addSuccessMessage("User '{$user->getId()}' updated");
        }
        else {
            if ($this->core->getQueries()->getSubmittyUser($_POST['user_id']) === null) {
                $this->core->getQueries()->insertSubmittyUser($user);
                $this->core->addSuccessMessage("Added a new user {$user->getId()} to Submitty");
                $this->core->getQueries()->insertCourseUser($user, $semester, $course);
                $this->core->addSuccessMessage("New Submitty user '{$user->getId()}' added");
            }
            else {
                $this->core->getQueries()->updateUser($user);
                $this->core->getQueries()->insertCourseUser($user, $this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse());
                $this->core->addSuccessMessage("Existing Submitty user '{$user->getId()}' added");
            }

            $all_gradeable_ids = $this->core->getQueries()->getAllGradeablesIds();
            foreach ($all_gradeable_ids as $row) {
                $g_id = $row['g_id'];
                $gradeable = $this->tryGetGradeable($g_id, false);
                if ($gradeable === false) {
                    continue;
                }
                if ($gradeable->isVcs() && !$gradeable->isTeamAssignment()) {
                    AdminGradeableController::enqueueGenerateRepos($semester,$course,$g_id);
                }
            }

        }
        $this->core->redirect($return_url);
    }

    /**
     * @Route("/{_semester}/{_course}/sections", methods={"GET"})
     */
    public function sectionsForm() {
        $students = $this->core->getQueries()->getAllUsers();
        $reg_sections = $this->core->getQueries()->getRegistrationSections();
        $non_null_counts = $this->core->getQueries()->getCountUsersRotatingSections();

        //Adds "invisible" sections: rotating sections that exist but have no students assigned to them
        $sections_with_students = array();
        foreach ($non_null_counts as $rows) {
            array_push($sections_with_students,$rows['rotating_section']);
        }
        for ($i = 1; $i <= $this->core->getQueries()->getMaxRotatingSection(); $i++) {
            if ( !in_array($i,$sections_with_students) ) {
                array_push($non_null_counts,[
                    "rotating_section" => $i,
                    "count" => 0
                ]);
            }
        }

        $null_counts = $this->core->getQueries()->getCountNullUsersRotatingSections();
        $max_section = $this->core->getQueries()->getMaxRotatingSection();
        $this->core->getOutput()->renderOutput(array('admin', 'Users'), 'sectionsForm', $students, $reg_sections,
            $non_null_counts, $null_counts, $max_section);
    }

    /**
     * @Route("/{_semester}/{_course}/sections/registration", methods={"POST"})
     */
    public function updateRegistrationSections() {
        $return_url = $this->core->buildCourseUrl(['sections']);

        if (isset($_POST['add_reg_section']) && $_POST['add_reg_section'] !== "") {
            if (User::validateUserData('registration_section', $_POST['add_reg_section'])) {
                // SQL query's ON CONFLICT clause should resolve foreign key conflicts, so we are able to INSERT after successful validation.
                // $num_new_sections indicates how many new INSERTions were performed.  0 INSERTions means the reg section given on the form is a duplicate.
                $num_new_sections = $this->core->getQueries()->insertNewRegistrationSection($_POST['add_reg_section']);
                if ($num_new_sections === 0) {
                    $this->core->addErrorMessage("Registration Section {$_POST['add_reg_section']} already present");
                }
                else {
                    $this->core->addSuccessMessage("Registration section {$_POST['add_reg_section']} added");
                }
            }
            else {
                $this->core->addErrorMessage("Registration Section entered does not follow the specified format");
                $_SESSION['request'] = $_POST;
            }
        }
        else if (isset($_POST['delete_reg_section']) && $_POST['delete_reg_section'] !== "") {
            if (User::validateUserData('registration_section', $_POST['delete_reg_section'])) {
                // DELETE trigger function in master DB will catch integrity violation exceptions (such as FK violations when users/graders are still enrolled in section).
                // $num_del_sections indicates how many DELETEs were performed.  0 DELETEs means either the section didn't exist or there are users still enrolled.
                $num_del_sections = $this->core->getQueries()->deleteRegistrationSection($_POST['delete_reg_section']);
                if ($num_del_sections === 0) {
                    $this->core->addErrorMessage("Section {$_POST['delete_reg_section']} not removed.  Section must exist and be empty of all users/graders.");
                }
                else {
                    $this->core->addSuccessMessage("Registration section {$_POST['delete_reg_section']} removed.");
                }
            }
            else {
                $this->core->addErrorMessage("Registration Section entered does not follow the specified format");
                $_SESSION['request'] = $_POST;
            }
        }

        $this->core->redirect($return_url);
    }

    /**
     * @Route("/{_semester}/{_course}/sections/rotating", methods={"POST"})
     */
    public function updateRotatingSections() {
        $return_url = $this->core->buildCourseUrl(['sections']);

        if (!isset($_POST['sort_type'])) {
            $this->core->addErrorMessage("Must select one of the four options for setting up rotating sections");
            $this->core->redirect($return_url);
        }
        else if ($_POST['sort_type'] === "drop_null") {
            $this->core->getQueries()->setNonRegisteredUsersRotatingSectionNull();
            $this->core->addSuccessMessage("Non registered students removed from rotating sections");
            $this->core->redirect($return_url);
        }
        else if ($_POST['sort_type'] === "drop_all") {
            $this->core->getQueries()->setAllUsersRotatingSectionNull();
            $this->core->getQueries()->setAllTeamsRotatingSectionNull();
            $this->core->addSuccessMessage("All students removed from rotating sections");
            $this->core->redirect($return_url);
        }

        if (isset($_POST['rotating_type']) && in_array($_POST['rotating_type'], array('random', 'alphabetically'))) {
            $type = $_POST['rotating_type'];
        }
        else {
            $type = 'random';
        }

        $section_count = intval($_POST['sections']);
        if ($section_count < 1) {
            $this->core->addErrorMessage("You must have at least one rotating section");
            $this->core->redirect($return_url);
        }

        if (in_array($_POST['sort_type'], array('redo', 'fewest')) && $type == "random") {
            $sort = $_POST['sort_type'];
        }
        else {
            $sort = 'redo';
        }

        $section_counts = array_fill(0, $section_count, 0);
        $team_section_counts = [];
        if ($sort === 'redo') {
            $users = $this->core->getQueries()->getRegisteredUserIds();
            $teams = $this->core->getQueries()->getTeamIdsAllGradeables();
            $users_with_reg_section = $this->core->getQueries()->getAllUsers();

            $exclude_sections = [];
            $reg_sections = $this->core->getQueries()->getRegistrationSections();
            foreach ($reg_sections as $row) {
                $test = $row['sections_registration_id'];
                if (isset($_POST[$test])) {
                    array_push($exclude_sections,$_POST[$row['sections_registration_id']]);
                }
            }
            //remove people who should not be added to rotating sections
            for ($j = 0;$j < count($users_with_reg_section);) {
                for ($i = 0;$i < count($exclude_sections);++$i) {
                    if ($users_with_reg_section[$j]->getRegistrationSection() == $exclude_sections[$i]) {
                        array_splice($users_with_reg_section,$j,1);
                        $j--;
                        break;
                    }
                }
                ++$j;

            }
            for ($i = 0;$i < count($users);) {
                $found_in = false;
                for ($j = 0;$j < count($users_with_reg_section);++$j) {
                    if ($users[$i] == $users_with_reg_section[$j]->getId()) {
                        $found_in = true;
                        break;
                    }
                }
                if (!$found_in) {
                    array_splice($users,$i,1);
                    continue;
                }
                ++$i;
            }
            if ($type === 'random') {
                shuffle($users);
                foreach ($teams as $g_id => $team_ids) {
                    shuffle($teams[$g_id]);
                }
            }
            $this->core->getQueries()->setAllUsersRotatingSectionNull();
            $this->core->getQueries()->setAllTeamsRotatingSectionNull();
            $this->core->getQueries()->deleteAllRotatingSections();
            for ($i = 1; $i <= $section_count; $i++) {
                $this->core->getQueries()->insertNewRotatingSection($i);
            }

            for ($i = 0; $i < count($users); $i++) {
                $section = $i % $section_count;
                $section_counts[$section]++;
            }
            foreach ($teams as $g_id => $team_ids) {
                for ($i = 0; $i < count($team_ids); $i ++) {
                    $section = $i % $section_count;

                    if (!array_key_exists($g_id, $team_section_counts)) {
                        $team_section_counts[$g_id] = array_fill(0, $section_count, 0);
                    }

                    $team_section_counts[$g_id][$section]++;
                }
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
            $teams = $this->core->getQueries()->getTeamIdsWithNullRotating();
            // only random sort can use 'fewest' type
            shuffle($users);
            foreach ($teams as $g_id => $team_ids) {
                shuffle($teams[$g_id]);
            }
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
            foreach ($teams as $g_id => $team_ids) {
                for ($i = 0; $i < count($team_ids); $i ++) {
                    $use_section = ($use_section + 1) % $section_count;

                    if (!array_key_exists($g_id, $team_section_counts)) {
                        $team_section_counts[$g_id] = array_fill(0, $section_count, 0);
                    }

                    $team_section_counts[$g_id][$use_section]++;
                }
            }
        }

        for ($i = 0; $i < $section_count; $i++) {
            $update_users = array_splice($users, 0, $section_counts[$i]);
            if (count($update_users) == 0) {
                continue;
            }
            $this->core->getQueries()->updateUsersRotatingSection($i + 1, $update_users);
        }

        foreach ($team_section_counts as $g_id => $counts) {
            for ($i = 0; $i < $section_count; $i ++) {
                $update_teams = array_splice($teams[$g_id], 0, $team_section_counts[$g_id][$i]);

                foreach ($update_teams as $team_id) {
                    $this->core->getQueries()->updateTeamRotatingSection($team_id, $i + 1);
                }
            }
        }

        $this->core->addSuccessMessage("Rotating sections setup");
        $this->core->redirect($return_url);
    }

    /**
     * Parse uploaded users data file as either XLSX or CSV, and return its data
     *
     * @param string $filename  Original name of uploaded file
     * @param string $tmp_name  PHP assigned unique name and path of uploaded file
     * @param string $return_url
     *
     * @return array $contents  Data rows and columns read from xlsx or csv file
     */
    private function getUserDataFromUpload($filename, $tmp_name, $return_url) {
        // Data is confidential, and therefore must be deleted immediately after
        // this process ends, regardless if process completes successfully or not.
        register_shutdown_function(
            function() use (&$csv_file, &$xlsx_file) {
                foreach (array($csv_file, $xlsx_file) as $file) {
                    if (isset($file) && file_exists($file)) {
                        unlink($file);
                    }
                }
            }
        );

        $content_type = FileUtils::getContentType($filename);
        $mime_type = mime_content_type($tmp_name);

        // If an XLSX spreadsheet is uploaded.
        if ($content_type === 'spreadsheet/xlsx' && $mime_type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            // Declare tmp file paths with unique file names.
            $csv_file = FileUtils::joinPaths($this->core->getConfig()->getCgiTmpPath(), uniqid("", true));
            $xlsx_file = FileUtils::joinPaths($this->core->getConfig()->getCgiTmpPath(), uniqid("", true));

            // This is to create tmp files and set permissions to RW-RW----
            // chmod() is disabled by security policy, so we are using umask().
            $old_umask = umask(0117);
            file_put_contents($csv_file, "");
            $did_move = move_uploaded_file($tmp_name, $xlsx_file);
            umask($old_umask);

            if ($did_move) {
                // exec() and similar functions are disabled by security policy,
                // so we are using a python script via CGI to invoke external program 'xlsx2csv'
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
                $this->core->addErrorMessage("Did not properly recieve spreadsheet. Contact your sysadmin.");
                $this->core->redirect($return_url);
            }

        } else if ($content_type === 'text/csv' && $mime_type === 'text/plain') {
            $csv_file = $tmp_name;
        } else {
            $this->core->addErrorMessage("Must upload xlsx or csv");
            $this->core->redirect($return_url);
        }

        // Parse user data (should be a CSV file either uploaded or converted from XLSX).
        // First, set environment config to allow '\r' EOL encoding. (Used by Microsoft Excel on Macintosh)
        ini_set("auto_detect_line_endings", true);

        // Read csv file as an entire string.
        $user_data = file_get_contents($csv_file);

        // Make sure read was successful.
        if ($user_data === false) {
            $this->core->addErrorMessage("File was not properly uploaded. Contact your sysadmin.");
            $this->core->redirect($return_url);
        }

        // Remove UTF-8 BOM, if it exists.  Otherwise, it will cause a validation error on row 1, $val[0].
        // First, define BOM (byte order mark).  Always hexadecimal EFBBBF.
        // (note that EFBBBF is not printable)
        $bom = hex2bin('EFBBBF');

        // Remove BOM from $user_data.
        $user_data = preg_replace("~^{$bom}~", "", $user_data);

        // convert csv data string to array of $user_data[$rows][$vals].
        $user_data = array_map('str_getcsv', str_getcsv($user_data, PHP_EOL));

        // Remove all empty-data rows.  This should preserve original row number
        // ordering, so error messaging refers to appropriately labeled row(s)
        // in the spreadsheet / csv upload.
        $user_data = array_filter($user_data, function($row) {
            return !empty(array_filter($row, function($val) {
                return !empty($val);
            }));
        });

        //Apply trim() to all data values.
        array_walk_recursive($user_data, function(&$val) {
            $val = trim($val);
        });

        return $user_data;
    }

    /**
     * Upload user list data to database
     *
     * @param string $list_type "classlist" or "graderlist"
     * @Route("/{_semester}/{_course}/users/upload", methods={"POST"})
     */
    public function uploadUserList($list_type = "classlist") {
        // A few places have different behaviors depending on $list_type.
        // These closure functions will help control those few times when
        // $list_type dictates behavior.

        /**
         * Closure to validate $vals[4] depending on $list_type
         *
         * @return boolean true on successful validation, false otherwise.
         */
        $row4_validation_function = function() use ($list_type, &$vals) {
            //$row[4] is different based on classlist vs graderlist
            switch($list_type) {
            case "classlist":
                //student
                if (isset($vals[4]) && strtolower($vals[4]) === "null") {
                    $vals[4] = null;
                }
                //Check registration for appropriate format. Allowed characters - A-Z,a-z,_,-
                return User::validateUserData('registration_section', $vals[4]);
            case "graderlist":
                //grader
                if (isset($vals[4]) && is_numeric($vals[4])) {
                    $vals[4] = intval($vals[4]); //change float read from xlsx to int
                }
                //grader-level check is a digit between 1 - 4.
                return User::validateUserData('user_group', $vals[4]);
            default:
                throw new ValidationException("Unknown classlist", array($list_type, '$row4_validation_function'));
            }
        };

        /**
         * Closure to get (as return) either user's registration_section or group_id, based on $list-type
         *
         * @return string
         */
        $get_user_registration_or_group_function = function($user) use ($list_type) {
            switch($list_type) {
            case "classlist":
                return $user->getRegistrationSection();
            case "graderlist":
                return (string)$user->getGroup();
            default:
                throw new ValidationException("Unknown classlist", array($list_type, '$get_user_registration_or_group_function'));
            }
        };

        /**
         * Closure to set a user's registration_section or group_id based on $list_type)
         */
        $set_user_registration_or_group_function = function(&$user) use ($list_type, &$row) {
            switch($list_type) {
            case "classlist":
                // Registration section has to exist, or a DB exception gets thrown on INSERT or UPDATE.
                // ON CONFLICT clause in DB query prevents thrown exceptions when registration section already exists.
                $this->core->getQueries()->insertNewRegistrationSection($row[4]);
                $user->setRegistrationSection($row[4]);
                $user->setGroup(4);
                break;
            case "graderlist":
                $user->setGroup($row[4]);
                break;
            default:
                throw new ValidationException("Unknown classlist", array($list_type, '$set_user_registration_or_group_function'));
            }
        };

        /**
         * Closure to INSERT or UPDATE user data based on $action
         *
         * @param string $action "insert" or "update"
         */
        $insert_or_update_user_function = function($action, $user) use (&$semester, &$course, &$uploaded_data, &$return_url) {
            try {
                switch($action) {
                case 'insert':
                    //User must first exist in Submitty before being enrolled to a course.
                    //$uploaded_data[0] = authentication ID.
                    if (is_null($this->core->getQueries()->getSubmittyUser($uploaded_data[0]))) {
                        $this->core->getQueries()->insertSubmittyUser($user);
                    }
                    $this->core->getQueries()->insertCourseUser($user, $semester, $course);
                    break;
                case 'update':
                    $this->core->getQueries()->updateUser($user, $semester, $course);
                    break;
                default:
                    throw new ValidationException("Unknown DB operation", array($action, '$insert_or_update_user_function'));
                    break;
                }
            }
            catch (DatabaseException $e) {
                $this->core->addErrorMessage("Database Exception.  Please contact your sysadmin.");
                $this->core->redirect($return_url);
            }
        };

        /**
         * Closure to determine $return_url action ("students" or "graders").
         *
         * @return string
         */
        $set_return_url_action_function = function() use ($list_type) {
            switch($list_type) {
            case "classlist":
                return "users";
            case "graderlist":
                return "graders";
            default:
                throw new ValidationException("Unknown classlist", array($list_type, '$set_return_url_action_function'));
            }
        };

        $return_url = $this->core->buildCourseUrl([$set_return_url_action_function()]);
        $use_database = $this->core->getAuthentication() instanceof DatabaseAuthentication;

        if ($_FILES['upload']['name'] == "") {
            $this->core->addErrorMessage("No input file specified");
            $this->core->redirect($return_url);
        }

        $uploaded_data = $this->getUserDataFromUpload($_FILES['upload']['name'], $_FILES['upload']['tmp_name'], $return_url);

        // Validation and error checking.
        $pref_firstname_idx = $use_database ? 6 : 5;
        $pref_lastname_idx = $pref_firstname_idx + 1;
        $bad_rows = array();
        foreach($uploaded_data as $row_num => $vals) {
            // Blacklist validation.  Validation fails if any test resolves as false.
            switch(false) {
            // Bounds check to ensure minimum required number of rows is present.
            case count($vals) >= 5:
            // Username must contain only lowercase alpha, numbers, underscores, hyphens
            case User::validateUserData('user_id', $vals[0]):
            // First and Last name must be alpha characters, white-space, or certain punctuation.
            case User::validateUserData('user_legal_firstname', $vals[1]):
            case User::validateUserData('user_legal_lastname', $vals[2]):
            // Check email address for appropriate format. e.g. "student@university.edu", "student@cs.university.edu", etc.
            case User::validateUserData('user_email', $vals[3]):
            // $row[4] validation varies by $list_type
            // "classlist" validates registration_section, and "graderlist" validates user_group
            case $row4_validation_function():
            // Database password cannot be blank, no check on format.
            // Automatically validate if NOT using database authentication (e.g. using PAM authentication).
            case !$use_database || User::validateUserData('user_password', $row[5]):
            // Preferred first and last name must be alpha characters, white-space, or certain punctuation.
            // Automatically validate if not set (this field is optional).
            case !isset($vals[$pref_firstname_idx]) || User::validateUserData('user_preferred_firstname', $vals[$pref_firstname_idx]):
            case !isset($vals[$pref_lastname_idx])  || User::validateUserData('user_preferred_lastname',  $vals[$pref_lastname_idx] ):
                // Validation failed somewhere.  Record which row failed.
                // $row_num is zero based.  ($row_num+1) will better match spreadsheet labeling.
                $bad_rows[] = ($row_num+1);
                break;
            }
        }

        // $bad_rows will contain rows with errors.  No errors to report when empty.
        if (!empty($bad_rows)) {
            $msg = "Error(s) on row(s) ";
            array_walk($bad_rows, function($row_num) use (&$msg) {
                $msg .= " {$row_num}";
            });
            $this->core->addErrorMessage($msg);
            $this->core->redirect($return_url);
        }

        // Isolate existing users ($users_to_update[]) and new users ($users_to_add[])
        $existing_users = $this->core->getQueries()->getAllUsers();
        $users_to_add = array();
        $users_to_update = array();
        foreach($uploaded_data as $row) {
            $exists = false;
            foreach($existing_users as $i => $existing_user) {
                if ($row[0] === $existing_user->getId()) {
                    // Validate if this user has any data to update.
                    // Did student registration section or grader group change?
                    if ($row[4] !== $get_user_registration_or_group_function($existing_user)) {
                        $users_to_update[] = $row;
                    }
                    $exists = true;
                    //Unset this existing user.
                    //Those that remain in this list are candidates to be moved to NULL reg section, later.
                    unset($existing_users[$i]);
                    break;
                }
            }
            if (!$exists) {
                $users_to_add[] = $row;
            }
        }

        // Insert new students to database
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        foreach($users_to_add as $uploaded_data) {
            $user = new User($this->core);
            $user->setId($uploaded_data[0]);
            $user->setLegalFirstName($uploaded_data[1]);
            $user->setLegalLastName($uploaded_data[2]);
            $user->setEmail($uploaded_data[3]);
            $set_user_registration_or_group_function($user);
            if (isset($uploaded_data[$pref_firstname_idx]) && !empty($uploaded_data[$pref_firstname_idx])) {
                $user->setPreferredFirstName($uploaded_data[$pref_firstname_idx]);
            }
            if (isset($uploaded_data[$pref_lastname_idx]) && !empty($uploaded_data[$pref_lastname_idx])) {
                $user->setPreferredLastName($uploaded_data[$pref_lastname_idx]);
            }
            if ($use_database) {
                $user->setPassword($uploaded_data[5]);
            }
            $insert_or_update_user_function('insert', $user);
        }

        // Existing users update
        foreach($users_to_update as $row) {
            $user = $this->core->getQueries()->getUserById($row[0]);
            //Update registration section (student) or group (grader)
            $set_user_registration_or_group_function($user);
            $insert_or_update_user_function('update', $user);
        }
        $added = count($users_to_add);
        $updated = count($users_to_update);

        //Special case to move students to the NULL section with a classlist upload.
        if ($list_type === "classlist" && isset($_POST['move_missing'])) {
            foreach($existing_users as $existing_user) {
                if (!is_null($existing_user->getRegistrationSection())) {
                    $existing_user->setRegistrationSection(null);
                    $insert_or_update_user_function('update', $existing_user);
                    $updated++;
                }
            }
        }

        $this->core->addSuccessMessage("Uploaded {$_FILES['upload']['name']}: ({$added} added, {$updated} updated)");
        $this->core->redirect($return_url);
    }
}
