<?php

namespace app\controllers\admin;

use app\authentication\DatabaseAuthentication;
use app\authentication\SamlAuthentication;
use app\controllers\AbstractController;
use app\controllers\admin\AdminGradeableController;
use app\controllers\course\CourseRegistrationController;
use app\libraries\FileUtils;
use app\libraries\response\JsonResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\RedirectResponse;
use app\models\User;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;
//Enable us to throw, catch, and handle exceptions as needed.
use app\exceptions\ValidationException;
use app\exceptions\DatabaseException;
use app\controllers\SelfRejoinController;

/**
 * Class UsersController
 * @package app\controllers\admin
 */
#[AccessControl(role: "INSTRUCTOR")]
class UsersController extends AbstractController {
    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/users", methods: ["GET"])]
    #[Route("/api/courses/{_semester}/{_course}/users", methods: ["GET"])]
    public function getStudents() {
        $students = $this->core->getQueries()->getAllUsers();
        //Assemble students into sections
        $sorted_students = [];
        $download_info = [];
        $formatted_tzs = [];
        foreach ($students as $student) {
            $rot_sec = ($student->getRotatingSection() === null) ? 'NULL' : $student->getRotatingSection();
            $reg_sec = ($student->getRegistrationSection() === null) ? 'NULL' : $student->getRegistrationSection();
            $formatted_tzs[$student->getId()] = $student->getNiceFormatTimeZone() === 'NOT SET' ? 'NOT SET' : $student->getUTCOffset() . ' ' . $student->getTimeZone();
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
                'given_name' => $student->getDisplayedGivenName(),
                'family_name' => $student->getDisplayedFamilyName(),
                'pronouns' => $student->getPronouns(),
                'display_pronouns' => $student->getDisplayPronouns(),
                'user_id' => $student->getId(),
                'email' => $student->getEmail(),
                'secondary_email' => $student->getSecondaryEmail(),
                'utc_offset' => $student->getUTCOffset(),
                'time_zone' => $student->getNiceFormatTimeZone(),
                'reg_section' => $reg_sec,
                'rot_section' => $rot_sec,
                'group' => $grp
            ]);
        }

        //Get Active student Columns
        $active_student_columns = '';
        //Second argument in if statement checks if cookie has correct # of columns (to clear outdated lengths)
        if (isset($_COOKIE['active_student_columns']) && count(explode('-', $_COOKIE['active_student_columns'])) == 17) {
            $active_student_columns = $_COOKIE['active_student_columns'];
        }
        else {
            //Expires 10 years from today (functionally indefinite)
            if (setcookie('active_student_columns', implode('-', array_merge(array_fill(0, 12, true), array_fill(0, 5, false))), time() + (10 * 365 * 24 * 60 * 60))) {
                $active_student_columns = implode('-', array_merge(array_fill(0, 12, true), array_fill(0, 5, false)));
            }
        }

        $can_rejoin = [];
        $self_rejoin_tester = new SelfRejoinController($this->core);
        $course = $this->core->getConfig()->getCourse();
        $term = $this->core->getConfig()->getTerm();
        foreach ($sorted_students['NULL'] as $student) {
            $user_id = $student->getId();
            if (
                $user_id !== null
                && $student->getGroup() === User::GROUP_STUDENT
                && $self_rejoin_tester->canRejoinCourseHelper($student, $course, $term)
            ) {
                $can_rejoin[$user_id] = true;
            }
            else {
                $can_rejoin[$user_id] = false;
            }
        }

        return new MultiResponse(
            JsonResponse::getSuccessResponse($download_info),
            new WebResponse(
                ['admin', 'Users'],
                'listStudents',
                $sorted_students,
                $this->core->getQueries()->getRegistrationSections(),
                $this->core->getQueries()->getRotatingSections(),
                $can_rejoin,
                $download_info,
                $formatted_tzs,
                $this->core->getAuthentication() instanceof DatabaseAuthentication,
                $active_student_columns
            )
        );
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/graders", methods: ["GET"])]
    #[Route("/api/courses/{_semester}/{_course}/graders", methods: ["GET"])]
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
                'given_name' => $grader->getDisplayedGivenName(),
                'family_name' => $grader->getDisplayedFamilyName(),
                'pronouns' => $grader->getPronouns(),
                'display_pronouns' => $grader->getDisplayPronouns(),
                'user_id' => $grader->getId(),
                'email' => $grader->getEmail(),
                'secondary_email' => $grader->getSecondaryEmail(),
                'reg_section' => $reg_sec,
                'rot_section' => $rot_sec,
                'group' => $grp
            ]);
        }

        //Get Active grader Columns
        $active_grader_columns = '';
        //Second argument in if statement checks if cookie has correct # of columns (to clear outdated lengths)
        if (isset($_COOKIE['active_grader_columns']) && count(explode('-', $_COOKIE['active_grader_columns'])) == 7) {
            $active_grader_columns = $_COOKIE['active_grader_columns'];
        }
        else {
            //Expires 10 years from today (functionally indefinite)
            if (setcookie('active_grader_columns', implode('-', array_fill(0, 7, true)), time() + (10 * 365 * 24 * 60 * 60))) {
                $active_grader_columns = implode('-', array_fill(0, 7, true));
            }
        }

        return new MultiResponse(
            JsonResponse::getSuccessResponse($download_info),
            new WebResponse(
                ['admin', 'Users'],
                'listGraders',
                $graders_sorted,
                $this->core->getQueries()->getRegistrationSections(),
                $this->core->getQueries()->getRotatingSections(),
                $download_info,
                $this->core->getAuthentication() instanceof DatabaseAuthentication,
                $active_grader_columns
            )
        );
    }
    #[Route("/courses/{_semester}/{_course}/graders/assign_registration_sections", methods: ["POST"])]
    public function reassignRegistrationSections() {
        $return_url = $this->core->buildCourseUrl(['graders']);
        $new_registration_information = [];

        foreach ($_POST as $key => $value) {
            $key_array = explode("_", $key, 2);
            if (!array_key_exists($key_array[0], $new_registration_information)) {
                $new_registration_information[$key_array[0]] = [];
            }
            if ($key_array[1] != 'all') {
                $new_registration_information[$key_array[0]][] = $key_array[1];
            }
        }

        foreach ($this->core->getQueries()->getAllGraders() as $grader) {
            $grader_id = $grader->getId();
            if (array_key_exists($grader_id, $new_registration_information)) {
                $grader->setGradingRegistrationSections($new_registration_information[$grader_id]);
            }
            else {
                $grader->setGradingRegistrationSections([]);
            }
            $this->core->getQueries()->updateUser($grader, $this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse());
        }

        $this->core->redirect($return_url);
    }

    #[Route("/courses/{_semester}/{_course}/users/details", methods: ["GET"])]
    public function ajaxGetUserDetails($user_id) {
        $user = $this->core->getQueries()->getUserById($user_id);
        $this->core->getOutput()->renderJsonSuccess([
            'user_id' => $user->getId(),
            'already_in_course' => true,
            'user_numeric_id' => $user->getNumericId(),
            'user_givenname' => $user->getLegalGivenName(),
            'user_familyname' => $user->getLegalFamilyName(),
            'user_preferred_givenname' => $user->getPreferredGivenName(),
            'user_preferred_familyname' => $user->getPreferredFamilyName(),
            'user_pronouns' => $user->getPronouns(),
            'user_display_pronouns' => $user->getDisplayPronouns(),
            'user_email' => $user->getEmail(),
            'user_email_secondary' => $user->getSecondaryEmail(),
            'user_group' => $user->getGroup(),
            'registration_section' => $user->getRegistrationSection(),
            'course_section_id' => $user->getCourseSectionId(),
            'rotating_section' => $user->getRotatingSection(),
            'user_updated' => $user->isUserUpdated(),
            'instructor_updated' => $user->isInstructorUpdated(),
            'manual_registration' => $user->isManualRegistration(),
            'grading_registration_sections' => $user->getGradingRegistrationSections(),
            'registration_type' => $user->getRegistrationType(),
        ]);
    }

    #[Route("/courses/{_semester}/{_course}/user_information", methods: ["GET"])]
    public function ajaxGetSubmittyUsers() {
        $submitty_users = $this->core->getQueries()->getAllSubmittyUsers();
        $user_ids = array_keys($submitty_users);
        $course_users = $this->core->getQueries()->getUsersById($user_ids);

        //uses more thorough course information if it exists, if not uses database information
        $user_information = [];
        foreach ($user_ids as $user_id) {
            $already_in_course = array_key_exists($user_id, $course_users);
            $user = $already_in_course ? $course_users[$user_id] : $submitty_users[$user_id];
            $user_information[$user_id] = [
                'already_in_course' => $already_in_course,
                'user_numeric_id' => $user->getNumericId(),
                'user_givenname' => $user->getLegalGivenName(),
                'user_familyname' => $user->getLegalFamilyName(),
                'user_preferred_givenname' => $user->getPreferredGivenName() ?? '',
                'user_preferred_familyname' => $user->getPreferredFamilyName() ?? '',
                'user_pronouns' => $user->getPronouns() ?? '',
                'display_pronoun' => $user->getDisplayPronouns(),
                'user_email' => $user->getEmail(),
                'user_email_secondary' => $user->getSecondaryEmail(),
                'user_group' => $user->getGroup(),
                'registration_section' => $user->getRegistrationSection(),
                'course_section_id' => $user->getCourseSectionId(),
                'rotating_section' => $user->getRotatingSection(),
                'user_updated' => $user->isUserUpdated(),
                'instructor_updated' => $user->isInstructorUpdated(),
                'manual_registration' => $user->isManualRegistration(),
                'grading_registration_sections' => $user->getGradingRegistrationSections()
            ];
        }
        $this->core->getOutput()->renderJsonSuccess($user_information);
    }

    #[Route("/courses/{_semester}/{_course}/users", methods: ["POST"])]
    public function updateUser($type = 'users') {
        $return_url = $this->core->buildCourseUrl([$type]) . '#user-' . $_POST['user_id'];
        $authentication = $this->core->getAuthentication();
        $use_database = $authentication instanceof DatabaseAuthentication;
        $_POST['user_id'] = trim($_POST['user_id']);
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        if (empty($_POST['user_id'])) {
            $this->core->addErrorMessage("User ID cannot be empty");
        }

        $user = $this->core->getQueries()->getUserById($_POST['user_id']);

        $error_message = "";
        //Username must contain only lowercase alpha, numbers, underscores, hyphens
        $error_message .= User::validateUserData('user_id', trim($_POST['user_id'])) ? "" : "Error in username: \"" . strip_tags($_POST['user_id']) . "\"<br>";
        if ($user === null && $authentication instanceof SamlAuthentication) {
            $authentication->setValidUsernames([$_POST['user_id']]);
            if ($authentication->isInvalidUsername($_POST['user_id'])) {
                $error_message .= "User ID must be a valid SAML username.\n";
            }
        }
        //Pronouns must be less than 12 characters.
        $error_message .= User::validateUserData('user_pronouns', trim($_POST['user_pronouns'])) ? "" : "Error in pronouns: \"" . strip_tags($_POST['user_pronouns']) . "\"<br>";
        //Given and Family name must be alpha characters, white-space, or certain punctuation.
        $error_message .= User::validateUserData('user_legal_givenname', trim($_POST['user_givenname'])) ? "" : "Error in first name: \"" . strip_tags($_POST['user_givenname']) . "\"<br>";
        $error_message .= User::validateUserData('user_legal_familyname', trim($_POST['user_familyname'])) ? "" : "Error in last name: \"" . strip_tags($_POST['user_familyname']) . "\"<br>";
        //Check email address for appropriate format. e.g. "user@university.edu", "user@cs.university.edu", etc.
        $error_message .= User::validateUserData('user_email', trim($_POST['user_email'])) ? "" : "Error in email: \"" . strip_tags($_POST['user_email']) . "\"<br>";
        //Check secondary email address for appropriate format.
        $error_message .= User::validateUserData('user_email_secondary', trim($_POST['user_email_secondary'])) ? "" : "Error in secondary email: \"" . strip_tags($_POST['user_email_secondary']) . "\"<br>";
        //Preferred given name must be alpha characters, white-space, or certain punctuation.
        if (!empty($_POST['user_preferred_givenname']) && trim($_POST['user_preferred_givenname']) !== "") {
            $error_message .= User::validateUserData('user_preferred_givenname', trim($_POST['user_preferred_givenname'])) ? "" : "Error in preferred first name: \"" . strip_tags($_POST['user_preferred_givenname']) . "\"<br>";
        }
        if (!empty($_POST['user_preferred_familyname']) && trim($_POST['user_preferred_familyname']) !== "") {
            $error_message .= User::validateUserData('user_preferred_familyname', trim($_POST['user_preferred_familyname'])) ? "" : "Error in preferred last name: \"" . strip_tags($_POST['user_preferred_familyname']) . "\"<br>";
        }

        //Database password cannot be blank, no check on format
        if ($use_database && (($_POST['edit_user'] == 'true' && !empty($_POST['user_password'])) || $_POST['edit_user'] != 'true')) {
            $error_message .= User::validateUserData('user_password', $_POST['user_password']) ? "" : "Error must enter password for user<br>";
        }

        if (!empty($error_message)) {
            $this->core->addErrorMessage($error_message . " Contact your sysadmin if this should not cause an error.");
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

        $user->setLegalGivenName(trim($_POST['user_givenname']));
        if (isset($_POST['user_preferred_givenname']) && trim($_POST['user_preferred_givenname']) !== "") {
            $user->setPreferredGivenName(trim($_POST['user_preferred_givenname']));
        }

        $user->setLegalFamilyName(trim($_POST['user_familyname']));
        if (isset($_POST['user_preferred_familyname']) && trim($_POST['user_preferred_familyname']) !== "") {
            $user->setPreferredFamilyName(trim($_POST['user_preferred_familyname']));
        }

        $user->setPronouns(trim($_POST['user_pronouns']));

        $user->setDisplayPronouns($_POST['display_pronouns']);

        $user->setEmail(trim($_POST['user_email']));

        $user->setSecondaryEmail(trim($_POST['user_email_secondary']));

        if (!empty($_POST['user_password'])) {
            $user->setPassword($_POST['user_password']);
        }

        if ($_POST['registered_section'] === "null") {
            $user->setRegistrationSection(null);
        }
        else {
            $user->setRegistrationSection($_POST['registered_section']);
        }

        if (isset($_POST['registration_subsection'])) {
            $user->setRegistrationSubsection(trim($_POST['registration_subsection']));
        }

        if ($_POST['rotating_section'] == "null") {
            $user->setRotatingSection(null);
        }
        else {
            $user->setRotatingSection(intval($_POST['rotating_section']));
        }

        $user->setGroup(intval($_POST['user_group']));
        $user->setRegistrationType(intval($_POST['user_group']) == 4 ? $_POST['registration_type'] : 'staff');
        //Instructor updated flag tells auto feed to not clobber some of the users data.
        $user->setInstructorUpdated(true);
        $user->setManualRegistration(isset($_POST['manual_registration']));
        if (isset($_POST['grading_registration_section'])) {
            $user->setGradingRegistrationSections($_POST['grading_registration_section']);
        }
        else {
            $user->setGradingRegistrationSections([]);
        }

        if ($_POST['edit_user'] == "true") {
            $this->core->getQueries()->updateUser($user, $semester, $course);
            $this->core->addSuccessMessage("User '{$user->getId()}' updated");
        }
        else {
            $submitty_user = $this->core->getQueries()->getSubmittyUser($_POST['user_id']);
            if ($submitty_user === null) {
                $this->core->getQueries()->insertSubmittyUser($user);
                if ($authentication instanceof SamlAuthentication) {
                    $this->core->getQueries()->insertSamlMapping($_POST['user_id'], $_POST['user_id']);
                }
                $this->core->addSuccessMessage("Added a new user {$user->getId()} to Submitty");
                $this->core->getQueries()->insertCourseUser($user, $semester, $course);
                CourseRegistrationController::applyDefaultNotificationSettings($this->core, $user->getId());
                $this->core->addSuccessMessage("New Submitty user '{$user->getId()}' added");
            }
            else {
                $user->setEmailBoth($submitty_user->getEmailBoth());
                $this->core->getQueries()->updateUser($user);
                $this->core->getQueries()->insertCourseUser($user, $this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse());
                CourseRegistrationController::applyDefaultNotificationSettings($this->core, $user->getId());
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
                    AdminGradeableController::enqueueGenerateRepos($semester, $course, $g_id, $gradeable->getVcsSubdirectory());
                }
            }
        }
        $this->core->redirect($return_url);
    }

    /**
     * @return RedirectResponse
     */
    #[Route("/courses/{_semester}/{_course}/delete_user", methods: ["POST"])]
    public function deleteUser(): RedirectResponse {
        if (isset($_POST['user_id']) && isset($_POST['displayed_fullname'])) {
            $user_id = trim($_POST['user_id']);
            $displayed_fullname = trim($_POST['displayed_fullname']);
            $semester = $this->core->getConfig()->getTerm();
            $course = $this->core->getConfig()->getCourse();

            if ($user_id === $this->core->getUser()->getId()) {
                $this->core->addErrorMessage('You cannot delete yourself.');
            }
            elseif ($this->core->getQueries()->deleteUser($user_id, $semester, $course)) {
                $this->core->addSuccessMessage("{$displayed_fullname} has been removed from your course.");
            }
            else {
                $this->core->addErrorMessage("Could not remove {$displayed_fullname}.  They may have recorded activity in your course.");
            }
        }
        else {
            $this->core->addErrorMessage('User ID or name is not set.');
        }

        return new RedirectResponse($this->core->buildCourseUrl(['users']));
    }

    /**
     * @return RedirectResponse
     */
    #[Route("/courses/{_semester}/{_course}/demote_grader", methods: ["POST"])]
    public function demoteGrader(): RedirectResponse {
        if (isset($_POST['user_id']) && isset($_POST['displayed_fullname'])) {
            $user_id = trim($_POST['user_id']);
            $displayed_fullname = trim($_POST['displayed_fullname']);
            $semester = $this->core->getConfig()->getTerm();
            $course = $this->core->getConfig()->getCourse();

            if ($user_id === $this->core->getUser()->getId()) {
                $this->core->addErrorMessage('You cannot demote yourself.');
            }
            elseif ($this->core->getQueries()->demoteGrader($user_id, $semester, $course)) {
                $this->core->addSuccessMessage("{$displayed_fullname} has been demoted to a student.");
            }
            else {
                $this->core->addErrorMessage("Failed to demote {$displayed_fullname}.");
            }
        }
        else {
            $this->core->addErrorMessage('User ID or name is not set.');
        }

        return new RedirectResponse($this->core->buildCourseUrl(['graders']));
    }

    #[Route("/courses/{_semester}/{_course}/sections", methods: ["GET"])]
    public function sectionsForm() {
        $students = $this->core->getQueries()->getAllUsers();
        $reg_sections = $this->core->getQueries()->getRegistrationSections();
        $non_null_counts = $this->core->getQueries()->getUsersCountByRotatingSections();

        //Adds "invisible" sections: rotating sections that exist but have no students assigned to them
        $sections_with_students = [];
        foreach ($non_null_counts as $rows) {
            array_push($sections_with_students, $rows['rotating_section']);
        }
        for ($i = 1; $i <= $this->core->getQueries()->getMaxRotatingSection(); $i++) {
            if (!in_array($i, $sections_with_students)) {
                array_push($non_null_counts, [
                    "rotating_section" => $i,
                    "count" => 0
                ]);
            }
        }
        $term = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        $null_counts = $this->core->getQueries()->getCountNullUsersRotatingSections();
        $max_section = $this->core->getQueries()->getMaxRotatingSection();
        $is_self_register = $this->core->getQueries()->getSelfRegistrationType($term, $course) !== ConfigurationController::NO_SELF_REGISTER;
        $default_section = $this->core->getQueries()->getDefaultRegistrationSection($term, $course);
        $this->core->getOutput()->renderOutput(
            ['admin', 'Users'],
            'sectionsForm',
            $students,
            $reg_sections,
            $non_null_counts,
            $null_counts,
            $max_section,
            $default_section,
            $is_self_register
        );
    }

    #[Route("/courses/{_semester}/{_course}/sections/registration", methods: ["POST"])]
    public function updateRegistrationSections() {
        $return_url = $this->core->buildCourseUrl(['sections']);
        $term = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        if (isset($_POST['default_section'])) {
            $this->core->getQueries()->setDefaultRegistrationSection($term, $course, $_POST['default_section']);
        }

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
        elseif (isset($_POST['delete_reg_section']) && $_POST['delete_reg_section'] !== "") {
            if (User::validateUserData('registration_section', $_POST['delete_reg_section'])) {
                // DELETE trigger function in master DB will catch integrity violation exceptions (such as FK violations when users/graders are still enrolled in section).
                // $num_del_sections indicates how many DELETEs were performed.  0 DELETEs means either the section didn't exist or there are users still enrolled.
                $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
                if (file_exists($fp)) {
                    $json = file_get_contents($fp);
                    $jsonArray = json_decode($json, true);
                    foreach ($jsonArray as $key => $value) {
                        if (isset($value['sections'])) {
                            $sections = $value['sections'];
                            if ($key = array_search($_POST['delete_reg_section'], $sections) !== false) {
                                $this->core->addErrorMessage("Section {$_POST['delete_reg_section']} not removed.  This section is referenced in course materials");
                                $this->core->redirect($return_url);
                            }
                        }
                    }
                }
                $default_section = $this->core->getQueries()->getDefaultRegistrationSection($term, $course);
                $is_self_register =  $this->core->getQueries()->getSelfRegistrationType($term, $course) !== ConfigurationController::NO_SELF_REGISTER;
                if ($default_section === $_POST['delete_reg_section'] && $is_self_register) {
                        $this->core->addErrorMessage("Section {$_POST['delete_reg_section']} not removed.  Cannot delete the default registration section if self registration is enabled.");
                }
                else {
                    $num_del_sections = $this->core->getQueries()->deleteRegistrationSection($_POST['delete_reg_section']);

                    if ($num_del_sections === 0) {
                        $this->core->addErrorMessage("Section {$_POST['delete_reg_section']} not removed.  Section must exist and be empty of all users/graders.");
                    }
                    else {
                        $this->core->addSuccessMessage("Registration section {$_POST['delete_reg_section']} removed.");
                    }
                }
            }
            else {
                $this->core->addErrorMessage("Registration Section entered does not follow the specified format");
                $_SESSION['request'] = $_POST;
            }
        }

        $this->core->redirect($return_url);
    }

    #[Route("/courses/{_semester}/{_course}/sections/rotating", methods: ["POST"])]
    public function updateRotatingSections() {
        $return_url = $this->core->buildCourseUrl(['sections']);

        if (!isset($_POST['rotating_assignment_type'])) {
            $this->core->addErrorMessage("Must select one of the four options for setting up rotating sections");
            $this->core->redirect($return_url);
        }
        elseif (!in_array($_POST['rotating_assignment_type'], ['drop_null', 'drop_all', 'redo', 'fewest'])) {
            $this->core->addErrorMessage("Invalid radio button option selected for setting up rotating sections");
            $this->core->redirect($return_url);
        }
        elseif ($_POST['rotating_assignment_type'] === "drop_null") {
            $this->core->getQueries()->setNonRegisteredUsersRotatingSectionNull();
            $this->core->addSuccessMessage("Unregistered students removed from rotating sections");
            $this->core->redirect($return_url);
        }
        elseif ($_POST['rotating_assignment_type'] === "drop_all") {
            $this->core->getQueries()->setAllUsersRotatingSectionNull();
            $this->core->getQueries()->setAllTeamsRotatingSectionNull();
            $this->core->addSuccessMessage("All students removed from rotating sections");
            $this->core->redirect($return_url);
        }
        if ($_POST['rotating_assignment_type'] === "redo") {
            $unassigned_user_ids = $this->core->getQueries()->getRegisteredUserIds();
            $unassigned_gradeable_teams = $this->core->getQueries()->getTeamIdsAllGradeables();
            // Find the number of rotating sections to create or update during section assignments
            $num_rotating_sections = intval($_POST['sections']);
            if ($num_rotating_sections < 1) {
                $this->core->addErrorMessage("Must specify a positive number of sections to redo rotating sections");
                $this->core->redirect($return_url);
            }
            // get all users' id's except those that are in excluded registration sections (from selected checkboxes)
            $excluded_registration_sections = $_POST["excluded_registration_sections"] ?? [];
            $excluded_users = $this->core->getQueries()->getUsersByRegistrationSections($excluded_registration_sections);
            $excluded_user_ids = array_map(function (User $user) {
                return $user->getId();
            }, $excluded_users);
            $unassigned_user_ids = array_values(array_diff($unassigned_user_ids, $excluded_user_ids));
            // shuffle order of user id's and team id's for "random" radio button
            $sort_type = $_POST['sort_type'] ?? 'random';
            if ($sort_type === 'random') {
                shuffle($unassigned_user_ids);
                foreach ($unassigned_gradeable_teams as $g_id => $team_ids) {
                    shuffle($unassigned_gradeable_teams[$g_id]);
                }
            }
            // delete current rotating sections and create new ones
            $this->core->getQueries()->setAllUsersRotatingSectionNull();
            $this->core->getQueries()->setAllTeamsRotatingSectionNull();
            $this->core->getQueries()->deleteAllRotatingSections();
            for ($i = 1; $i <= $num_rotating_sections; $i++) {
                $this->core->getQueries()->insertNewRotatingSection($i);
            }
            // distribute users to their new rotating sections and create $section_assignment_counts array
            // $section_assigment_counts array represents the number of students to add to each rotating section (section represented by an index in array)
            $section_assignment_counts = array_fill(0, $num_rotating_sections, floor(count($unassigned_user_ids) / $num_rotating_sections));
            for ($section = 0; $section < count($unassigned_user_ids) % $num_rotating_sections; $section++) {
                $section_assignment_counts[$section]++;
            }
            // $gradeables_section_assignment_counts 2d array represents the number of gradeable teams to add to each rotating section
            // array's top level is a gradeable id + bottom level is # teams to add to each rotating section (section represented by an index)
            $gradeables_section_assignment_counts = [];
            // distribute gradeable teams to their new rotating sections and update $gradeables_section_assignment_counts array
            foreach ($unassigned_gradeable_teams as $g_id => $team_ids) {
                $gradeables_section_assignment_counts[$g_id] = array_fill(0, $num_rotating_sections, floor(count($team_ids) / $num_rotating_sections));
                for ($section = 0; $section < count($team_ids) % $num_rotating_sections; $section++) {
                    $gradeables_section_assignment_counts[$g_id][$section]++;
                }
            }
            // update each team's rotating section assignments using $gradeables_section_assignment_counts array
            // TODO: why don't we have to do all the checks that we did for setRotatingGraderSections?
            foreach ($gradeables_section_assignment_counts as $g_id => $counts) {
                for ($i = 0; $i < $num_rotating_sections; $i++) {
                    $update_teams = array_splice($unassigned_gradeable_teams[$g_id], 0, intval($counts[$i]));
                    $this->core->getQueries()->updateTeamsRotatingSection($update_teams, $i + 1, $g_id);
                }
            }
        }
        else { // $_POST['rotating_assignment_type'] === "fewest"
            $this->core->getQueries()->setNonRegisteredUsersRotatingSectionNull();
            $unassigned_user_ids = $this->core->getQueries()->getRegisteredUserIdsWithNullRotating();
            // Find the number of rotating sections to create or update during section assignments
            $num_rotating_sections = $this->core->getQueries()->getMaxRotatingSection();
            if ($num_rotating_sections === null) {
                $this->core->addErrorMessage("No rotating sections have been added to the system, cannot put newly
                    registered students into rotating section with fewest members");
                $this->core->redirect($return_url);
            }

            //Gets all the ids of the excluded sections
            $excluded_registration_sections = $_POST["excluded_registration_sections"] ?? [];
            $excluded_users = $this->core->getQueries()->getUsersByRegistrationSections($excluded_registration_sections);
            $excluded_user_ids = array_map(function (User $user) {
                return $user->getId();
            }, $excluded_users);

            $unassigned_user_ids = array_values(array_diff($unassigned_user_ids, $excluded_user_ids));

            // 'fewest' rotating section setup option can only use random sort
            shuffle($unassigned_user_ids);
            // distribute newly registered users ($unassigned_user_ids) to rotating sections and create $section_assignment_counts array
            // $section_assigment_counts array represents the number of students to add to each rotating section (section represented by an index in array)
            $total_users_count = $this->core->getQueries()->getTotalRegisteredUsersCount();
            $expected_section_sizes = array_fill(0, $num_rotating_sections, floor($total_users_count / $num_rotating_sections));
            for ($section = 0; $section < $total_users_count % $num_rotating_sections; $section++) {
                $expected_section_sizes[$section]++;
            }
            $curr_section_sizes = $this->core->getQueries()->getUsersCountByRotatingSections();
            $section_assignment_counts = array_map(function ($expected_size, $curr_size) {
                return $curr_size === null ? $expected_size : $expected_size - $curr_size['count'];
            }, $expected_section_sizes, $curr_section_sizes);
        }
        // distribute unassigned users to rotating sections using the $section_assigment_counts array
        for ($section = 0; $section < $num_rotating_sections; $section++) {
            $update_users = array_splice($unassigned_user_ids, 0, intval($section_assignment_counts[$section]));
            if (count($update_users) == 0) {
                continue;
            }
            $this->core->getQueries()->updateUsersRotatingSection($section + 1, $update_users);
        }
        // Update graders' access for gradeables with all access grading for limited access graders now that rotating sections are set up
        $update_graders_gradeables = []; // all gradeables in this course where there's limited access graders + all access grading
        $update_graders_gradeables_ids = $this->core->getQueries()->getGradeableIdsForFullAccessLimitedGraders();
        foreach ($update_graders_gradeables_ids as $row) {
            $g_id = $row['g_id'];
            $tmp_gradeable = $this->tryGetGradeable($g_id, false);
            if ($tmp_gradeable === false) {
                continue;
            }
            $update_graders_gradeables[] = $tmp_gradeable;
        }
        $new_graders = $this->core->getQueries()->getNewGraders();
        foreach ($update_graders_gradeables as $update_gradeable) {
            $update_gradeable->setRotatingGraderSections($new_graders);
            $this->core->getQueries()->updateGradeable($update_gradeable);
        }

        $this->core->addSuccessMessage("Rotating sections reassigned successfully");
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
        // Need to provide a comment typehint on these two variables to avoid a phpstan error
        // See: https://github.com/phpstan/phpstan/issues/6559
        /** @var string */
        $csv_file = '';
        /** @var string */
        $xlsx_file = '';

        // Data is confidential, and therefore must be deleted immediately after
        // this process ends, regardless if process completes successfully or not.
        register_shutdown_function(
            function () use (&$csv_file, &$xlsx_file) {
                foreach ([$csv_file, $xlsx_file] as $file) {
                    if (!empty($file) && file_exists($file)) {
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
                curl_setopt($ch, CURLOPT_URL, $this->core->getConfig()->getCgiUrl() . "xlsx_to_csv.cgi?xlsx_file={$xlsx_tmp}&csv_file={$csv_tmp}");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $output = curl_exec($ch);

                if ($output === false) {
                    $this->core->addErrorMessage("Error parsing xlsx to csv");
                    $this->core->redirect($return_url);
                }

                $output = json_decode($output, true);
                if ($output === null) {
                    $this->core->addErrorMessage("Error parsing JSON response: " . json_last_error_msg());
                    $this->core->redirect($return_url);
                }
                elseif ($output['error'] === true) {
                    $this->core->addErrorMessage("Error parsing xlsx to csv: " . $output['error_message']);
                    $this->core->redirect($return_url);
                }
                elseif ($output['success'] !== true) {
                    $this->core->addErrorMessage("Error on response on parsing xlsx: " . curl_error($ch));
                    $this->core->redirect($return_url);
                }

                curl_close($ch);
            }
            else {
                $this->core->addErrorMessage("Did not properly receive spreadsheet. Contact your sysadmin.");
                $this->core->redirect($return_url);
            }
        }
        elseif ($content_type === 'text/csv' && ($mime_type === 'text/plain' || $mime_type === "text/csv")) {
            $csv_file = $tmp_name;
        }
        else {
            $this->core->addErrorMessage("Must upload xlsx or csv");
            $this->core->redirect($return_url);
        }

        // Parse user data (should be a CSV file either uploaded or converted from XLSX).
        // First, set environment config to allow '\r' EOL encoding. (Used by Microsoft Excel on Macintosh)
        ini_set("auto_detect_line_endings", '1');

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
        $user_data = array_filter($user_data, function ($row) {
            return !empty(array_filter($row, function ($val) {
                return !empty($val);
            }));
        });

        //Apply trim() to all data values.
        array_walk_recursive($user_data, function (&$val) {
            $val = trim($val);
        });

        return $user_data;
    }

    /**
     * Upload user list data to database
     *
     * @param string $list_type "classlist" or "graderlist"
     */
    #[Route("/courses/{_semester}/{_course}/users/upload", methods: ["POST"])]
    public function uploadUserList($list_type = "classlist") {

        /**
         * Closure to INSERT or UPDATE user data based on $action
         *
         * @param string $action "insert" or "update"
         */
        $insert_or_update_user_function = function ($action, $user) use (&$semester, &$course, &$return_url) {
            try {
                switch ($action) {
                    case 'insert':
                        //User must first exist in Submitty before being enrolled to a course.
                        if (is_null($this->core->getQueries()->getSubmittyUser($user->getId()))) {
                            $this->core->getQueries()->insertSubmittyUser($user);
                            if ($this->core->getAuthentication() instanceof SamlAuthentication) {
                                $this->core->getQueries()->insertSamlMapping($user->getId(), $user->getId());
                            }
                        }
                        $this->core->getQueries()->insertCourseUser($user, $semester, $course);
                        CourseRegistrationController::applyDefaultNotificationSettings($this->core, $user->getId());
                        break;
                    case 'update':
                        $this->core->getQueries()->updateUser($user, $semester, $course);
                        break;
                    default:
                        throw new ValidationException("Unknown DB operation", [$action, '$insert_or_update_user_function']);
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
        $set_return_url_action_function = function () use ($list_type) {
            switch ($list_type) {
                case "classlist":
                    return "users";
                case "graderlist":
                    return "graders";
                default:
                    throw new ValidationException("Unknown classlist", [$list_type, '$set_return_url_action_function']);
            }
        };

        $return_url = $this->core->buildCourseUrl([$set_return_url_action_function()]);
        $use_database = $this->core->getAuthentication() instanceof DatabaseAuthentication;

        if ($_FILES['upload']['name'] === "") {
            $this->core->addErrorMessage("No input file specified");
            $this->core->redirect($return_url);
        }

        $uploaded_data = $this->getUserDataFromUpload($_FILES['upload']['name'], $_FILES['upload']['tmp_name'], $return_url);

        // Validation and error checking.
        $pref_givenname_idx = $use_database ? 6 : 5;
        $pref_familyname_idx = $pref_givenname_idx + 1;
        $registration_type_idx = $pref_givenname_idx + 2;
        $registration_section_idx = $list_type === 'classlist' ? 4 : $pref_givenname_idx + 2;
        $grading_assignments_idx = $use_database ? 9 : 8;
        $bad_row_details = [];
        $bad_columns = []; //Tracks columns in which errors occurred

        /* Used for validation of grading assignment for graders to registration sections. Graders cannot
          be assigned to grade the (created-by-default) null registration section. */
        $invalid_grading_assignments = [];
        $valid_sections = $this->core->getQueries()->getRegistrationSections();
        foreach ($valid_sections as $i => $section) {
            $valid_sections[$i] = $section['sections_registration_id'];
        }

        // Mapping column with its validation formats
        $column_formats = [
            'column_count' => 'Only 5 to 10 columns are allowed',
            'user_id' => 'UserId must contain only lowercase alpha, numbers, underscores, hyphens',
            'user_id_saml' => 'UserId must be a valid SAML username',
            'user_legal_givenname' => 'Legal first name must be alpha characters, white-space, or certain punctuation.',
            'user_legal_familyname' => 'Legal last name must be alpha characters, white-space, or certain punctuation.',
            'user_email' => 'Email address should be valid with appropriate format. e.g. "student@university.edu", "student@cs.university.edu", etc.',
            'registration_section' =>  'Registration must contain only these characters - A-Z,a-z,_,-',
            'grader_group' => 'Grader-level should be in between 1 - 4.',
            'user_password' => 'user_password cannot be blank',
            'user_preferred_givenname' => 'Preferred first name must be alpha characters, white-space, or certain punctuation.',
            'user_preferred_familyname' => 'Preferred last name must be alpha characters, white-space, or certain punctuation.',
            'grading_assignments_format' => 'Grading assignments must be comma-separated course registration sections, ' .
                'enclosed in double quotes (e.g. "1,3,STAFF").',
            'grading_assignments_duplicate' => 'Grading assignments must be unique. Duplicate registration sections detected.',
            'invalid_grading_assignments' => 'Grading assignments must be valid course registration sections.',
            'user_registration_type' => 'Student registration type must be one of either "graded", "audit", or "withdrawn".',
        ];
        $users = [];
        foreach ($uploaded_data as $vals) {
            $users[] = $vals[0];
        }
        $authentication = $this->core->getAuthentication();
        if ($authentication instanceof SamlAuthentication) {
            $authentication->setValidUsernames($users);
        }
        foreach ($uploaded_data as $row_num => $vals) {
            // When record contain just one field, only check for valid user_id
            if (count($vals) === 1) {
                if (!User::validateUserData('user_id', $vals[0])) {
                    $bad_rows[] = ($row_num + 1);
                }
                continue;
            }
            // Bounds check to ensure minimum required number of rows is present.
            if (count($vals) < 5 || count($vals) > 10) {
                $bad_row_details[$row_num + 1][] = 'column Count';
                if (!in_array('column_count', $bad_columns)) {
                    $bad_columns[] = 'column_count';
                }
            }
            // Username must contain only lowercase alpha, numbers, underscores, hyphens
            if (!User::validateUserData('user_id', $vals[0])) {
                $bad_row_details[$row_num + 1][] = 'user_id';
                if (!in_array('user_id', $bad_columns)) {
                    $bad_columns[] = 'user_id';
                }
            }
            if ($authentication instanceof SamlAuthentication) {
                if ($authentication->isInvalidUsername($vals[0])) {
                    $bad_row_details[$row_num + 1][] = 'user_id';
                    if (!in_array('user_id_saml', $bad_columns)) {
                        $bad_columns[] = 'user_id_saml';
                    }
                }
            }
            // Given Name must be alpha characters, white-space, or certain punctuation.
            if (!User::validateUserData('user_legal_givenname', $vals[1])) {
                $bad_row_details[$row_num + 1][] = 'given name';
                if (!in_array('user_legal_givenname', $bad_columns)) {
                    $bad_columns[] = 'user_legal_givenname';
                }
            }
            // Family Name must be alpha characters, white-space, or certain punctuation.
            if (!User::validateUserData('user_legal_familyname', $vals[2])) {
                $bad_row_details[$row_num + 1][] = 'family name';
                if (!in_array('user_legal_familyname', $bad_columns)) {
                    $bad_columns[] = 'user_legal_familyname';
                }
            }
            // Check email address for appropriate format. e.g. "student@university.edu", "student@cs.university.edu", etc.
            if (!User::validateUserData('user_email', $vals[3])) {
                $bad_row_details[$row_num + 1][] = 'user email';
                if (!in_array('user_email', $bad_columns)) {
                    $bad_columns[] = 'user_email';
                }
            }
            /* Check registration for appropriate format. Allowed characters - A-Z,a-z,_,- .
            Registration section is optional for graders, so automatically validate if not set.*/
            if (isset($vals[$registration_section_idx]) && strtolower($vals[$registration_section_idx]) === "null") {
                $vals[$registration_section_idx] = null;
            }
            $unset_grader_registration_section = ($list_type === 'graderlist' && empty($vals[$registration_section_idx]));
            if (!($unset_grader_registration_section || User::validateUserData('registration_section', $vals[$registration_section_idx]))) {
                $bad_row_details[$row_num + 1][] = 'Registration section';
                if (!in_array('registration_section', $bad_columns)) {
                    $bad_columns[] = 'registration_section';
                }
            }
            /* Check grader group for appropriate format if graderlist upload. */
            if ($list_type === 'graderlist') {
                if (isset($vals[4]) && is_numeric($vals[4])) {
                    $vals[4] = intval($vals[4]); //change float read from xlsx to int
                }
                //grader-level check is a digit between 1 - 4.
                if (!User::validateUserData('user_group', $vals[4])) {
                    $bad_row_details[$row_num + 1][] = 'Grader-group';
                    if (!in_array('grader_group', $bad_columns)) {
                        $bad_columns[] = 'grader_group';
                    }
                }
            }
            /* Database password cannot be blank, no check on format.
               Automatically validate if NOT using database authentication (e.g. using PAM authentication) */
            if (!(!$use_database || User::validateUserData('user_password', $vals[5]))) {
                $bad_row_details[$row_num + 1][] = 'User password';
                if (!in_array('user_password', $bad_columns)) {
                    $bad_columns[] = 'user_password';
                }
            }
            /* Preferred given and family name must be alpha characters, white-space, or certain punctuation.
               Automatically validate if not set (this field is optional) */
            if (!(empty($vals[$pref_givenname_idx]) || User::validateUserData('user_preferred_givenname', $vals[$pref_givenname_idx]))) {
                $bad_row_details[$row_num + 1][] = 'preferred given name';
                if (!in_array('user_preferred_givenname', $bad_columns)) {
                    $bad_columns[] = 'user_preferred_givenname';
                }
            }
            if (!(empty($vals[$pref_familyname_idx]) || User::validateUserData('user_preferred_familyname', $vals[$pref_familyname_idx]))) {
                $bad_row_details[$row_num + 1][] = 'preferred family name';
                if (!in_array('user_preferred_familyname', $bad_columns)) {
                    $bad_columns[] = 'user_preferred_familyname';
                }
            }
            /* Grading assignments for graderlist uploads must be valid, comma-separated course registration sections.
               Automatically validate if not set (this field is optional). */
            if ($list_type === 'graderlist' && !(empty($vals[$grading_assignments_idx]))) {
                if (!User::validateUserData('grading_assignments', $vals[$grading_assignments_idx])) {
                    // Regex check for comma-separated registration sections.
                    $bad_row_details[$row_num + 1][] = 'grading assignments format';
                    if (!in_array('grading_assignments_format', $bad_columns)) {
                        $bad_columns[] = 'grading_assignments_format';
                    }
                }
                else {
                    $grading_assignments = explode(',', $vals[$grading_assignments_idx]);
                    if (count($grading_assignments) !== count(array_unique($grading_assignments))) {
                        // Prevent duplicate registration sections from being specified for assignment.
                        $bad_row_details[$row_num + 1][] = 'duplicate grading assignments';
                        if (!in_array('grading_assignments_format', $bad_columns)) {
                            $bad_columns[] = 'grading_assignments_duplicate';
                        }
                    }
                    else {
                        // Confirm entered registration sections are valid, pre-existing sections within the course.
                        $unrecognized_sections = array_diff($grading_assignments, $valid_sections);
                        if (count($unrecognized_sections) > 0) {
                            $bad_row_details[$row_num + 1][] = 'grading assignment sections';
                            if (!in_array('invalid_grading_assignments', $bad_columns)) {
                                $bad_columns[] = 'invalid_grading_assignments';
                            }
                            $invalid_grading_assignments = array_unique(array_merge($invalid_grading_assignments, $unrecognized_sections));
                        }
                    }
                }
            }
            /* Check valid registration type for classlist uploads; automatically validate if not set (this field is optional).
               Graderlist uploads default to 'staff' registration type. */
            if ($list_type === 'classlist' && !(empty($vals[$registration_type_idx]))) {
                if (!User::validateUserData('student_registration_type', $vals[$registration_type_idx])) {
                    $bad_row_details[$row_num + 1][] = 'registration type';
                    if (!in_array('user_registration_type', $bad_columns)) {
                        $bad_columns[] = 'user_registration_type';
                    }
                }
            }
            // Ensure changes to $vals (which is an alias to a row in $uploaded_data) reflects in actual $uploaded_data.
            $uploaded_data[$row_num] = $vals;
        }

        // $bad_rows will contain rows with errors.  No errors to report when empty.
        if (!empty($bad_row_details)) {
            $msg = "Please correct the following errors :- \n";
            array_walk($bad_row_details, function ($errors, $row_num) use (&$msg) {
                $msg .= "Invalid " . implode(', ', $errors) . " on row number - $row_num \n";
            });
            // Adding Suggestion for the user tp
            $msg .= "\n Format your data as per following standards";
            foreach ($bad_columns as $bad_col) {
                $msg .= "\n " . $column_formats[$bad_col];
                if ($bad_col === 'invalid_grading_assignments') {
                    $msg .= ' Invalid registration sections specified:- ' . implode(', ', $invalid_grading_assignments) . '.';
                }
            }
            $this->core->addErrorMessage($msg);
            $this->core->redirect($return_url);
        }

        // Isolate existing users ($users_to_update[]) and new users ($users_to_add[])
        $existing_users = $this->core->getQueries()->getAllUsers();
        $users_to_add = [];
        $users_to_update = [];
        foreach ($uploaded_data as $row) {
            $exists = false;
            foreach ($existing_users as $i => $existing_user) {
                if ($row[0] === $existing_user->getId()) {
                    // Validate if this user has any data to update.
                    // Did student registration section, grader group, or registration type change?
                    if (count($row) === 1) {
                        $users_to_update[] = $row;
                    }
                    elseif (!empty($row[$registration_section_idx]) && $row[$registration_section_idx] !== $existing_user->getRegistrationSection()) {
                        $users_to_update[] = $row;
                    }
                    elseif ($list_type === 'graderlist' && $row[4] !== (string) $existing_user->getGroup()) {
                        $users_to_update[] = $row;
                    }
                    elseif ($list_type === 'classlist' && !empty($row[$registration_type_idx]) && $row[$registration_type_idx] !== $existing_user->getRegistrationType()) {
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
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        $users_not_found = []; // track wrong user_ids given

        foreach ($users_to_add as $key => $row) {
            // Remove the rows in which wrong or incorrect user_id is passed (only for row containing user_id)
            if (count($row) === 1 && is_null($this->core->getQueries()->getSubmittyUser($row[0]))) {
                $users_not_found[] = $row[0];
                unset($users_to_add[$key]);
            }
        }
        if (!empty($users_not_found)) {
            $this->core->addErrorMessage('User(s) with following username are not found:- ' . implode(', ', $users_not_found));
            $this->core->redirect($return_url);
        }
        foreach ($users_to_add as $row) {
            // Auto-populate user detail if only user_id is given
            if (count($row) === 1) {
                $user = $this->core->getQueries()->getUserById($row[0]);
                // set group as 'student' if upload is meant for classlist else set 'limited_access_grader' level
                $user_group = $list_type === 'classlist' ? User::GROUP_STUDENT : User::GROUP_LIMITED_ACCESS_GRADER;
                $user->setGroup($user_group);
                $user_registration_type = $list_type === 'classlist' ? 'graded' : 'staff';
                $user->setRegistrationType($user_registration_type);
                $insert_or_update_user_function('insert', $user);
            }
            else {
                $user = new User($this->core);
                $user->setId($row[0]);
                $user->setLegalGivenName($row[1]);
                $user->setLegalFamilyName($row[2]);
                $user->setEmail($row[3]);
                // Registration section has to exist, or a DB exception gets thrown on INSERT or UPDATE.
                // ON CONFLICT clause in DB query prevents thrown exceptions when registration section already exists.
                if (!empty($row[$registration_section_idx])) {
                    $this->core->getQueries()->insertNewRegistrationSection($row[$registration_section_idx]);
                    $user->setRegistrationSection($row[$registration_section_idx]);
                }
                $user->setGroup($list_type === 'classlist' ? 4 : $row[4]);
                if (!empty($row[$pref_givenname_idx])) {
                    $user->setPreferredGivenName($row[$pref_givenname_idx]);
                }
                if (!empty($row[$pref_familyname_idx])) {
                    $user->setPreferredFamilyName($row[$pref_familyname_idx]);
                }
                if ($use_database) {
                    $user->setPassword($row[5]);
                }
                if ($list_type === 'graderlist' && !empty($row[$grading_assignments_idx])) {
                    $grading_assignments = explode(',', $row[$grading_assignments_idx]);
                    sort($grading_assignments);
                    $user->setGradingRegistrationSections($grading_assignments);
                }
                if ($list_type === 'classlist') {
                    $user->setRegistrationType($row[$registration_type_idx] ?? 'graded');
                }
                else {
                    $user->setRegistrationType('staff');
                }
                $insert_or_update_user_function('insert', $user);
            }
        }

        // Existing users update
        foreach ($users_to_update as $row) {
            $user = $this->core->getQueries()->getUserById($row[0]);
            //Update registration section (student) or group (grader)
            if (count($row) === 1) {
                // set group as 'student' if upload is meant for classlist else set 'limited_access_grader' level
                $user_group = $list_type === 'classlist' ? User::GROUP_STUDENT : User::GROUP_LIMITED_ACCESS_GRADER;
                $user->setGroup($user_group);
            }
            else {
               // Registration section has to exist, or a DB exception gets thrown on INSERT or UPDATE.
                // ON CONFLICT clause in DB query prevents thrown exceptions when registration section already exists.
                if (!empty($row[$registration_section_idx])) {
                    $this->core->getQueries()->insertNewRegistrationSection($row[$registration_section_idx]);
                    $user->setRegistrationSection($row[$registration_section_idx]);
                }
                $user->setGroup($list_type === 'classlist' ? 4 : $row[4]);
                if ($list_type === 'graderlist' && !empty($row[$grading_assignments_idx])) {
                    $grading_assignments = explode(',', $row[$grading_assignments_idx]);
                    sort($grading_assignments);
                    $user->setGradingRegistrationSections($grading_assignments);
                }
                if ($list_type === 'classlist') {
                    $user->setRegistrationType($row[$registration_type_idx] ?? 'graded');
                }
                else {
                    $user->setRegistrationType('staff');
                }
            }
            $insert_or_update_user_function('update', $user);
        }
        $added = count($users_to_add);
        $updated = count($users_to_update);

        //Special case to move students to the NULL section with a classlist upload.
        if ($list_type === "classlist" && isset($_POST['move_missing'])) {
            foreach ($existing_users as $existing_user) {
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
