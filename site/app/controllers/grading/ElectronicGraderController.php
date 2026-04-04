<?php

namespace app\controllers\grading;

use app\libraries\DateUtils;
use app\libraries\DiffViewer;
use app\libraries\GradeableType;
use app\libraries\response\RedirectResponse;
use app\libraries\routers\AccessControl;
use app\models\gradeable\Component;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedComponent;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\LateDayInfo;
use app\models\gradeable\LateDays;
use app\models\gradeable\Submitter;
use app\models\gradeable\TaGradedGradeable;
use app\models\GradeableAutocheck;
use app\libraries\Logger;
use app\models\GradingOrder;
use app\models\User;
use app\libraries\FileUtils;
use app\libraries\response\JsonResponse;
use app\controllers\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ElectronicGraderController extends AbstractController {
    /**
     * Checks that a given diff viewer option is valid using DiffViewer::isValidSpecialCharsOption
     * @param  string $option
     * @return bool
     */
    private function validateDiffViewerOption(string $option) {
        if (!DiffViewer::isValidSpecialCharsOption($option)) {
            $this->core->getOutput()->renderJsonFail('Invalid diff viewer option parameter');
            return false;
        }
        return true;
    }

    /**
     * Checks that a given diff viewer type is valid using DiffViewer::isValidType
     * @param string $type
     * @return bool
     */
    private function validateDiffViewerType(string $type) {
        if (!DiffViewer::isValidType($type)) {
            $this->core->getOutput()->renderJsonFail('Invalid diff viewer type parameter');
            return false;
        }
        return true;
    }
    /**
     * Route for Getting Gradeable
     *
     * @param string $gradeable_id
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/getUserGroup", methods: ["GET"])]
    public function getUserGroup($gradeable_id) {
        $user_group = $this->core->getUser()->getGroup();
        return JsonResponse::getSuccessResponse($user_group);
    }
    /**
     * Route for getting whitespace information for the diff viewer
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/student_output/remove")]
    public function ajaxRemoveEmpty(
        $gradeable_id,
        $who_id = '',
        $index = '',
        $option = 'original',
        $version = '',
        $which = 'actual',
        $autocheck_cnt = '0'
    ) {
        //There are three options: original (Don't show empty space), escape (with escape codes), and unicode (with characters)
        if (!$this->validateDiffViewerOption($option)) {
            return;
        }

        // Type can be either 'actual' or 'expected'
        if (!$this->validateDiffViewerType($which)) {
            return;
        }

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $who_id);
        if ($graded_gradeable === false) {
            return;
        }

        // get the requested version
        $version_instance = $this->tryGetVersion($graded_gradeable->getAutoGradedGradeable(), $version);
        if ($version_instance === false) {
            return;
        }

        // Get the requested testcase
        $testcase = $this->tryGetTestcase($version_instance, $index);
        if ($testcase === false) {
            return;
        }

        // Get the requested autocheck
        $autocheck = $this->tryGetAutocheck($testcase, $autocheck_cnt);
        if ($autocheck === false) {
            return;
        }

        try {
            $results = $this->removeEmpty($autocheck, $option, $which);
            $this->core->getOutput()->renderJsonSuccess($results);
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    private function removeEmpty(GradeableAutocheck $autocheck, string $option, string $type) {
        $diff_viewer = $autocheck->getDiffViewer();

        //There are currently two views, the view of student's code and the expected view.
        if ($type === DiffViewer::ACTUAL) {
            $html = $diff_viewer->getDisplayActual($option);
        }
        else {
            $html = $diff_viewer->getDisplayExpected($option);
        }
        $white_spaces = $diff_viewer->getWhiteSpaces();
        return ['html' => $html, 'whitespaces' => $white_spaces];
    }
    /**
     * Shows the list of submitters
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/details")]
    public function showDetails(string $gradeable_id) {
        // Default is viewing your sections
        // Limited grader does not have "View All" option
        // If nothing to grade, Instructor will see all sections
        $view_all = isset($_COOKIE['view']) && $_COOKIE['view'] === 'all';
        $gradeable = $this->tryGetGradeable($gradeable_id);
        $current_user = $this->core->getUser();
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid Gradeable!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            return new RedirectResponse($this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading']));
        }

        $gradeableUrl = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'details']);
        $this->core->getOutput()->addBreadcrumb("{$gradeable->getTitle()} Grading", $gradeableUrl);

        $peer = ($gradeable->hasPeerComponent() && $current_user->getGroup() == User::GROUP_STUDENT);
        if (!$this->core->getAccess()->canI("grading.electronic.details", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getTitle()}");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->hasAutogradingConfig()) {
            $this->core->getOutput()->renderOutput(
                'Error',
                'unbuiltGradeable',
                $gradeable,
                "grades"
            );
            return;
        }

        // Anon mode for limited access graders should be based on the limited access blind setting
        // For the instructor, they can choose to toggle anon mode, so check the cookie then the setting
        $anon_mode =
        ($current_user->getGroup() === User::GROUP_LIMITED_ACCESS_GRADER && $gradeable->getLimitedAccessBlind() === Gradeable::SINGLE_BLIND_GRADING) ||
        ($current_user->getGroup() < 3 && (
            (isset($_COOKIE['anon_mode']) && $_COOKIE['anon_mode'] === 'on') ||
            (!isset($_COOKIE['anon_mode']) && $gradeable->getInstructorBlind() === Gradeable::SINGLE_BLIND_GRADING)
        ));

        $inquiry_status = isset($_COOKIE['inquiry_status']) && $_COOKIE['inquiry_status'] === 'on';

        $sort = isset($_COOKIE['sort']) ? $_COOKIE['sort'] : 'id';
        $direction = isset($_COOKIE['direction']) ? $_COOKIE['direction'] : 'ASC';


        //Get grading_details Columns
        $grading_details_columns = [];
        if (isset($_COOKIE['grading_details_columns'])) {
            $grading_details_columns = json_decode($_COOKIE['grading_details_columns'], true);
        }

        //Checks to see if the Grader has access to all users in the course,
        //Will only show the sections that they are graders for if not TA or Instructor
        $can_show_all = $this->core->getAccess()->canI("grading.electronic.details.show_all");
        $show_all = $view_all && $can_show_all;

        $order = new GradingOrder($this->core, $gradeable, $current_user, $show_all);

        $order->sort($sort, $direction);

        $section_submitters = $order->getSectionSubmitters();
        $section_key = $order->getSectionKey();
        $graders = $order->getSectionGraders();
        $sections = $order->getSectionNames();

        $student_ids = [];
        foreach ($section_submitters as $section) {
            $student_ids = array_merge($student_ids, array_map(function (Submitter $submitter) {
                return $submitter->getId();
            }, $section));
        }

        $show_empty_teams = $this->core->getAccess()->canI("grading.electronic.details.show_empty_teams");
        $all_teams = [];
        $empty_teams = [];
        if ($gradeable->isTeamAssignment()) {
            // Only give getGradeables one User ID per team
            $all_teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
            foreach ($all_teams as $team) {
                $student_ids = array_diff($student_ids, $team->getMembers());
                $team_section = $gradeable->isGradeByRegistration() ? $team->getRegistrationSection() : $team->getRotatingSection();
                if ($team->getSize() > 0 && (in_array($team_section, $sections) || $show_all)) {
                    $student_ids[] = $team->getLeaderId();
                }
                if ($team->getSize() === 0 && $show_empty_teams) {
                    $empty_teams[] = $team;
                }
            }
        }

        $graded_gradeables = [];
        $user_ids = [];
        if ($gradeable->isTeamAssignment()) {
            $user_ids = $this->core->getQueries()->getUsersOnTeamsForGradeable($gradeable);
            // Collect user ids so we know who isn't on a team
        }
        /** @var GradedGradeable $g */
        foreach ($order->getSortedGradedGradeables() as $g) {
            $graded_gradeables[] = $g;
            if ($gradeable->isTeamAssignment()) {
                $user_ids = array_merge($user_ids, $g->getSubmitter()->getTeam()->getMemberUserIds());
            }
        }
        $teamless_users = [];
        if ($gradeable->isTeamAssignment()) {
            //Find teamless users
            if ($show_all) {
                $students = $this->core->getQueries()->getAllUsers();
            }
            else {
                if ($gradeable->isGradeByRegistration()) {
                    $students = $this->core->getQueries()->getUsersByRegistrationSections($order->getSectionNames());
                }
                else {
                    $students = $this->core->getQueries()->getUsersByRotatingSections($order->getSectionNames());
                }
            }
            foreach ($students as $user) {
                if (!in_array($user->getId(), $user_ids)) {
                    $teamless_users[] = $user;
                }
            }
        }
        $show_all_sections_button = $can_show_all;
        $show_edit_teams = $this->core->getAccess()->canI("grading.electronic.show_edit_teams") && $gradeable->isTeamAssignment();
        $show_import_teams_button = $show_edit_teams && (count($all_teams) > count($empty_teams));
        $show_export_teams_button = $show_edit_teams && (count($all_teams) == count($empty_teams));
        $past_grade_start_date = $gradeable->getDates()['grade_start_date'] < $this->core->getDateTimeNow();

        $rawOverrides = $this->core->getQueries()->getRawUsersWithOverriddenGrades($gradeable->getId());
        $overrides = [];
        $override_data = [];
        foreach ($rawOverrides as $o) {
            $overrides[] = $o['user_id'];
            $override_data[$o['user_id']] = [
                'marks' => $o['marks'],
                'comment' => $o['comment'] ?? ''
            ];
        }

        $rawAnonIds = $this->core->getQueries()->getAllAnonIdsByGradeableWithUserIds($gradeable->getId());
        if ($gradeable->isTeamAssignment()) {
            $rawAnonIds = array_merge($rawAnonIds, $this->core->getQueries()->getAllTeamAnonIdsByGradeable($gradeable->getId()));
        }
        $anon_ids = [];
        foreach ($rawAnonIds as $anon) {
            if (key_exists('team_id', $anon)) {
                $anon_ids[$anon['team_id']] = $anon['anon_id'];
            }
            else {
                $anon_ids[$anon['user_id']] = $anon['anon_id'];
            }
        }

        $activeGradersData = $this->core->getQueries()->getActiveGradersForGradeable($gradeable_id);
        $activeGraders = [];
        if ($gradeable->isTeamAssignment()) {
            $key = "ag_team_id";
        }
        else {
            $key = "ag_user_id";
        }
        for ($i = 0; $i < count($activeGradersData); $i++) {
            $activeGraders[$activeGradersData[$i][$key]][$activeGradersData[$i]['gc_id']][] = $activeGradersData[$i];
        }

        $this->core->getOutput()->renderOutput(['grading', 'ElectronicGrader'], 'detailsPage', $gradeable, $graded_gradeables, $teamless_users, $graders, $empty_teams, $show_all_sections_button, $show_import_teams_button, $show_export_teams_button, $show_edit_teams, $past_grade_start_date, $view_all, $sort, $direction, $anon_mode, $overrides, $override_data, $anon_ids, $inquiry_status, $grading_details_columns, $activeGraders);

        if ($show_edit_teams) {
            $all_reg_sections = $this->core->getQueries()->getRegistrationSections();
            $key = 'sections_registration_id';
            foreach ($all_reg_sections as $i => $section) {
                $all_reg_sections[$i] = $section[$key];
            }

            $all_rot_sections = $this->core->getQueries()->getRotatingSections();
            $key = 'sections_rotating_id';

            foreach ($all_rot_sections as $i => $section) {
                $all_rot_sections[$i] = $section[$key];
            }

            $students = $this->core->getQueries()->getAllUsers();
            $this->core->getOutput()->renderOutput(['grading', 'ElectronicGrader'], 'adminTeamForm', $gradeable, $all_reg_sections, $all_rot_sections, $students);
            $this->core->getOutput()->renderOutput(['grading', 'ElectronicGrader'], 'importTeamForm', $gradeable);

            $this->core->getOutput()->renderOutput(['grading','ElectronicGrader'], 'randomizeButtonWarning', $gradeable);
        }
    }

    /**
     * Get attachments for a gradeable
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/attachments", methods: ["GET"])]
    public function ajaxGetAttachments($gradeable_id) {
        $anon_id = $_POST['anon_id'] ?? '';
        $grader_id = $_POST['grader_id'] ?? '';

        $grader = $this->core->getQueries()->getUserById($grader_id);
        if ($grader === null) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get attachments.');
            return;
        }
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get attachments.');
            return;
        }
        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id);
        if ($submitter_id === false) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get attachments.');
            return;
        }
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get attachments.');
            return;
        }

        // checks if user has permission
        if (!$this->core->getAccess()->canI("path.read.attachments", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get attachments.');
            return;
        }

        $ta_graded_gradeable = $graded_gradeable->getTaGradedGradeable();

        if ($ta_graded_gradeable !== null) {
            $this->core->getOutput()->renderJsonSuccess($ta_graded_gradeable->getAttachments($grader));
        }
        else {
            $this->core->getOutput()->renderJsonSuccess([]);
        }
    }

    /**
     * Upload an attachment for a grader
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/attachments/upload", methods: ["POST"])]
    public function uploadAttachment($gradeable_id) {
        $anon_id = $_POST['anon_id'] ?? '';

        //Get the user uploading attachment
        $grader = $this->core->getUser();
        if (!$grader->accessGrading()) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to upload attachments.');
            return;
        }

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to upload attachments.');
            return;
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }

        // Get user id from the anon id
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id);
        if ($submitter_id === false) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to upload attachments.');
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to upload attachments.');
            return;
        }

        // Check access
        if (!$this->core->getAccess()->canI("path.write.attachments", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to upload attachments.');
            return;
        }

        if (empty($_FILES) || empty($_FILES['attachment']) || empty($_FILES['attachment']['tmp_name'])) {
            $this->core->getOutput()->renderJsonFail('Missing attachment to save.');
            return;
        }

        $attachment = $_FILES['attachment'];
        $status = FileUtils::validateUploadedFiles($attachment);
        if (!$status[0]["success"]) {
            $this->core->getOutput()->renderJsonFail('Failed to validate upload: ' . $status[0]["error"]);
            return;
        }
        $attachment_path_folder = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'attachments', $gradeable->getId(), $submitter_id, $grader->getId());
        FileUtils::createDir($attachment_path_folder, true);
        $attachment_path = FileUtils::joinPaths($attachment_path_folder, $attachment['name']);
        if (!move_uploaded_file($attachment["tmp_name"], $attachment_path)) {
            $this->core->getOutput()->renderJsonFail('Failed to upload file.');
        }
        else {
            // Get the TA graded gradeable
            $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

            // New info, so reset the user viewed date
            $ta_graded_gradeable->resetUserViewedDate();

            // Finally, save the changes to the database
            $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);
            $submitter = $ta_graded_gradeable->getGradedGradeable()->getSubmitter();
            if ($submitter->isTeam()) {
                $this->core->getQueries()->clearTeamViewedTime($submitter->getId());
            }
            $this->core->getOutput()->renderJsonSuccess(
                [
                    "name" => $attachment['name'],
                    "path" => $attachment_path
                ]
            );
        }
    }

    /**
     * Delete an attachment for a grader
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/attachments/delete", methods: ["POST"])]
    public function deleteAttachment($gradeable_id) {
        $anon_id = $_POST['anon_id'] ?? '';
        $grader = $this->core->getUser();

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to delete attachments.');
            return;
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }

        // Get user id from the anon id
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id);
        if ($submitter_id === false) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to delete attachments.');
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to delete attachment.');
            return;
        }

        // Check access
        if (!$this->core->getAccess()->canI("path.write.attachments", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to delete attachments.');
            return;
        }

        if (empty($_POST['attachment'])) {
            $this->core->getOutput()->renderJsonFail('Missing attachment to delete.');
            return;
        }

        if (strpos($_POST['attachment'], "..") !== false) {
            $this->core->getOutput()->renderJsonFail('Invalid path.');
            return;
        }

        $attachment_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'attachments', $gradeable->getId(), $submitter_id, $grader->getId(), $_POST["attachment"]);
        if (is_file($attachment_path)) {
            if (@unlink($attachment_path)) {
                $this->core->getOutput()->renderJsonSuccess();
            }
            else {
                $this->core->getOutput()->renderJsonFail('Failed to remove file.');
            }
        }
        else {
            $this->core->getOutput()->renderJsonFail('File not found.');
        }
    }

    /**
     * Imports teams from a csv file upload
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/teams/import", methods: ["POST"])]
    public function importTeams($gradeable_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->addErrorMessage('This gradeable is not an electronic file gradeable');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'grading', 'details']);

        if (!$this->core->getAccess()->canI("grading.electronic.import_teams", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("You do not have permission to do that.");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($return_url);
        }

        if ($_FILES['upload_team']['name'] == "") {
            $this->core->addErrorMessage("No input file specified");
            $this->core->redirect($return_url);
        }

        $csv_file = $_FILES['upload_team']['tmp_name'];
        register_shutdown_function(
            function () use ($csv_file) {
                unlink($csv_file);
            }
        );
        ini_set("auto_detect_line_endings", '1');

        $contents = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($contents === false) {
            $this->core->addErrorMessage("File was not properly uploaded. Contact your sysadmin.");
            $this->core->redirect($return_url);
        }

        $row_num = 1;
        $error_message = "";
        /** @var string[][] */
        $new_teams_members = [];
        $team_names = [];
        foreach ($contents as $content) {
            $vals = str_getcsv($content);
            $vals = array_map('trim', $vals);
            if (count($vals) != 7) {
                $error_message .= "ERROR on row {$row_num}, csv row do not follow specified format<br>";
                continue;
            }
            if ($row_num == 1) {
                $row_num += 1;
                continue;
            }
            $team_name = $vals[4];
            $team_id = $vals[3];
            $user_id = $vals[2];

            if ($team_name == '') {
                $team_name = null;
            }

            if ($this->core->getQueries()->getUserById($user_id) === null) {
                $error_message .= "ERROR on row {$row_num}, user_id doesn't exists<br>";
                continue;
            }
            if (!array_key_exists($team_id, $new_teams_members)) {
                $new_teams_members[$team_id] = [];
            }
            $new_teams_members[$team_id][] = $user_id;
            $team_names[$team_id] = $team_name;
        }

        if ($error_message != "") {
            $this->core->addErrorMessage($error_message);
            $this->core->redirect($return_url);
        }

        $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id);
        if (!FileUtils::createDir($gradeable_path)) {
            $this->core->addErrorMessage("Failed to make folder for this assignment");
            $this->core->redirect($return_url);
        }

        foreach ($new_teams_members as $team_id => $members) {
            $leader_id = $members[0];

            $leader = $this->core->getQueries()->getUserById($leader_id);
            $members = $this->core->getQueries()->getUsersById(array_slice($members, 1));
            try {
                $gradeable->createTeam($leader, $members, '', -1, $team_names[$team_id]);
            }
            catch (\Exception $e) {
                $this->core->addErrorMessage("Team may not have been properly initialized ($leader_id): {$e->getMessage()}");
            }
        }

        $this->core->addSuccessMessage("All Teams are imported to the gradeable");
        $this->core->redirect($return_url);
    }

    /**
     * Exports team into a csv file and displays it to the user
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/teams/export")]
    public function exportTeams($gradeable_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->addErrorMessage('This gradeable is not an electronic file gradeable');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$this->core->getAccess()->canI("grading.electronic.export_teams", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("You do not have permission to do that.");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $all_teams = $gradeable->getTeams();
        $nl = "\n";
        $csvdata = "Given Name,Family Name,User ID,Team ID,Team Name,Team Registration Section,Team Rotating Section" . $nl;
        foreach ($all_teams as $team) {
            if ($team->getSize() != 0) {
                foreach ($team->getMemberUsers() as $user) {
                    $csvdata .= implode(',', [
                        $user->getDisplayedGivenName(),
                        $user->getDisplayedFamilyName(),
                        $user->getId(),
                        $team->getId(),
                        $team->getTeamName(),
                        $team->getRegistrationSection(),
                        $team->getRotatingSection()
                    ]);
                    $csvdata .= $nl;
                }
            }
        }
        $filename = $this->core->getConfig()->getCourse() . "_" . $gradeable_id . "_teams.csv";
        $this->core->getOutput()->renderFile($csvdata, $filename);
    }

    /**
     * Randomly redistributes teams with members into Rotating Grading Sections
     * Evenly distributes them between all sections, giving extra teams to Sections numerically if necessary
     * Ex: 13 teams in 3 sections will always give Section 1: 5 teams; Section 2: 4 teams;  Section 3: 4 teams
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/teams/randomize_rotating")]
    public function randomizeTeamRotatingSections($gradeable_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($this->core->buildCourseUrl());
        }
        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->addErrorMessage('This gradeable is not an electronic file gradeable');
            $this->core->redirect($this->core->buildCourseUrl());
        }
        $section_count = $this->core->getQueries()->getMaxRotatingSection();
        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'grading', 'details']);
        $teams = $this->core->getQueries()->getTeamsWithMembersFromGradeableID($gradeable_id);

        //Does nothing if there are no sections or no teams
        if ($section_count <= 0 || empty($teams)) {
            $this->core->redirect($return_url);
            return;
        }

        shuffle($teams);

        $cur_group = 1;
        foreach ($teams as $team_id) {
            $this->core->getQueries()->updateTeamRotatingSection($team_id, $cur_group);
            $cur_group++;
            if ($cur_group > $section_count) {
                $cur_group = 1;
            }
        }

        $this->core->redirect($return_url);
    }

    /**
     * Handle requests to create individual teams via the AdminTeamForm
     */
    #[AccessControl(permission: "grading.electronic.submit_team_form")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/teams/new", methods: ["POST"])]
    public function adminTeamSubmit($gradeable_id) {
        $new_team = ($_POST['new_team'] ?? '') === 'true';
        $leader_id = $_POST['new_team_user_id'] ?? '';
        $team_id = $_POST['edit_team_team_id'] ?? '';
        $reg_section = $_POST['reg_section'] ?? 'NULL';
        $rot_section = $_POST['rot_section'] ?? 'NULL';

        if ($rot_section === 'NULL') {
            $rot_section = 0;
        }
        else {
            $rot_section = intval($rot_section);
        }

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->addErrorMessage('This gradeable is not an electronic file gradeable');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'grading', 'details']);

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($return_url);
        }

        $num_users = intval($_POST['num_users']);
        $user_ids = [];
        for ($i = 0; $i < $num_users; $i++) {
            $id = trim(htmlentities($_POST["user_id_{$i}"]));
            if (in_array($id, $user_ids)) {
                $this->core->addErrorMessage("ERROR: {$id} is already on this team");
                $this->core->redirect($return_url);
            }
            // filter empty strings and leader
            if ($id !== "" && $id !== $leader_id) {
                $user_ids[] = $id;
            }
        }

        // Load the user instances from the database
        $users = $this->core->getQueries()->getUsersById($user_ids);
        $invalid_members = array_diff($user_ids, array_keys($users));
        if (count($invalid_members) > 0) {
            $members_message = implode(', ', $invalid_members);
            $this->core->addErrorMessage("ERROR: {$members_message} are not valid User IDs");
            $this->core->redirect($return_url);
        }

        $team_name = $_POST['team_name'] ?? null;

        if ($new_team) {
            $leader = $this->core->getQueries()->getUserById($leader_id);
            try {
                $gradeable->createTeam($leader, $users, $reg_section, $rot_section, $team_name);
                $this->core->addSuccessMessage("Created New Team {$team_id}");
            }
            catch (\Exception $e) {
                $this->core->addErrorMessage("Team may not have been properly initialized: {$e->getMessage()}");
                $this->core->redirect($return_url);
            }
        }
        else {
            $team = $this->core->getQueries()->getTeamById($team_id);
            if ($team === null) {
                $this->core->addErrorMessage("ERROR: {$team_id} is not a valid Team ID");
                $this->core->redirect($return_url);
            }
            $new_team_name = false;
            if ($team_name !== $team->getTeamName()) {
                $new_team_name = true;
            }
            $team_members = $team->getMembers();
            $add_user_ids = [];
            foreach ($user_ids as $id) {
                if (!in_array($id, $team_members)) {
                    if ($this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id) !== null) {
                        $this->core->addErrorMessage("ERROR: {$id} is already on a team");
                        $this->core->redirect($return_url);
                    }
                    $add_user_ids[] = $id;
                }
            }
            $remove_user_ids = [];
            foreach ($team_members as $id) {
                if (!in_array($id, $user_ids)) {
                    $remove_user_ids[] = $id;
                }
            }

            $this->core->getQueries()->updateTeamRegistrationSection($team_id, $reg_section === 'NULL' ? null : $reg_section);
            $this->core->getQueries()->updateTeamRotatingSection($team_id, $rot_section === 0 ? null : $rot_section);
            $this->core->getQueries()->updateTeamName($team_id, $team_name);
            foreach ($add_user_ids as $id) {
                $this->core->getQueries()->declineAllTeamInvitations($gradeable_id, $id);
                $this->core->getQueries()->acceptTeamInvitation($team_id, $id);
            }
            foreach ($remove_user_ids as $id) {
                $this->core->getQueries()->leaveTeam($team_id, $id);
            }
            $this->core->addSuccessMessage("Updated Team {$team_id}");

            $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO") . " " . $this->core->getConfig()->getTimezone()->getName();
            $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $team_id, "user_assignment_settings.json");
            $json = FileUtils::readJsonFile($settings_file);
            if ($json === false) {
                $this->core->addErrorMessage("Failed to open settings file");
                $this->core->redirect($return_url);
            }
            foreach ($add_user_ids as $id) {
                $json["team_history"][] = ["action" => "admin_add_user", "time" => $current_time,
                "admin_user" => $this->core->getUser()->getId(), "added_user" => $id];
            }
            foreach ($remove_user_ids as $id) {
                $json["team_history"][] = ["action" => "admin_remove_user", "time" => $current_time,
                "admin_user" => $this->core->getUser()->getId(), "removed_user" => $id];
            }
            if ($new_team_name) {
                $json["team_history"][] = ["action" => "change_name", "time" => $current_time, "user" => $this->core->getUser()->getId()];
            }
            if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
                $this->core->addErrorMessage("Failed to write to team history to settings file");
            }
        }

        $this->core->redirect($return_url);
    }

    /**
     * Given a gradeable, the logged in user, and if the logged in user is a peer
     * determine if the user should be single, double, or unblind grading
     */

    private function amIBlindGrading($gradeable, $user, $peer) {
        if ($peer && $gradeable->getPeerBlind() === Gradeable::DOUBLE_BLIND_GRADING) {
            return "double";
        }

        if (($peer && $gradeable->getPeerBlind() === Gradeable::SINGLE_BLIND_GRADING) || ($gradeable->getLimitedAccessBlind() === Gradeable::SINGLE_BLIND_GRADING && $this->core->getUser()->getGroup() === User::GROUP_LIMITED_ACCESS_GRADER)) {
            return "single";
        }

        return "unblind";
    }

    /**
     * Display the electronic grading page
     *
     * @param string $who_id This is the user we wish to view, this field will only be passed on when the request originates
     *                  on the grading index page
     * @param string $from This is the user that was being viewed when a navigation button was clicked on the TA grading
     *                  interface.  Knowing who we were viewing allows us to decide who to view next.
     * @param null|string $to Used to determine the direction to move in, either 'prev' or 'next'
     *
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/grade")]
    public function showGrading(
        $gradeable_id,
        $who_id = '',
        $from = "",
        $to = null,
        $gradeable_version = null,
        $sort = "id",
        $direction = "ASC",
        $component_id = "-1",
        $anon_mode = false,
        $filter = 'default',
        $navigate_assigned_students_only = "true"
    ) {
        if (empty($this->core->getQueries()->getTeamsById([$who_id])) && $this->core->getQueries()->getUserById($who_id) == null) {
            $anon_mode = true;
        }
        /** @var Gradeable $gradeable */

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid Gradeable!');
            $this->core->redirect($this->core->buildCourseUrl());
        }
        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->addErrorMessage('This gradeable is not an electronic file gradeable');
            $this->core->redirect($this->core->buildCourseUrl());
        }
        if (!$gradeable->hasAutogradingConfig()) {
            $this->core->getOutput()->renderOutput(
                'Error',
                'unbuiltGradeable',
                $gradeable,
                "grades"
            );
            return;
        }
        $peer = $gradeable->hasPeerComponent() && $this->core->getUser()->getGroup() == User::GROUP_STUDENT;
        $team = $gradeable->isTeamAssignment();
        if ($gradeable->hasPeerComponent() && $this->core->getUser()->getGroup() == User::GROUP_STUDENT) {
            $peer = true;
        }
        $blind_grading = $this->amIBlindGrading($gradeable, $this->core->getUser(), $peer);

        // If $who_id is empty string then this request came from the TA grading interface navigation buttons
        // We must decide who to display prev/next and assign them to $who_id
        $order_all_sections = null;
        if ($who_id === '') {
            $order_grading_sections = new GradingOrder($this->core, $gradeable, $this->core->getUser());
            $order_grading_sections->sort($sort, $direction);

            // Only need to instantiate this order if the user is a full access grader
            // Limited access graders should never need the order that includes all sections
            if ($this->core->getUser()->accessFullGrading() && $navigate_assigned_students_only === "false") {
                $order_all_sections = new GradingOrder($this->core, $gradeable, $this->core->getUser(), true);
                $order_all_sections->sort($sort, $direction);
            }

            // Get the graded gradeable for the $from user
            $id_from_anon = $this->core->getQueries()->getSubmitterIdFromAnonId($from, $gradeable_id);
            if ($blind_grading !== "unblind" || $anon_mode) {
                if ($id_from_anon === null) {
                    $gradeableUrl = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'details']);
                    return new RedirectResponse($gradeableUrl);
                }
                $from_graded_gradeable = $this->tryGetGradedGradeable($gradeable, $id_from_anon, false);
            }
            else {
                $from_graded_gradeable = $this->tryGetGradedGradeable($gradeable, $from, false);
            }

            if ($from_graded_gradeable === false) {
                $this->core->redirect($this->core->buildCourseUrl(['gradeable', $gradeable_id, 'grading', 'details']));
            }

            // Get the user ID of the user we were viewing on the TA grading interface
            $from_id = $from_graded_gradeable->getSubmitter();

            // Determine the student to go to based on the button that was pressed
            // For full access graders, pressing the single arrow should navigate to the next submission, regardless
            // of if that submission is in their assigned section
            // Limited access graders should only be able to navigate to submissions in their assigned sections
            $goToStudent = null;
            if ($to === 'prev' && $navigate_assigned_students_only === "false" && $this->core->getUser()->accessFullGrading()) {
                $goToStudent = $order_all_sections->getPrevSubmitter($from_id, is_numeric($component_id) ? $component_id : -1, $filter);
            }
            elseif ($to === 'prev') {
                $goToStudent = $order_grading_sections->getPrevSubmitter($from_id, is_numeric($component_id) ? $component_id : -1, $filter);
            }
            elseif ($to === 'next' && $navigate_assigned_students_only === "false" && $this->core->getUser()->accessFullGrading()) {
                $goToStudent = $order_all_sections->getNextSubmitter($from_id, is_numeric($component_id) ? $component_id : -1, $filter);
            }
            elseif ($to === 'next') {
                $goToStudent = $order_grading_sections->getNextSubmitter($from_id, is_numeric($component_id) ? $component_id : -1, $filter);
            }
            // Reassign who_id
            if ($goToStudent !== null) {
                $who_id = $goToStudent->getId();
                if ($blind_grading || $anon_mode) {
                    $who_id = $goToStudent->getAnonId($gradeable_id);
                }
            }
            if (empty($who_id)) {
                $message = "You've reached the ";
                $message .= $to === 'prev' ? " start" : " end";
                $message .= " of";
                $message .= $navigate_assigned_students_only !== "false" ? " your assigned sections" : " the list";
                $message .= $filter !== 'default' ? " (using filter '" . $filter . "')." : ".";
                $this->core->addSuccessMessage($message);
                $this->core->redirect($this->core->buildCourseUrl(['gradeable', $gradeable_id, 'grading', 'details'])  . '?' . http_build_query(['sort' => $sort, 'direction' => $direction]));
            }
        }
        // Get the graded gradeable for the submitter we are requesting
        $graded_gradeable = false;
        $id_from_anon = $this->core->getQueries()->getSubmitterIdFromAnonId($who_id, $gradeable_id);
        if ($blind_grading !== "unblind" || $anon_mode) {
            $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $id_from_anon, false);
        }
        else {
            $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $who_id, false);
        }
        if ($graded_gradeable === false) {
            $this->core->redirect($this->core->buildCourseUrl(['gradeable', $gradeable_id, 'grading', 'details'])  . '?' . http_build_query(['sort' => $sort, 'direction' => $direction]));
            $peer = false;
        }

        $gradeableUrl = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'details']);
        $this->core->getOutput()->addBreadcrumb("{$gradeable->getTitle()} Grading", $gradeableUrl);
        $indexUrl = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'grading', 'details']);
        $this->core->getOutput()->addBreadcrumb('Grading Interface ' . GradingOrder::getGradingOrderMessage($sort, $direction));
        $graded = 0;
        $total = 0;
        $total_submitted = 0;
        $non_late_total_submitted = 0;
        $non_late_graded = 0;
        $include_withdrawn_students = ($_COOKIE['include_withdrawn_students'] ?? 'omit') === 'include';

        // Assure late day cache is calculated
        $this->core->getQueries()->generateLateDayCacheForUsers();
        $section_key = ($gradeable->isGradeByRegistration() ? 'registration_section' : 'rotating_section');
        if ($peer) {
            $total = $gradeable->getPeerGradeSet();
            $graded = $this->core->getQueries()->getNumGradedPeerComponents($gradeable->getId(), $this->core->getUser()->getId()) / count($gradeable->getPeerComponents());
            $non_late_total_submitted = $total_submitted;
            $non_late_graded = $graded;
        }
        elseif ($gradeable->isGradeByRegistration()) {
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if ($this->core->getAccess()->canI("grading.electronic.grade.if_no_sections_exist") && $sections == null) {
                $sections = $this->core->getQueries()->getRegistrationSections();
                for ($i = 0; $i < count($sections); $i++) {
                    $sections[$i] = $sections[$i]['sections_registration_id'];
                }
            }
            if ($team) {
                $graded = array_sum($this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $team, $include_withdrawn_students));
                $total = array_sum($this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, $section_key));
                $total_submitted = array_sum($this->core->getQueries()->getSubmittedTeamCountByGradingSections($gradeable_id, $sections, $section_key));
                $late_submitted = $this->core->getQueries()->getBadTeamSubmissionsByGradingSection($gradeable_id, $sections, $section_key);
                $non_late_total_submitted = $total_submitted - array_sum($late_submitted);
                $late_graded = $this->core->getQueries()->getBadGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $gradeable->isTeamAssignment(), $include_withdrawn_students);
                $non_late_graded = $graded - array_sum($late_graded);
            }
            else {
                $graded = array_sum($this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $team, $include_withdrawn_students));
                $total = array_sum($this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key, $include_withdrawn_students));
                $total_submitted = array_sum($this->core->getQueries()->getTotalSubmittedUserCountByGradingSections($gradeable_id, $sections, $section_key, $include_withdrawn_students));
                $late_submitted = $this->core->getQueries()->getBadUserSubmissionsByGradingSection($gradeable_id, $sections, $section_key);
                $non_late_total_submitted = $total_submitted - array_sum($late_submitted);
                $late_graded = $this->core->getQueries()->getBadGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $gradeable->isTeamAssignment(), $include_withdrawn_students);

                $non_late_graded = $graded - array_sum($late_graded);
            }
        }
        else {
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            if ($this->core->getAccess()->canI("grading.electronic.grade.if_no_sections_exist") && $sections == null) {
                $sections = $this->core->getQueries()->getRotatingSections();
                for ($i = 0; $i < count($sections); $i++) {
                    $sections[$i] = $sections[$i]['sections_rotating_id'];
                }
            }
            if ($team) {
                //$total = array_sum($this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, 'rotating_section'));
                $late_submitted = $this->core->getQueries()->getBadTeamSubmissionsByGradingSection($gradeable_id, $sections, $section_key);
                $total_submitted = array_sum($this->core->getQueries()->getSubmittedTeamCountByGradingSections($gradeable_id, $sections, $section_key));
            }
            else {
                $late_submitted = $this->core->getQueries()->getBadUserSubmissionsByGradingSection($gradeable_id, $sections, $section_key);
                $total_submitted = array_sum($this->core->getQueries()->getTotalSubmittedUserCountByGradingSections($gradeable->getId(), $sections, $section_key, $include_withdrawn_students));
            }
            $late_graded = $this->core->getQueries()->getBadGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $gradeable->isTeamAssignment(), $include_withdrawn_students);
            $non_late_total_submitted = $total_submitted - array_sum($late_submitted);
            $graded = array_sum($this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $team, $include_withdrawn_students));
            $non_late_graded = $graded - array_sum($late_graded);
        }
        //multiplies users and the number of components a gradeable has together
        $total_submitted = $total_submitted * count($gradeable->getNonPeerComponents());
        if ($total_submitted == 0) {
            $progress = 100;
        }
        else {
            $progress = round(($graded / $total_submitted) * 100, 1);

            if (($_COOKIE["include_bad_submissions"] ?? 'omit') === 'include') {
                $progress = round(($non_late_graded / $non_late_total_submitted) * 100, 1);
            }
        }


        if (!$this->core->getAccess()->canI("grading.electronic.grade", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
            $this->core->addErrorMessage("ERROR: You do not have access to grade the requested student.");
            $this->core->redirect($this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'status']));
        }

        $show_verify_all = false;
        //check if verify all button should be shown or not
        foreach ($gradeable->getComponents() as $component) {
            $graded_component = $graded_gradeable->getOrCreateTaGradedGradeable()->getGradedComponent($component, $this->core->getUser());
            if ($graded_component === null) {
                continue;
            }
            if ($graded_component->getGrader()->getId() !== $this->core->getUser()->getId() && $graded_component->getVerifierId() === '') {
                $show_verify_all = true;
                break;
            }
        }
        $can_inquiry = $this->core->getAccess()->canI("grading.electronic.grade_inquiry", ['graded_gradeable' => $graded_gradeable]);
        $can_verify = $this->core->getAccess()->canI("grading.electronic.verify_grader");
        $show_verify_all = $show_verify_all && $can_verify;

        if ($can_inquiry) {
            $this->core->authorizeWebSocketToken([
                'page' => 'grade_inquiry',
                'gradeable_id' => $gradeable_id,
                'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
            ]);
        }

        $show_silent_edit = $this->core->getAccess()->canI("grading.electronic.silent_edit");

        $display_version = intval($gradeable_version ?? '0');
        if ($display_version <= 0) {
            $display_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        }

        $late_days_users = [];
        if ($gradeable->isTeamAssignment()) {
            // If its a team assignment, use the leader for late days...
            $late_days_users = $graded_gradeable->getSubmitter()->getTeam()->getMemberUsers();
        }
        else {
            $late_days_users[] = $graded_gradeable->getSubmitter()->getUser();
        }

        $late_status = null;  // Assume its good
        $rollback_submission = PHP_INT_MAX;

        // Get the "worst" status from all users in the submission
        foreach ($late_days_users as $user) {
            $ld = new LateDays($this->core, $user, [$graded_gradeable]);
            $ldi = $ld->getLateDayInfoByGradeable($gradeable);

            // Skip null entries
            if ($ldi === null) {
                continue;
            }

            $late_status = max($ldi->getStatus(), $late_status ?? 0);
            $rollback_submission = min($rollback_submission, $ld->getLatestValidVersion($graded_gradeable));
        }

        if ($late_status === null) {
            $late_status = LateDayInfo::STATUS_GOOD;  // Assume its good
        }

        $logger_params = [
            "course_semester" => $this->core->getConfig()->getTerm(),
            "course_name" => $this->core->getDisplayedCourseName(),
            "gradeable_id" => $gradeable_id,
            "grader_id" => $this->core->getUser()->getId(),
            "submitter_id" => $who_id,
            "action" => "VIEW_PAGE",
        ];
        Logger::logTAGrading($logger_params);

        $user = $this->core->getUser();
        $anon_mode =
        ($user->getGroup() === User::GROUP_LIMITED_ACCESS_GRADER && $gradeable->getLimitedAccessBlind() === Gradeable::SINGLE_BLIND_GRADING) ||
        ($user->getGroup() < 3 && (
            (isset($_COOKIE['anon_mode']) && $_COOKIE['anon_mode'] === 'on') ||
            (!isset($_COOKIE['anon_mode']) && $gradeable->getInstructorBlind() === Gradeable::SINGLE_BLIND_GRADING)
        ));

        $submitter_itempool_map = $this->getItempoolMapForSubmitter($gradeable, $graded_gradeable->getSubmitter()->getId());
        $solution_ta_notes = $this->getSolutionTaNotesForGradeable($gradeable, $submitter_itempool_map) ?? [];

        $this->core->getOutput()->addInternalCss('forum.css');
        $this->core->getOutput()->addInternalCss('electronic.css');

        $this->core->getOutput()->addInternalJs('forum.js');
        $this->core->getOutput()->addInternalCss('grade-inquiry.css');
        $this->core->getOutput()->addInternalJs('grade-inquiry.js');
        $this->core->getOutput()->addInternalJs('websocket.js');
        $show_hidden = $this->core->getAccess()->canI("autograding.show_hidden_cases", ["gradeable" => $gradeable]);
        $this->core->getOutput()->renderOutput(['grading', 'ElectronicGrader'], 'hwGradingPage', $gradeable, $graded_gradeable, $display_version, $progress, $show_hidden, $can_inquiry, $can_verify, $show_verify_all, $show_silent_edit, $late_status, $rollback_submission, $sort, $direction, $who_id, $solution_ta_notes, $submitter_itempool_map, $anon_mode, $blind_grading);
        $this->core->getOutput()->renderOutput(['grading', 'ElectronicGrader'], 'popupStudents');
        $this->core->getOutput()->renderOutput(['grading', 'ElectronicGrader'], 'popupMarkConflicts');
        $this->core->getOutput()->renderOutput(['grading', 'ElectronicGrader'], 'popupSettings');
    }
    /**
     * Route for getting information about a individual grader
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/graded_gradeable")]
    public function ajaxGetGradedGradeable($gradeable_id, $anon_id = '', $all_peers = false) {
        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }
        // Make sure that this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }

        $all_peers = ($all_peers === "true");

        $grader = $this->core->getUser();

        // Get user id from the anon id
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id);
        if ($submitter_id === false) {
            return;
        }

        $section = null;

        if ($gradeable->isGradeByRegistration()) {
            $section = $this->core->getQueries()->getSubmitterById($submitter_id)->getRegistrationSection();
        }
        else {
            $section = $this->core->getQueries()->getSubmitterById($submitter_id)->getRotatingSection();
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }

        // checks if user has permission
        if (!$this->core->getAccess()->canI("grading.electronic.grade", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "section" => $section])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get graded gradeable');
            return;
        }

        // Check if user has permission to view all peer grades
        /*if ($all_peers) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get view peer panel');
            return;
        }*/

        // Get / create the TA grade
        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

        try {
            // Once we've parsed the inputs and checked permissions, perform the operation
            $response_data = null;
            if ($ta_graded_gradeable !== null) {
                $response_data = $this->getGradedGradeable($ta_graded_gradeable, $grader, $all_peers);
            }
            $this->core->getOutput()->renderJsonSuccess($response_data);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    public function getGradedGradeable(TaGradedGradeable $ta_graded_gradeable, User $grader, $all_peers = false) {

        // Passing null returns grading for all graders.
        $grading_done_by = ($all_peers ? null : $grader);
        $response_data = $ta_graded_gradeable->toArray($grading_done_by);
        $response_data_with_peer = $ta_graded_gradeable->toArray();
        $graded_gradeable = $ta_graded_gradeable->getGradedGradeable();
        $gradeable = $graded_gradeable->getGradeable();
        $submitter = $graded_gradeable->getSubmitter()->getId();
        $combined_score = 0;
        foreach ($response_data_with_peer['peer_scores'] as $score) {
            $combined_score += $score;
        }
        //remove non peer component scores from combined score to get the combined peer score
        $combined_peer_score = $combined_score - $ta_graded_gradeable->getTotalTaScore($this->core->getUser());

        // If there is autograding, also send that information TODO: this should be restricted to non-peer
        if (count($gradeable->getAutogradingConfig()->getPersonalizedTestcases($submitter)) > 1) {
            // NOTE/REDESIGN FIXME: We might have autograding that is
            // penalty only.  The available positive autograding
            // points might be zero.  Testing for autograding > 1 is
            // ignoring the submission limit test case... but this is
            // also imperfect.  We want to render the column if any
            // student has received the penalty.  But if no one has
            // received the penalty maybe we omit it?  (expensive?/confusing?)
            // See also note in ElectronicGraderView.php
            // if ($gradeable->getAutogradingConfig()->anyPoints()) {
            $response_data['auto_grading_total'] = $gradeable->getAutogradingConfig()->getTotalNonExtraCredit();

            // Furthermore, if the user has a grade, send that information
            if ($graded_gradeable->getAutoGradedGradeable()->hasActiveVersion()) {
                $response_data['auto_grading_earned'] = $graded_gradeable->getAutoGradedGradeable()->getActiveVersionInstance()->getTotalPoints();
            }
        }
        //send ta score information
        $response_data['ta_grading_total'] = $gradeable->getTaPoints();
        if ($gradeable->hasPeerComponent()) {
            $response_data['ta_grading_earned'] = $ta_graded_gradeable->getTotalTaScore($this->core->getUser());
            $response_data['see_peer_grade'] = $ta_graded_gradeable->getTotalPeerScore($grading_done_by);
            $response_data['peer_grade_earned'] = $ta_graded_gradeable->getTotalScore($this->core->getUser());
            $response_data['peer_total'] = $gradeable->getPeerPoints();
            $response_data['user_group'] = $this->core->getUser()->getGroup();
            $response_data['peer_gradeable'] = true;
            $response_data['combined_peer_score'] = $combined_peer_score;
        }
        else {
            $response_data['ta_grading_earned'] = $ta_graded_gradeable->getTotalScore(null);
            $response_data['user_group'] = $this->core->getUser()->getGroup();
            $response_data['peer_gradeable'] = false;
        }

        $response_data['anon_id'] = $graded_gradeable->getSubmitter()->getAnonId($gradeable->getId());

        $response_data['itempool_items'] = [];
        $components = $gradeable->getComponents();
        $submitter_itempool_map = $this->getItempoolMapForSubmitter($gradeable, $submitter);
        foreach ($components as $key => $value) {
            $response_data['itempool_items'][$value->getId()] = $value->getItempool() === '' ? '' : $submitter_itempool_map[$value->getItempool()];
        }
        $graders = $graded_gradeable->getActiveGraders();
        $timestamps = $graded_gradeable->getActiveGradersTimestamps();
        $graders_names = $graded_gradeable->getActiveGradersNames();

        if ($gradeable->hasPeerComponent() && !$this->core->getUser()->accessGrading()) {
            // If the user is a student, we don't want to show the peer grader's name
            $response_data['active_graders'] = [];
            $response_data['active_graders_timestamps'] = [];
            return $response_data;
        }
        // Ensure the current grader is not in the list of active graders
        foreach ($graders as $component_id => $component_graders) {
            if (isset($timestamps[$component_id]) && isset($graders_names[$component_id])) {
                for ($i = 0; $i < count($component_graders); $i++) {
                    if ($component_graders[$i] === $grader->getId()) {
                        // Use array_splice to remove the grader from the list of active graders
                        array_splice($graders_names[$component_id], $i, 1);
                        array_splice($timestamps[$component_id], $i, 1);
                        break;
                    }
                }
            }
        }
        $response_data['active_graders'] = $graders_names;
        $response_data['active_graders_timestamps'] = $timestamps;

        return $response_data;
    }

    /**
     * Route for saving the marks the submitter received for a component
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/graded_gradeable/graded_component", methods: ["POST"])]
    public function ajaxSaveGradedComponent($gradeable_id) {
        $anon_id = $_POST['anon_id'] ?? '';
        $component_id = $_POST['component_id'] ?? '';
        $custom_message = $_POST['custom_message'] ?? null;
        $custom_points = $_POST['custom_points'] ?? null;
        $component_version = $_POST['graded_version'] ?? null;
        // Optional marks parameter
        $marks = $_POST['mark_ids'] ?? [];

        // Validate required parameters
        if ($custom_message === null) {
            $this->core->getOutput()->renderJsonFail('Missing custom_message parameter');
            return;
        }
        if ($custom_points === null) {
            $this->core->getOutput()->renderJsonFail('Missing custom_points parameter');
            return;
        }
        if (!is_numeric($custom_points)) {
            $this->core->getOutput()->renderJsonFail('Invalid custom_points parameter');
            return;
        }
        if ($component_version === null) {
            $this->core->getOutput()->renderJsonFail('Missing graded_version parameter');
            return;
        }
        if (!ctype_digit($component_version)) {
            $this->core->getOutput()->renderJsonFail('Invalid graded_version parameter');
            return;
        }

        // Convert the mark ids to integers
        $numeric_mark_ids = [];
        foreach ($marks as $mark) {
            if (!ctype_digit($mark)) {
                $this->core->getOutput()->renderJsonFail('One of provided mark ids was invalid');
                return;
            }
            $numeric_mark_ids[] = intval($mark);
        }
        $marks = $numeric_mark_ids;

        // Parse the strings into ints/floats
        $component_version = intval($component_version);
        $custom_points = floatval($custom_points);

        // Optional Parameters
        $silent_edit = ($_POST['silent_edit'] ?? 'false') === 'true';

        $grader = $this->core->getUser();

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }
        // Make sure that this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        // get the component
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) {
            return;
        }

        // Get user id from the anon id
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id);
        if ($submitter_id === false) {
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }


        // checks if user has permission
        if (!$this->core->getAccess()->canI("grading.electronic.save_graded_component", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "component" => $component])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save component/marks');
            return;
        }

        //don't allow custom marks if they are disabled
        if ($custom_message != null || $custom_points != null) {
            if (!$gradeable->getAllowCustomMarks()) {
                $this->core->getOutput()->renderJsonFail('Custom marks are disabled for this assignment');
                return;
            }
        }

        //don't allow peer graders to save custom marks no matter how gradeable is configured
        if (($custom_message != null || $custom_points != null) && $gradeable->hasPeerComponent()) {
            if ($this->core->getUser()->getGroup() == User::GROUP_STUDENT) {
                $this->core->getOutput()->renderJsonFail('Insufficient permissions to save component/marks');
                return;
            }
        }
        // Check if the user can silently edit assigned marks
        if (!$this->core->getAccess()->canI('grading.electronic.silent_edit')) {
            $silent_edit = false;
        }

        $logger_params = [
            "course_semester" => $this->core->getConfig()->getTerm(),
            "course_name" => $this->core->getDisplayedCourseName(),
            "gradeable_id" => $gradeable_id,
            "grader_id" => $this->core->getUser()->getId(),
            "component_id" => $component_id,
            "action" => "SAVE_COMPONENT",
            "submitter_id" => $submitter_id
        ];
        Logger::logTAGrading($logger_params);

        // Get / create the TA grade
        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

        // Get / create the graded component
        $graded_component = $ta_graded_gradeable->getOrCreateGradedComponent($component, $grader, true);

        try {
            // Once we've parsed the inputs and checked permissions, perform the operation
            $this->saveGradedComponent(
                $ta_graded_gradeable,
                $graded_component,
                $grader,
                $custom_points,
                $custom_message,
                $marks,
                $component_version,
                !$silent_edit
            );
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    /**
     * @param string $gradeable_id
     * @param string $anon_id
     * @param string $component_id
     * @param GradingAction $action
     * @return JsonResponse
     */
    public function changeComponentGraders(string $gradeable_id, string $anon_id, ?string $component_id = null, ?GradingAction $action = null) {
        $grader = $this->core->getUser();

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return JsonResponse::getErrorResponse('Failed to get gradeable');
        }
        // Make sure that this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            return JsonResponse::getErrorResponse('This gradeable is not an electronic file gradeable');
        }
        // get the component
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) {
            return JsonResponse::getErrorResponse('Failed to get component');
        }

        // Get user id from the anon id
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id);
        if ($submitter_id === false) {
            return JsonResponse::getErrorResponse('Failed to get submitter id');
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return JsonResponse::getErrorResponse('Failed to get graded gradeable');
        }


        // checks if user has permission
        if (!$this->core->getAccess()->canI("grading.electronic.save_graded_component", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "component" => $component])) {
            return JsonResponse::getErrorResponse('Insufficient permissions to save component/marks');
        }

        $logger_params = [
            "course_semester" => $this->core->getConfig()->getTerm(),
            "course_name" => $this->core->getDisplayedCourseName(),
            "gradeable_id" => $gradeable_id,
            "grader_id" => $this->core->getUser()->getId(),
            "component_id" => $component_id,
            "action" => $action === GradingAction::OPEN_COMPONENT ? "OPEN_COMPONENT" : "CLOSE_COMPONENT",
            "submitter_id" => $submitter_id
        ];
        Logger::logTAGrading($logger_params);

        $graders = $graded_gradeable->getActiveGraders();
        $timestamps = $graded_gradeable->getActiveGradersTimestamps();
        $graders_names = $graded_gradeable->getActiveGradersNames();
        if ($action === GradingAction::OPEN_COMPONENT) {
            $this->core->getQueries()->addComponentGrader($component, $gradeable->isTeamAssignment(), $grader->getId(), $submitter_id);
        }
        else {
            $this->core->getQueries()->removeComponentGrader($component, $grader->getId(), $submitter_id);
        }

        if ($gradeable->hasPeerComponent() && !$this->core->getUser()->accessGrading()) {
            // return empty data for peers
            return JsonResponse::getSuccessResponse(['active_graders' => [], 'active_graders_timestamps' => []]);
        }
        // If there are no graders for this component, use an empty array
        if (isset($graders[$component_id])) {
            // Ensure the current grader is not in the list of active graders
            for ($i = 0; $i < count($graders[$component_id]); $i++) {
                if ($graders[$component_id][$i] === $grader->getId()) {
                    array_splice($timestamps[$component_id], $i, 1);
                    array_splice($graders_names[$component_id], $i, 1);
                    break;
                }
            }
        }

        return JsonResponse::getSuccessResponse(['active_graders' => $graders_names, 'active_graders_timestamps' => $timestamps]);
    }

    /**
     * @param string $gradeable_id
     * @return JsonResponse|void
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/graded_gradeable/open_component", methods: ["POST"])]
    public function ajaxOpenGradedComponent($gradeable_id) {
        return $this->changeComponentGraders($gradeable_id, $_POST['anon_id'] ?? '', $_POST['component_id'] ?? '', GradingAction::OPEN_COMPONENT);
    }

    /**
     * @param string $gradeable_id
     * @return JsonResponse|void
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/graded_gradeable/close_component", methods: ["POST"])]
    public function ajaxCloseGradedComponent($gradeable_id) {
        return $this->changeComponentGraders($gradeable_id, $_POST['anon_id'] ?? '', $_POST['component_id'] ?? '', GradingAction::CLOSE_COMPONENT);
    }

    public function saveGradedComponent(TaGradedGradeable $ta_graded_gradeable, GradedComponent $graded_component, User $grader, float $custom_points, string $custom_message, array $mark_ids, int $component_version, bool $overwrite) {
        // Only update the grader if we're set to overwrite it
        if ($overwrite) {
            $graded_component->setGrader($grader);
        }
        $version_updated = $graded_component->getGradedVersion() !== $component_version;
        if ($version_updated) {
            $graded_component->setGradedVersion($component_version);
        }
        $graded_component->setComment($custom_message);
        $graded_component->setScore($custom_points);
        $graded_component->setGradeTime($this->core->getDateTimeNow());

        // Set the marks the submitter received
        $graded_component->setMarkIds($mark_ids);

        // Check if this graded component should be deleted
        if (
            count($graded_component->getMarkIds()) === 0
            && $graded_component->getScore() === 0.0
            && $graded_component->getComment() === ''
        ) {
            $ta_graded_gradeable->deleteGradedComponent($graded_component->getComponent(), $graded_component->getGrader());
            $graded_component = null;
        }
        else {
            //change the component to be unverified after changing a mark
            if ($graded_component->isMarksModified()) {
                $graded_component->setVerifier();
                $graded_component->setVerifyTime(null);
            }
        }

        // TODO: is this desirable
        // Reset the user viewed date since we updated the grade
        $ta_graded_gradeable->resetUserViewedDate();

        // Finally, save the changes to the database
        $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);
        $submitter = $ta_graded_gradeable->getGradedGradeable()->getSubmitter();
        if ($submitter->isTeam()) {
            $this->core->getQueries()->clearTeamViewedTime($submitter->getId());
        }
    }

    /**
     * Route for saving the marks the submitter received for a component
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/graded_gradeable/change_grade_version", methods: ["POST"])]
    public function ajaxUpdateGradedVersionForStudent(string $gradeable_id): JsonResponse {
        $anon_id = $_POST['anon_id'] ?? null;
        $graded_version = intval($_POST['graded_version'] ?? null);
        $component_ids = $_POST['component_ids'] ?? [];

        if ($anon_id === null) {
            return JsonResponse::getFailResponse('Missing anon_id parameter');
        }
        if ($graded_version < 1) {
            return JsonResponse::getFailResponse('Invalid graded_version parameter');
        }

        if (count($component_ids) < 1) {
            return JsonResponse::getFailResponse('Missing component_ids parameter');
        }

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            return JsonResponse::getFailResponse('Missing gradeable_id parameter');
        }
        // Get user id from the anon id
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id, false);
        if ($submitter_id === false) {
            return JsonResponse::getFailResponse('Missing anon_id parameter');
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id, false);
        if ($graded_gradeable === false) {
            return JsonResponse::getFailResponse('Missing gradeable_id parameter');
        }


        // check if all the components provided are real and the user has permission
        foreach ($component_ids as $component_id) {
            $component = $this->tryGetComponent($gradeable, $component_id);
            if ($component === false) {
                return JsonResponse::getFailResponse("Invalid component id \"$component_id\"");
            }
            elseif (!$this->core->getAccess()->canI("grading.electronic.save_graded_component", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "component" => $component])) {
                return JsonResponse::getFailResponse("Insufficient permissions to change graded version of component $component_id");
            }
        }

        $logger_params = [
            "course_semester" => $this->core->getConfig()->getTerm(),
            "course_name" => $this->core->getDisplayedCourseName(),
            "gradeable_id" => $gradeable_id,
            "grader_id" => $this->core->getUser()->getId(),
            "component_ids" => implode(",", $component_ids),
            "action" => "CHANGE_GRADEABLE_GRADED_VERSION",
            "submitter_id" => $submitter_id
        ];
        Logger::logTAGrading($logger_params);

        try {
            $this->core->getQueries()->changeGradedVersionOfComponents($gradeable_id, $submitter_id, $graded_version, $component_ids);
            return JsonResponse::getSuccessResponse();
        }
        catch (\InvalidArgumentException $e) {
            return JsonResponse::getFailResponse($e->getMessage());
        }
        catch (\Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }
    }
    /**
     * Route for getting the student's program output for the diff-viewer
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/student_output")]
    public function ajaxGetStudentOutput($gradeable_id, $who_id = '', $version = '', $index = '') {
        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }

        // Get the graded gradeable
        $who_id = $this->core->getQueries()->getSubmitterIdFromAnonId($who_id, $gradeable_id);
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $who_id);
        if ($graded_gradeable === false) {
            return;
        }

        // get the requested version
        $version_instance = $this->tryGetVersion($graded_gradeable->getAutoGradedGradeable(), $version);
        if ($version_instance === false) {
            return;
        }

        // Get the requested testcase
        $testcase = $this->tryGetTestcase($version_instance, $index);
        if ($testcase === false) {
            return;
        }

        // Check access
        if (!$this->core->getAccess()->canI("autograding.load_checks", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
            // TODO: streamline permission error strings
            $this->core->getOutput()->renderJsonFail('You have insufficient permissions to access this command');
            return;
        }

        try {
            //display hidden testcases only if the user can view the entirety of this gradeable.
            $can_view_hidden = $this->core->getAccess()->canI("autograding.show_hidden_cases", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable]);
            $popup_css = "diff-viewer.css";
            $this->core->getOutput()->renderJsonSuccess(
                $this->core->getOutput()->renderTemplate(
                    'AutoGrading',
                    'loadAutoChecks',
                    $graded_gradeable,
                    $version_instance,
                    $testcase,
                    $popup_css,
                    $who_id,
                    $can_view_hidden
                )
            );
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }
    /**
     * Route for saving the general comment for the gradeable
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/comments", methods: ["POST"])]
    public function ajaxSaveOverallComment($gradeable_id) {
        $anon_id = $_POST['anon_id'] ?? '';
        $comment = $_POST['overall_comment'] ?? '';
        $grader = $this->core->getUser();

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }

        // Get user id from the anon id
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id);
        if ($submitter_id === false) {
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }

        // Check access
        if (!$this->core->getAccess()->canI("grading.electronic.save_general_comment", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save component general comment');
            return;
        }

        // Get the Ta graded gradeable
        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

        try {
            // Once we've parsed the inputs and checked permissions, perform the operation
            $this->saveOverallComment($ta_graded_gradeable, $comment, $grader);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    public function saveOverallComment(TaGradedGradeable $ta_graded_gradeable, string $comment, User $grader) {
        // Set the comment
        $ta_graded_gradeable->setOverallComment($comment, $grader->getId());

        // New info, so reset the user viewed date
        $ta_graded_gradeable->resetUserViewedDate();

        // Finally, save the graded gradeable
        $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);
        $submitter = $ta_graded_gradeable->getGradedGradeable()->getSubmitter();
        if ($submitter->isTeam()) {
            $this->core->getQueries()->clearTeamViewedTime($submitter->getId());
        }
    }

    /**
     * Route for getting a GradedComponent
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/graded_gradeable/graded_component", methods: ["GET"])]
    public function ajaxGetGradedComponent($gradeable_id, $anon_id = '', $component_id = '') {
        $grader = $this->core->getUser();

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }

        // get the component
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) {
            return;
        }

        // Get user id from the anon id
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id);
        if ($submitter_id === false) {
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }

        // checks if user has permission
        if (!$this->core->getAccess()->canI("grading.electronic.view_component_grade", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "component" => $component])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get component data');
            return;
        }

        // Get / create the TA grade
        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

        // Get / create the graded component
        $graded_component = $ta_graded_gradeable->getGradedComponent($component, $grader);

        $logger_params = [
            "course_semester" => $this->core->getConfig()->getTerm(),
            "course_name" => $this->core->getDisplayedCourseName(),
            "gradeable_id" => $gradeable_id,
            "grader_id" => $this->core->getUser()->getId(),
            "component_id" => $component_id,
            "action" => "OPEN_COMPONENT",
            "submitter_id" => $submitter_id
        ];
        Logger::logTAGrading($logger_params);


        try {
            // Once we've parsed the inputs and checked permissions, perform the operation
            $response_data = null;
            if ($graded_component !== null) {
                $response_data = $graded_component->toArray();
            }
            $this->core->getOutput()->renderJsonSuccess($response_data);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    /**
     * Route for getting the overall comment for the currently logged in user.
     * @param string $gradeable_id
     * @param string $anon_id, the anonymous id of the student
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/comments", methods: ["GET"])]
    public function ajaxGetOverallComment($gradeable_id, $anon_id = '') {
        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }

        // Get user id from the anon id
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id);
        if ($submitter_id === false) {
            return;
        }
        $commenter = $this->core->getUser();

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }

        // checks if user has permission
        if (!$this->core->getAccess()->canI("grading.electronic.get_gradeable_comment", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save gradeable comment');
            return;
        }

        // Get / create the TA grade
        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

        // Once we've parsed the inputs and checked permissions, perform the operation
        $this->core->getOutput()->renderJsonSuccess($ta_graded_gradeable->getOverallComments($commenter));
    }
    /**
     * @param Gradeable $gradeable
     * @param string $who_id
     * @return array
     */
    protected function getItempoolMapForSubmitter($gradeable, $who_id) {
        $user_item_map = [];
        // read config file
        $gradeable_config = $gradeable->getAutogradingConfig();

        $notebook_config = $gradeable_config->getNotebookConfig();
        $hashes = $gradeable_config->getUserSpecificNotebook($who_id)->getHashes();
        $que_idx = 0;
        // loop through the notebook key, and find from_pool key in each object (or question)
        foreach ($notebook_config as $key => $item) {
            // store those question which are having count(from_pool array) > 1
            if (isset($item['type']) && $item['type'] === 'item') {
                $item_id = !empty($item['item_label']) ? $item["item_label"] : "item";
                $item_id = isset($user_item_map[$item_id]) ? $item_id . '_' . $key : $item_id;
                $selected_idx = $item["user_item_map"][$who_id] ?? null;
                if (is_null($selected_idx)) {
                    $selected_idx = $hashes[$que_idx] % count($item['from_pool']);
                    $que_idx++;
                }
                $user_item_map[$item_id] = $item['from_pool'][$selected_idx];
            }
        }
        return $user_item_map;
    }

    /**
     * @param Gradeable $gradeable
     * @param array $submitter_itempool_map
     * @return array
     */
    public function getSolutionTaNotesForGradeable($gradeable, $submitter_itempool_map) {
        $solutions = [];
        try {
            $result_rows = $this->core->getQueries()->getSolutionForAllComponentIds($gradeable->getId());

            foreach ($result_rows as $row) {
                foreach ($row as $values) {
                    // itempool_name === '' indicates that the component is not linked with the itempool
                    if (empty($values['itempool_name']) || $submitter_itempool_map[$values['itempool_name']] === $values['itempool_item']) {
                        $solutions[$values['component_id']] = $values;
                        break;
                    }
                }
            }
        }
        catch (\Exception $exception) {
            $error = $exception->getMessage();
            $this->core->getOutput()->renderResultMessage("Something went wrong while fetching solutions " . $error, false);
        }
        return $solutions;
    }

    /**
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/solution_ta_notes", methods: ["POST"])]
    public function updateSolutionTaNotes($gradeable_id) {
        $solution_text = $_POST['solution_text'] ?? '';
        $component_id = $_POST['component_id'];
        $gradeable = $this->tryGetGradeable($gradeable_id);
        $author_id = $this->core->getUser()->getId();
        $itempool_item = $_POST['itempool_item'] ?? '';
        $error = "";
        $solution_row = [];
        $componentItempoolInfo = $this->core->getQueries()->componentItempoolInfo($gradeable_id, $component_id);
        if (!$gradeable) {
            $error = "Invalid Gradeable ID given!";
        }
        elseif ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $error = 'This gradeable is not an electronic file gradeable';
        }
        elseif ($componentItempoolInfo['is_linked'] && empty($itempool_item)) {
            //Itempool must be non-empty when component is linked with the itempool
            $error = 'This component expects only non-empty itempool-item!';
        }
        elseif (!$componentItempoolInfo['is_linked'] && !empty($itempool_item)) {
            // Itempool item passed when the component is not linked with itempool
            $error = 'This Component expects only non-empty itempool-item!' . json_encode($componentItempoolInfo) . $itempool_item;
        }
        elseif (!$this->core->getAccess()->canI("grading.electronic.view_solution", ["gradeable" => $gradeable])) {
            $error = 'Insufficient permissions to update solution';
        }
        else {
            try {
                $this->core->getQueries()->addSolutionForComponentId($gradeable_id, $component_id, $itempool_item, $solution_text, $author_id);
                $solution_row = $this->core->getQueries()->getSolutionForComponentItempoolItem($gradeable_id, $component_id, $itempool_item);
            }
            catch (\Exception $exception) {
                $error = $exception->getMessage();
            }
        }

        return empty($error) ? JsonResponse::getSuccessResponse([
            "author" => $author_id,
            "current_user_id" => $this->core->getUser()->getId(),
            "edited_at" => DateUtils::convertTimeStamp(
                $this->core->getUser(),
                $solution_row['edited_at'],
                $this->core->getConfig()->getDateTimeFormat()->getFormat('solution_ta_notes')
            ),
            "solution_text" => $solution_text,
            "component_id" => $component_id,
            "itempool_item" => $solution_row['itempool_item'],
        ]) : JsonResponse::getErrorResponse($error);
    }
}
