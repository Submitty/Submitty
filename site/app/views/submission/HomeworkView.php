<?php

namespace app\views\submission;

use app\exceptions\NotebookException;
use app\libraries\CodeMirrorUtils;
use app\libraries\DateUtils;
use app\libraries\NumberUtils;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\Component;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\LateDays;
use app\models\gradeable\LateDayInfo;
use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\Utils;
/* Notebook */
use app\models\notebook\AbstractNotebookInput;

class HomeworkView extends AbstractView {
    /**
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable
     * @param int $display_version
     * @param bool $can_inquiry
     * @param bool $show_hidden_testcases
     * @return string
     */
    public function showGradeable(Gradeable $gradeable, $graded_gradeable, int $display_version, bool $can_inquiry, bool $show_hidden_testcases = false) {
        $return = '';

        $this->core->getOutput()->addInternalJs('drag-and-drop.js');
        $this->core->getOutput()->addInternalCss('table.css');

        // The number of days late this gradeable would be if submitted now (including exceptions)
        $late_day_exceptions = 0;
        $version_instance = null;
        if ($graded_gradeable !== null) {
            $version_instance = $graded_gradeable->getAutoGradedGradeable()->getAutoGradedVersions()[$display_version] ?? null;
            $late_day_exceptions = max(0, $graded_gradeable->getLateDayException($this->core->getUser()));
        }

        $is_admin = $this->core->getAccess()->canI('admin.wrapper', []);
        $on_team = $this->core->getUser()->onTeam($gradeable->getId());
        $is_team_assignment = $gradeable->isTeamAssignment();

        if ($this->core->getUser()->accessFullGrading()) {
            $this->core->getOutput()->addInternalModuleJs('grader-submission.js');
        }

        // Only show the late banner and daylight savings banner if the submission has a due date
        // Instructors shouldn't see this banner if they're not on a team (they won't have proper information)
        if (LateDays::filterCanView($this->core, $gradeable) && !($is_admin && !$on_team && $is_team_assignment)) {
            $late_days = LateDays::fromUser($this->core, $this->core->getUser());
            $return .= $this->renderLateDayMessage($late_days, $gradeable, $graded_gradeable);
        }
        if (!$gradeable->canStudentSubmit() && $gradeable->getSubmissionOpenDate() < $this->core->getDateTimeNow()) {
            $return .= $this->renderSubmissionsClosedBox();
        }

        try {
            $late_days = LateDays::fromUser($this->core, $this->core->getUser());
            // showing submission if user is a grader or student can submit
            if ($this->core->getUser()->accessGrading()) {
                $return .= $this->renderSubmitBox($late_days, $gradeable, $graded_gradeable, $version_instance, $late_day_exceptions);
            }
            elseif ($gradeable->isStudentSubmit()) {
                $return .= $this->renderSubmitBox($late_days, $gradeable, $graded_gradeable, $version_instance, $late_day_exceptions, $gradeable->canStudentSubmit());
            }
        }
        catch (NotebookException $e) {
            return $this->core->getOutput()->renderTwigTemplate('error/GenericError.twig', ['error_messages' => [$e->getMessage()]]);
        }

        $all_directories = $gradeable->getSplitPdfFiles();
        if ($this->core->getUser()->accessFullGrading() && count($all_directories) > 0) {
            $return .= $this->renderBulkUploadBox($gradeable);
        }

        /*
          See #1624 and #1967

          if (!$this->core->getOutput()->bufferOutput()) {
              echo $return;
              $return = '';
          }
         */

        $auto_graded_gradeable = $graded_gradeable !== null ? $graded_gradeable->getAutoGradedGradeable() : null;
        $submission_count = $auto_graded_gradeable !== null ? $auto_graded_gradeable->getHighestVersion() : 0;
        $active_version = $auto_graded_gradeable !== null ? $auto_graded_gradeable->getActiveVersion() : 0;
        if ($submission_count === 0) {
            $return .= $this->renderNoSubmissionBox($graded_gradeable);
        }
        else {
            $return .= $this->renderVersionBox($graded_gradeable, $version_instance, $show_hidden_testcases);
        }

        // Determine how many grading "parts" there are (e.g. peer grading, ta grading, autograding).
        $num_parts = 0;
        if ($gradeable->hasPeerComponent()) {
            $num_parts++;
        }
        if ($gradeable->isTaGrading()) {
            $num_parts++;
        }

        if ($version_instance !== null && $version_instance->isAutogradingComplete()) {
            foreach ($version_instance->getTestcases() as $testcase) {
                if ($testcase->canView()) {
                    $num_parts++;
                    break;
                }
            }
        }

        if ($gradeable->hasLeaderboard()) {
            $return .= $this->renderLeaderboardBox($graded_gradeable);
        }

        if ($submission_count > 0 && $num_parts > 1) {
            $return .= $this->renderTotalScoreBox($graded_gradeable, $version_instance, $show_hidden_testcases);
        }

        if ($submission_count > 0 && $active_version !== 0) {
            $return .= $this->renderAutogradingBox($graded_gradeable, $version_instance, $show_hidden_testcases);
        }

        $grade_inquiry_available = ($gradeable->isTaGradeReleased() || !$gradeable->hasReleaseDate())
            && $gradeable->isTaGrading()
            && $graded_gradeable !== null
            && $graded_gradeable->isTaGradingComplete()
            && $gradeable->isGradeInquiryOpen()
            && $submission_count !== 0;

        if (
            ($gradeable->isTaGradeReleased() || !$gradeable->hasReleaseDate())
            && $gradeable->isTaGrading()
            && $submission_count !== 0
            && $active_version !== 0
        ) {
            $return .= $this->renderTAResultsBox($graded_gradeable, $grade_inquiry_available);

            if ($gradeable->hasPeerComponent()) {
                $return .= $this->renderPeerResultsBox($graded_gradeable, $grade_inquiry_available);
            }
        }
        if ($grade_inquiry_available || $graded_gradeable !== null && $graded_gradeable->hasGradeInquiry()) {
            $return .= $this->renderGradeInquiryBox($graded_gradeable, $can_inquiry);
        }
        return $return;
    }

    /**
     * Render the late days banner
     * @param LateDays $late_days
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable
     * @return string
     */
    public function renderLateDayMessage(LateDays $late_days, Gradeable $gradeable, $graded_gradeable) {
        $active_version = 0;
        if ($graded_gradeable !== null) {
            $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        }
        // LateDays data
        $late_days_remaining = $late_days->getLateDaysRemaining();
        $late_day_info = $late_days->getLateDayInfoByGradeable($gradeable);

        // Default gradeable information
        $would_be_days_late = $gradeable->getWouldBeDaysLate();
        $late_days_allowed = $gradeable->getLateDays();

        // LateDayInfo data
        $active_days_late =  $late_day_info !== null ? $late_day_info->getDaysLate() : 0;
        $extensions = $late_day_info !== null ? $late_day_info->getLateDayException() : 0;
        $active_days_charged = $late_day_info !== null ? $late_day_info->getLateDaysCharged() : $active_days_late - $extensions;
        $late_day_budget = $late_day_info !== null ? $late_day_info->getLateDaysRemaining() :  $late_days_remaining;

        $late_days_allowed = $gradeable->getLateDays();

        $error = false;
        $messages = [];

        // ------------------------------------------------------------
        // ALWAYS PRINT DEADLINE EXTENSION (IF ANY)
        if ($extensions > 0) {
            $messages[] = ['type' => 'extension', 'info' => [
                'extensions' => $extensions
            ]];
        }

        // HOW MANY DAYS LATE...  MINUS EXTENSIONS?
        if ($active_days_charged > 0) {
            $active_days_charged = max(0, $active_days_late - $extensions);
        }

        // process daylight savings banner
        // check if we have excessive amounts of late days or if the due date and due date + late days is in different time zones
        // and if the user can submit without it being too late
        $due_date = $gradeable->getSubmissionDueDate();
        $due_date_with_late_days = clone $due_date;
        $due_date_with_late_days->modify('+' . $late_days_allowed . ' days');
        $today = new \DateTime();

        // if we are past the due date + late days
        if ($today > $due_date_with_late_days) {
            $daylight_message_required = false;
        }
        elseif ($late_days_allowed <= 365) {
            $daylight_due_date = intval($due_date->format("I"));
            $daylight_due_date_with_late_days = intval($due_date_with_late_days->format("I"));
            // DST is different, DST message always required
            if ($daylight_due_date !== $daylight_due_date_with_late_days) {
                $daylight_message_required = true;
            }
            else { // check if we walked in and then out of DST
                // same year, only need to check due date outside DST (0) and late day + due date outside of DST but on the other side (0)
                if ($due_date->format("y") === $due_date_with_late_days->format("y")) {
                    if ($daylight_due_date === 1 || $daylight_due_date_with_late_days === 1) {
                        $daylight_message_required = false;
                    }
                    else {
                        $daylight_message_required = intval($due_date->format("m")) < 6 && intval($due_date_with_late_days->format("m")) > 6;
                    }

                // different year, only false if we go from second non-DST to first non-DST
                }
                else {
                    if (
                        $daylight_due_date === 0
                        && $daylight_due_date_with_late_days === 0
                        && intval($due_date->format("m")) > 6
                        && intval($due_date_with_late_days->format("m")) < 6
                    ) {
                        $daylight_message_required = false;
                    }
                    else {
                        $daylight_message_required = true;
                    }
                }
            }
        }
        else {
            // more than 365 days, always true
            $daylight_message_required = true;
        }

        // ------------------------------------------------------------
        // IF STUDENT HAS ALREADY SUBMITTED AND THE ACTIVE VERSION IS LATE, PRINT LATE DAY INFORMATION FOR THE ACTIVE VERSION
        if ($active_version >= 1 && $active_days_late > 0) {
            // BAD STATUS
            if ($active_days_charged == 0 && $active_days_late > $extensions) {
                $error = true;

                // AUTO ZERO BECAUSE INSUFFICIENT LATE DAYS REMAIN
                if ($active_days_late > $late_day_budget) {
                    $messages[] = ['type' => 'too_few_remain', 'info' => [
                        'late' => $active_days_late,
                        'remaining' => $late_day_budget
                    ]];
                } // AUTO ZERO BECAUSE TOO MANY LATE DAYS USED ON THIS ASSIGNMENT
                else {
                    $messages[] = ['type' => 'too_many_used', 'info' => [
                        'late' => $active_days_late,
                        'charged' => $active_days_late - $extensions,
                        'allowed' => $late_days_allowed
                    ]];
                }
            } // LATE STATUS
            else {
                $messages[] = ['type' => 'late', 'info' => [
                    'late' => $active_days_late,
                    'charged' => $active_days_charged,
                    'remaining' => $late_day_budget,
                    'allowed_remaining' => $late_days_allowed - $active_days_charged
                ]];
            }
            if ($error) {
                //You're going to get a zero
                $messages[] = ['type' => 'getting_zero'];
            }
        }

        // ------------------------------------------------------------
        // (IF LATE) PRINT LATE DAY INFORMATION
        if ($would_be_days_late > 0) {
            // HOW MANY DAYS LATE...  MINUS EXTENSIONS?
            $new_late_charged = max(0, $would_be_days_late - $extensions);

            // if unsubmitted, or submitted but still in the late days allowed window
            if (
                $active_version < 1
                || (
                    $new_late_charged <= $late_day_budget
                    && $new_late_charged <= $late_days_allowed
                )
            ) {
                // PRINT WOULD BE HOW MANY DAYS LATE
                $messages[] = ['type' => 'would_late', 'info' => [
                    'late' => $would_be_days_late
                ]];

                // SUBMISSION NOW WOULD BE BAD STATUS -- INSUFFICIENT LATE DAYS
                if ($new_late_charged > $late_day_budget) {
                    $messages[] = ['type' => 'would_too_few_remain', 'info' => [
                        'remaining' => $late_day_budget
                    ]];
                    $error = true;
                    $messages[] = ['type' => 'would_get_zero'];
                } // SUBMISSION NOW WOULD BE BAD STATUS -- EXCEEDS LIMIT FOR THIS ASSIGNMENT
                elseif ($new_late_charged > $late_days_allowed) {
                    $messages[] = ['type' => 'would_too_many_used', 'info' => [
                        'allowed' => $late_days_allowed
                    ]];
                    $error = true;
                    $messages[] = ['type' => 'would_get_zero'];
                } // SUBMISSION NOW WOULD BE LATE
                else {
                    $new_late_charged = max(0, $would_be_days_late - $active_days_late - $extensions);
                    $new_late_days_remaining = $late_day_budget - $new_late_charged;
                    $messages[] = ['type' => 'would_allowed', 'info' => [
                        'charged' => $new_late_charged,
                        'remaining' => $new_late_days_remaining,
                        'active_version' => $active_version
                    ]];
                }
            }
        }

        // ------------------------------------------------------------
        // IN CASE OF AUTOMATIC ZERO, MAKE THE MESSAGE RED
        if ($error == true) {
            $messages[] = ['type' => 'contact_instructor', 'info' => [
                'extensions' => $extensions
            ]];
        }

        $late_days_url = $this->core->buildCourseUrl(['late_table']);

        return $this->core->getOutput()->renderTwigTemplate('submission/homework/LateDayMessage.twig', [
            'messages' => $messages,
            'error' => $error,
            'daylight' => $daylight_message_required,
            'late_days_url' => $late_days_url
        ]);
    }

    private function renderSubmissionsClosedBox() {
        $this->core->getOutput()->renderTwigOutput("submission/homework/SubmissionsClosedBox.twig");
    }
    /**
     * @param LateDays $late_days
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable
     * @param AutoGradedVersion|null $version_instance
     * @param int $late_day_exceptions
     * @param bool $canStudentSubmit
     * @return string
     */
    private function renderSubmitBox(LateDays $late_days, Gradeable $gradeable, $graded_gradeable, $version_instance, int $late_day_exceptions, bool $canStudentSubmit = true): string {
        $student_page = $gradeable->isStudentPdfUpload();
        $students_full = [];
        $output = "";

        $config = $gradeable->getAutogradingConfig();
        $notebook = null;
        $notebook_inputs = [];
        $num_parts = $config->getNumParts();
        $notebook_file_submissions = [];
        $notebook_model = null;
        if ($config->isNotebookGradeable()) {
            $notebook_model = $config->getUserSpecificNotebook($this->core->getUser()->getId());

            $notebook = $notebook_model->getNotebook();
            $num_parts = $notebook_model->getNumParts();
            $warning = $notebook_model->getWarning();
            if (isset($warning) && $this->core->getUser()->accessGrading()) {
                $output = $this->core->getOutput()->renderTwigTemplate(
                    'generic/Banner.twig',
                    [
                        'message' => $warning,
                        'error' => true
                    ]
                );
            }
            if ($graded_gradeable !== null) {
                $notebook_data = $notebook_model->getMostRecentNotebookSubmissions(
                    $graded_gradeable->getAutoGradedGradeable()->getHighestVersion(),
                    $notebook,
                    $this->core->getUser()->getId(),
                    $version_instance !== null ? $version_instance->getVersion() : 0,
                    $graded_gradeable->getGradeableId()
                );
            }

            $notebook_inputs = $notebook_model->getInputs();
            $image_data = $notebook_model->getImagePaths();
            $notebook_file_submissions = $notebook_model->getFileSubmissions();

            $this->core->getOutput()->addInternalCss('gradeable-notebook.css');
            $this->core->getOutput()->addInternalJs('gradeable-notebook.js');
            $this->core->getOutput()->addInternalJs('autosave-utils.js');
        }
        $this->core->getOutput()->addInternalJs('submission-page.js');
        $late_day_info = $late_days->getLateDayInfoByGradeable($gradeable);
        $charged_late_days =  $late_day_info !== null ? $late_day_info->getLateDaysCharged() : 0;
        $remaining_late_days_for_gradeable = $late_day_info !== null ? $late_day_info->getLateDaysRemaining() : 0;
        $gradeable_status = $late_day_info !== null ? $late_day_info->getStatus() : 0;
        $active_version_instance = null;
        if ($graded_gradeable !== null) {
            $active_version_instance = $graded_gradeable->getAutoGradedGradeable()->getActiveVersionInstance();
        }
        $active_days_late =  $active_version_instance !== null ? $active_version_instance->getDaysLate() : 0;
        $old_files = [];
        $display_version = 0;

        if ($this->core->getUser()->accessGrading()) {
            $students = $this->core->getQueries()->getAllUsers();
            $student_ids = [];
            foreach ($students as $student) {
                $student_ids[] = $student->getId();
            }

            $students_version = [];
            foreach ($this->core->getQueries()->getGradedGradeables([$gradeable], $student_ids) as $gg) {
                /** @var GradedGradeable $gg */
                $students_version[$gg->getSubmitter()->getId()] = $gg->getAutoGradedGradeable()->getHighestVersion();
            }
            $students_full = json_decode(Utils::getAutoFillData($students, $students_version, true));
        }

        $github_user_id = '';
        $github_repo_id = '';


        if (!$gradeable->isVcs()) {
            if ($version_instance !== null) {
                $display_version = $version_instance->getVersion();
                // If students do not have permission to download files then hide the old files
                if ($this->core->getUser()->accessGrading() || $gradeable->canStudentDownload()) {
                    for ($i = 1; $i <= $num_parts; $i++) {
                        foreach ($version_instance->getPartFiles($i)['submissions'] as $file) {
                            $old_files[] = [
                                'name' => str_replace('\'', '\\\'', $file['name']),
                                'size' => number_format($file['size'] / 1024, 2),
                                'part' => $i,
                                'path' => $file['path']
                            ];
                        }
                    }
                }
            }
        }
        else {
            if ($graded_gradeable !== null) {
                // Get path to VCS_CHECKOUT
                $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable->getId());
                $who_id = $this->core->getUser()->getId();
                $user_path = FileUtils::joinPaths($gradeable_path, $who_id);
                $highest_version = $graded_gradeable->getAutoGradedGradeable()->getHighestVersion();
                $display_version = $version_instance != null ? $version_instance->getVersion() : 0;
                $version_path = FileUtils::joinPaths($user_path, $highest_version);
                $path = FileUtils::joinPaths($version_path, ".submit.VCS_CHECKOUT");

                // Load repo and user id
                if (file_exists($path)) {
                    $json = json_decode(file_get_contents($path), true);
                    if (!is_null($json)) {
                        if (isset($json["git_user_id"])) {
                            $github_user_id = $json["git_user_id"];
                        }
                        if (isset($json["git_repo_id"])) {
                            $github_repo_id = $json["git_repo_id"];
                        }
                    }
                }
            }
        }

        $component_names = array_map(function (Component $component) {
            return $component->getTitle();
        }, $gradeable->getComponents());

        $input_data = array_map(function (AbstractNotebookInput $inp) {
            return $inp->toArray();
        }, $notebook_inputs);
        $student_id = '';
        if (!is_null($graded_gradeable)) {
            $student_id = ($graded_gradeable->getSubmitter()->isTeam()) ?
                $graded_gradeable->getSubmitter()->getTeam()->getLeaderId() :
                $graded_gradeable->getSubmitter()->getId();
        }

        $highest_version = $graded_gradeable !== null ? $graded_gradeable->getAutoGradedGradeable()->getHighestVersion() : 0;

        $viewing_inactive_version = $display_version !== 0 && $highest_version !== $display_version;

        // instructors can access this page even if they aren't on a team => don't create errors
        $my_team = $graded_gradeable !== null ? $graded_gradeable->getSubmitter()->getTeam() : "";
        $my_repository = $graded_gradeable !== null ? $gradeable->getRepositoryPath($this->core->getUser(), $my_team) : "";

        // Grab all team member late day information
        $team_ldi = ($graded_gradeable !== null && $my_team !== null) ? LateDayInfo::fromSubmitter($this->core, $graded_gradeable->getSubmitter(), $graded_gradeable) : null;
        $min_team_member_late_days = $team_ldi !== null ? min(array_map(function ($ldi) {
            if ($ldi !== null) {
                return $ldi->getLateDaysRemaining();
            }
            else {
                return 0;
            }
        }, $team_ldi)) : 0;

        $min_team_member_late_days_exception = $team_ldi !== null ? min(array_map(function ($ldi) {
            if ($ldi !== null) {
                return $ldi->getLateDayException();
            }
            else {
                return 0;
            }
        }, $team_ldi)) : 0;

        $testcase_messages = $version_instance !== null ? $version_instance->getTestcaseMessages() : [];

        $this->core->getOutput()->addInternalCss('submitbox.css');
        $this->core->getOutput()->addInternalCss('highlightjs/atom-one-light.css');
        $this->core->getOutput()->addInternalCss('highlightjs/atom-one-dark.css');
        $this->core->getOutput()->addInternalJs('submitbox-button-status.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('highlight.js', 'highlight.min.js'));
        $this->core->getOutput()->addInternalJs('markdown-code-highlight.js');
        CodeMirrorUtils::loadDefaultDependencies($this->core);

        $has_overridden_grades = false;
        if (!is_null($graded_gradeable)) {
            $graded_gradeable->hasOverriddenGrades();
        }
        $vcs_partial_path = '';
        $vcs_partial_path = $gradeable->getVcsPartialPath();
        $vcs_partial_path = str_replace('{$vcs_type}', $this->core->getConfig()->getVcsType(), $vcs_partial_path);
        $vcs_partial_path = str_replace('{$gradeable_id}', $gradeable->getId(), $vcs_partial_path);
        $vcs_partial_path = str_replace('{$user_id}', $this->core->getUser()->getId(), $vcs_partial_path);
        if ($gradeable->isTeamAssignment()) {
            $vcs_partial_path = str_replace('{$team_id}', $graded_gradeable->getSubmitter()->getId(), $vcs_partial_path);
        }

        $vcs_repo_exists = false;
        if ($gradeable->isVcs()) {
            $path = FileUtils::joinPaths(
                $this->core->getConfig()->getSubmittyPath(),
                'vcs',
                'git',
                $this->core->getConfig()->getTerm(),
                $this->core->getConfig()->getCourse(),
                $vcs_partial_path
            );
            $vcs_repo_exists = file_exists($path);
        }

        $recent_version_url = $graded_gradeable ? $this->core->buildCourseUrl(['gradeable', $gradeable->getId()]) . '/' . $graded_gradeable->getAutoGradedGradeable()->getHighestVersion() : null;
        $numberUtils = new NumberUtils();
        return $output . $this->core->getOutput()->renderTwigTemplate('submission/homework/SubmitBox.twig', [
            'course' => $this->core->getConfig()->getCourse(),
            'term' => $this->core->getConfig()->getTerm(),
            'using_subdirectory' => $gradeable->isUsingSubdirectory(),
            'vcs_subdirectory' => $gradeable->getVcsSubdirectory(),
            'vcs_partial_path' => $vcs_partial_path ,
            'gradeable_id' => $gradeable->getId(),
            'gradeable_name' => $gradeable->getTitle(),
            'gradeable_url' => $gradeable->getInstructionsUrl(),
            'due_date' => $gradeable->getSubmissionDueDate(),
            'date_time_format' => $this->core->getConfig()->getDateTimeFormat()->getFormat('gradeable'),
            'server_time_zone_string' => $this->core->getConfig()->getTimezone()->getName(),
            'user_time_zone_string' => $this->core->getUser()->getUsableTimeZone()->getName(),
            'part_names' => $gradeable->getAutogradingConfig()->getPartNames(),
            'one_part_only' => $gradeable->getAutogradingConfig()->getOnePartOnly(),
            'is_vcs' => $gradeable->isVcs(),
            'vcs_host_type' => $gradeable->getVcsHostType(),
            'github_user_id' => $github_user_id,
            'github_repo_id' => $github_repo_id,
            'is_bulk_upload' => $gradeable->isBulkUpload(),
            'has_due_date' => $gradeable->hasDueDate(),
            'is_timed' => $gradeable->hasAllowedTime(),
            'repository_path' => $my_repository,
            'show_no_late_submission_warning' => !$gradeable->isLateSubmissionAllowed() && ($gradeable->isSubmissionClosed() && !$gradeable->isTaGradeReleased()),
            // This is only used as a placeholder, so the who loads this page is the 'user' unless the
            //  client overrides the user
            'bulk_upload_access' => $this->core->getAccess()->canI("grading.electronic.grade", ["gradeable" => $gradeable]),
            'user_id' => $this->core->getUser()->getId(),
            'has_gradeable_message' => $gradeable->getAutogradingConfig()->getGradeableMessage() !== null
               && $gradeable->getAutogradingConfig()->getGradeableMessage() !== '',
            'gradeable_message' => $gradeable->getAutogradingConfig()->getGradeableMessage(),
            'allowed_late_days' => $gradeable->getLateDays(),
            'min_team_member_late_days' => $min_team_member_late_days,
            'min_team_member_late_days_exception' => $min_team_member_late_days_exception,
            'num_inputs' => isset($notebook_inputs) ? count($notebook_inputs) : 0,
            'max_submissions' => $gradeable->getAutogradingConfig()->getMaxSubmissions(),
            'display_version' => $display_version,
            'highest_version' => $highest_version,
            'student_page' => $student_page,
            'students_full' => $students_full,
            'student_id' => $student_id,
            'numberUtils' => new class () {
                /**
                 * @return array<int,int>
                 */
                public function getRandomIndices(int $array_length, string $student_id, string $gradeable_id): array {
                    return NumberUtils::getRandomIndices($array_length, '' . $student_id . $gradeable_id);
                }
            },
            'late_day_exceptions' => $late_day_exceptions,
            'team_ldi' => $team_ldi,
            'is_team_assignment' => $gradeable->isTeamAssignment(),
            'gradeable_status' => $gradeable_status,
            'charged_late_days' => $charged_late_days,
            'remaining_late_days_for_gradeable' => $remaining_late_days_for_gradeable,
            'old_files' => $old_files,
            'notebook' => $notebook_data ?? null,
            'testcase_messages' => $testcase_messages,
            'image_data' => $image_data ?? null,
            'component_names' => $component_names,
            'upload_message' => $this->core->getConfig()->getUploadMessage(),
            "csrf_token" => $this->core->getCsrfToken(),
            'has_overridden_grades' => $has_overridden_grades,
            'max_file_size' => Utils::returnBytes(ini_get('upload_max_filesize')),
            'max_post_size' => Utils::returnBytes(ini_get('post_max_size')),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'is_notebook' => $config->isNotebookGradeable(),
            'viewing_inactive_version' => $viewing_inactive_version,
            'allowed_minutes' => $gradeable->getUserAllowedTime($this->core->getUser()),
            'can_student_submit' => $canStudentSubmit,
            'is_grader_view' => false,
            'recent_version_url' => $recent_version_url,
            'git_auth_token_url' => $this->core->buildUrl(['authentication_tokens']),
            'git_auth_token_required' => false,
            'vcs_repo_exists' => $vcs_repo_exists,
            'vcs_generate_repo_url' => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'generate_repo'])
        ]);
    }

    private function removeLowConfidenceDigits($confidences, $id) {
        $ret = "";
        $str_id = strval($id);
        $i = 0;
        $low_conf = 0;
        foreach ($confidences as $confidence_val) {
            if ($confidence_val <= .50) {
                $ret .= "_";
                $low_conf++;
            }
            else {
                $ret .= $str_id[$i];
            }

            $i++;
        }

        //if we didn't find any digits can't suggest similar ids
        return $low_conf === strlen($str_id) ? $str_id : $ret;
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    private function renderBulkUploadBox(Gradeable $gradeable): string {
        $all_directories = $gradeable->getSplitPdfFiles();

        $files = [];
        $cover_images = [];
        $count = 1;
        $count_array = [];
        $matches = [];
        $use_ocr = false;

        foreach ($all_directories as $timestamp => $content) {
            $dir_files = $content['files'];
            # read the decoded.json file
            if (!array_key_exists('decoded.json', $dir_files)) {
                continue;
            }
            $bulk_upload_data = FileUtils::readJsonFile($dir_files["decoded.json"]['path']);

            foreach ($dir_files as $filename => $details) {
                if ($filename === 'decoded.json') {
                    // later submissions should replace the previous ones
                    continue;
                }
                $clean_timestamp = str_replace('_', ' ', $timestamp);
                $path = rawurlencode(htmlspecialchars($details['path']));
                //get the cover image if it exists
                if (strpos($filename, '_cover.jpg') && pathinfo($filename)['extension'] === 'jpg') {
                    $corrected_filename = rawurlencode(htmlspecialchars($filename));
                    $url = $this->core->buildCourseUrl(['display_file']) . '?' . http_build_query([
                        'dir' => 'split_pdf',
                        'file' => $corrected_filename,
                        'path' => $path,
                        'ta_grading' => 'false'
                    ]);
                    $cover_images[] = [
                        'filename' => $corrected_filename,
                        'url' => $url,
                    ];
                }
                if (
                    strpos($filename, 'cover') === false
                    || pathinfo($filename)['extension'] === 'json'
                    || pathinfo($filename)['extension'] === "jpg"
                ) {
                    continue;
                }
                // get the full filename for PDF popout
                // add 'timestamp / full filename' to count_array so that path to each filename is to the full PDF, not the cover
                $filename = rawurlencode(htmlspecialchars($filename));
                $url = $this->core->buildCourseUrl(['display_file']) . '?' . http_build_query([
                    'dir' => 'split_pdf',
                    'file' => $filename,
                    'path' => $path,
                    'ta_grading' => 'false'
                ]);
                $filename_full = str_replace('_cover.pdf', '.pdf', $filename);
                $path_full = str_replace('_cover.pdf', '.pdf', $path);
                $url_full = $this->core->buildCourseUrl(['display_file']) . '?' . http_build_query([
                    'dir' => 'uploads',
                    'file' => $filename_full,
                    'path' => $path_full,
                    'ta_grading' => 'false'
                ]);
                $count_array[$count] = FileUtils::joinPaths($timestamp, rawurlencode($filename_full));
                //decode the filename after to display correctly for users
                $filename_full = rawurldecode($filename_full);
                $cover_image_name = substr($filename, 0, -3) . "jpg";
                $cover_image = [];
                foreach ($cover_images as $img) {
                    if ($img['filename'] === $cover_image_name) {
                        $cover_image = $img;
                    }
                }
                $file = [
                    'clean_timestamp' => $clean_timestamp,
                    'filename_full' => $filename_full,
                    'filename' => $filename,
                    'url' => $url,
                    'url_full' => $url_full,
                    'cover_image' => $cover_image
                ];
                if (array_key_exists('is_qr', $bulk_upload_data) && $bulk_upload_data['is_qr'] && !array_key_exists($file['filename_full'], $bulk_upload_data)) {
                    continue;
                }
                elseif (array_key_exists('is_qr', $bulk_upload_data) && $bulk_upload_data['is_qr']) {
                    $data = $bulk_upload_data[$file['filename_full']];
                }

                $page_count = 0;
                $is_valid = true;
                $id = '';

                //decoded.json may be read before the associated data is written, check if key exists first
                if (array_key_exists('is_qr', $bulk_upload_data) && $bulk_upload_data['is_qr']) {
                    $use_ocr = array_key_exists('use_ocr', $bulk_upload_data) && $bulk_upload_data['use_ocr'];
                    $data = $bulk_upload_data[$file['filename_full']];

                    if ($use_ocr) {
                        $tgt_string = $this->removeLowConfidenceDigits(json_decode($data['confidences']), $data['id']);

                        $matches = [];
                        if (strpos($tgt_string, '_') !== false) {
                            $matches = $this->core->getQueries()->getSimilarNumericIdMatches($tgt_string);
                        }
                    }

                    if (array_key_exists('id', $data)) {
                        $id = $data['id'];
                        if (is_numeric($id)) {
                            $is_valid = $this->core->getQueries()->getUserByNumericId($id) !== null;
                        }
                        else {
                            $is_valid = $this->core->getQueries()->getUserById($id) !== null;
                        }
                    }
                    else {
                    //set the blank id as invalid for now, after a page refresh it will recorrect
                        $id = '';
                        $is_valid = false;
                    }
                    if (array_key_exists('page_count', $data)) {
                        $page_count = $data['page_count'];
                    }
                }
                else {
                    $is_valid = true;
                    $id = '';
                    if (array_key_exists('page_count', $bulk_upload_data)) {
                        $page_count = $bulk_upload_data['page_count'];
                    }
                }

                $file += ['page_count' => $page_count,
                        'id' => $id,
                        'valid' => $is_valid,
                        'matches' => $matches ];
                $files[] = $file;
                $count++;
            }
        }

        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        return $this->core->getOutput()->renderTwigTemplate('submission/homework/BulkUploadBox.twig', [
            'gradeable_id' => $gradeable->getId(),
            'team_assignment' => $gradeable->isTeamAssignment(),
            'max_team_size' => $gradeable->getTeamSizeMax(),
            'count_array' => $count_array,
            'files' => $files,
            'csrf_token' => $this->core->getCsrfToken(),
            'use_ocr' => $use_ocr
        ]);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @return string
     */
    private function renderNoSubmissionBox($graded_gradeable): string {
        $team_assignment = $graded_gradeable === null ? true : $graded_gradeable->getGradeable()->isTeamAssignment();
        $member_list = $graded_gradeable !== null && $team_assignment
                    ? $graded_gradeable->getSubmitter()->getTeam()->getMemberList() : '';
        $team_name = $graded_gradeable !== null && $team_assignment ? $graded_gradeable->getSubmitter()->getTeam()->getTeamName() : '';
        return $this->core->getOutput()->renderTwigTemplate('submission/homework/NoSubmissionBox.twig', [
            'team_assignment' => $team_assignment,
            'member_list' => $member_list,
            'team_name' => $team_name
        ]);
    }

     /**
      * @param GradedGradeable $graded_gradeable
      * @return string
      */
    private function renderLeaderboardBox(GradedGradeable $graded_gradeable): string {
        $autograding_config = $graded_gradeable->getGradeable()->getAutogradingConfig();
        if (is_null($autograding_config)) {
            return "";
        }

        $leaderboards = $autograding_config->getLeaderboards();

        return $this->core->getOutput()->renderTwigTemplate('submission/homework/LeaderboardBox.twig', [
          'leaderboard_count' => count($leaderboards),
          'url' => $this->core->buildCourseUrl(['gradeable', $graded_gradeable->getGradeableId(), 'leaderboard'])
        ]);
    }

     /**
      * @param GradedGradeable $graded_gradeable
      * @param AutoGradedVersion|null $version_instance
      * @param bool $show_hidden
      * @return string
      */
    private function renderTotalScoreBox(GradedGradeable $graded_gradeable, $version_instance, bool $show_hidden): string {
        $gradeable = $graded_gradeable->getGradeable();
        $autograding_config = $gradeable->getAutogradingConfig();
        $ta_graded_gradeable = $graded_gradeable->getTaGradedGradeable();
        $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();
        $active_version = $auto_graded_gradeable->getActiveVersion();

        // Get the Total Score
        $total_score = $ta_graded_gradeable->getTotalScore();
        $total_max = $gradeable->getManualGradingPoints();

        if ($version_instance !== null) {
            $total_score += $version_instance->getTotalPoints();
            if ($show_hidden) {
                $total_max += $gradeable->getAutogradingConfig()->getTotalNonExtraCredit();
            }
            else {
                $total_max += $gradeable->getAutogradingConfig()->getTotalNonHiddenNonExtraCredit();
            }
        }
        //Clamp full gradeable score to zero
        $total_score = max($total_score, 0);
        $total_score = NumberUtils::roundPointValue($total_score, $gradeable->getPrecision());

        //Get the Peer / TA Grading Score

        $peer_grading_max = $gradeable->getPeerPoints();
        $ta_grading_max   = $gradeable->getTaPoints();

        $ta_grading_earned = 0;
        $peer_grading_earned = 0;

        foreach ($gradeable->getComponents() as $component) {
            $container = $ta_graded_gradeable->getGradedComponentContainer($component);
            if ($component->isPeerComponent()) {
                $peer_grading_earned += $container->getTotalScore();
            }
            else {
                $ta_grading_earned += $container->getTotalScore();
            }
        }

        // Get Autograding Score
        $autograding_earned = 0;
        $autograding_max = 0;
        if ($version_instance !== null) {
            if ($show_hidden) {
                $autograding_earned = $version_instance->getTotalPoints();
                $autograding_max = $autograding_config->getTotalNonExtraCredit();
            }
            else {
                $autograding_earned = $version_instance->getNonHiddenPoints();
                $autograding_max = $autograding_config->getTotalNonHiddenNonExtraCredit();
            }
        }

        // Find which parts of grading are complete
        $autograding_complete = $version_instance !== null ? $version_instance->isAutogradingComplete() : false;
        $peer_grading_complete = true;
        $ta_grading_complete = true;
        $active_same_as_graded = true;

        foreach ($gradeable->getComponents() as $component) {
            $container = $ta_graded_gradeable->getGradedComponentContainer($component);
            if (!$container->isComplete()) {
                // TODO: For now, peer and ta grading completeness are equivalent.
                $ta_grading_complete = false;
                $peer_grading_complete = false;
                continue;
            }

            if ($container->getGradedVersion() !== $active_version) {
                $active_same_as_graded = false;
            }
        }

        // Get the number of visible testcases (needed to see if there is autograding)
        $num_visible_testcases = 0;
        if ($autograding_complete) {
            foreach ($version_instance->getTestcases() as $testcase) {
                if ($testcase->canView()) {
                    $num_visible_testcases++;
                }
            }
        }

        return $this->core->getOutput()->renderTwigTemplate(
            'submission/homework/TotalScoreBox.twig',
            [
                // Total Information
                'total_complete' => $autograding_complete && $ta_grading_complete
                                    && $peer_grading_complete && $gradeable->isTaGradeReleased(),
                'total_score' => $total_score,
                'total_max'   => $total_max,
                // Autograding Information
                // autograding_max > 0 if there are normal points, $autograding_earned > 0 if we  earned extra credit
                'has_autograding' => $num_visible_testcases > 0 && ($autograding_max > 0 || $autograding_earned > 0),
                'autograding_complete' => $autograding_complete,
                'autograding_earned' => $autograding_earned,
                'autograding_max' => $autograding_max,
                // Is there a version conflict?
                'active_same_as_graded' => $active_same_as_graded,
                // Ta Grading Information
                'has_ta_grading' => $gradeable->isTaGrading() && ($ta_grading_max > 0 || $ta_grading_earned > 0),
                'ta_grading_complete' => $ta_grading_complete,
                'ta_grading_earned' => $ta_grading_earned,
                'ta_grading_max' => $ta_grading_max,
                // Peer Grading Information
                'has_peer_grading' => $gradeable->hasPeerComponent() && ($peer_grading_max > 0 || $peer_grading_earned > 0),
                'peer_grading_complete' => $peer_grading_complete,
                'peer_grading_earned' => $peer_grading_earned,
                'peer_grading_max' => $peer_grading_max,
                // Have grades been released yet?
                'ta_grades_released' => $gradeable->isTaGradeReleased()
            ]
        );
    }

     /**
      * @param GradedGradeable $graded_gradeable
      * @param AutoGradedVersion|null $version_instance
      * @param bool $show_hidden
      * @return string
      */
    private function renderAutogradingBox(GradedGradeable $graded_gradeable, $version_instance, bool $show_hidden): string {
        $gradeable = $graded_gradeable->getGradeable();
        $autograding_config = $gradeable->getAutogradingConfig();
        $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();
        $active_version_number = $auto_graded_gradeable->getActiveVersion();
        $display_version = 0;

        $param = [
            'in_queue' => false,
            'in_progress_grading' => false,
            'result_text' => ''
        ];

        $show_testcases = false;
        $show_incentive_message = false;
        $history = null;

        if ($version_instance !== null) {
            $display_version = $version_instance->getVersion();

            if ($version_instance->isAutogradingComplete()) {
                $history = $version_instance->getLatestHistory();

                foreach ($version_instance->getTestcases() as $testcase) {
                    if ($testcase->canView()) {
                        $show_testcases = true;
                        break;
                    }
                }
            }

            $show_incentive_message = $autograding_config->hasEarlySubmissionIncentive()
                && $active_version_number > 0
                && $version_instance->getEarlyIncentivePoints() >= $autograding_config->getEarlySubmissionMinimumPoints()
                && $version_instance->getDaysEarly() > $autograding_config->getEarlySubmissionMinimumDaysEarly();

            $param = array_merge($param, [
                'in_queue' => $version_instance->isQueued(),
                'in_progress_grading' => $version_instance->isGrading(),
                'result_text' => $this->core->getOutput()->renderTemplate('AutoGrading', 'showResults', $version_instance, $show_hidden)
            ]);

            if ($history !== null) {
                $param = array_merge($param, [
                    'results' => 0,
                ]);
            }
        }

        if (stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Cypress') === false) {
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('mermaid', 'mermaid.min.js'));
        }

        // Get the number of visible testcases (needed to see if there is autograding)
        $num_visible_testcases = 0;
        if ($version_instance !== null && $version_instance->isAutogradingComplete()) {
            foreach ($version_instance->getTestcases() as $testcase) {
                if ($testcase->canView()) {
                    $num_visible_testcases++;
                }
            }
        }
        // If there are 0 visible testcases and autograding is complete,
        // there is no autograding for this assignment.
        if ($version_instance !== null) {
            $no_autograding = $num_visible_testcases == 0 && $version_instance->isAutogradingComplete();
        }
        else {
            $no_autograding = true;
        }

        // If there is no autograding at all, only explicitly let the student know that before
        // TA grades are released.
        if (
            $no_autograding
            && $gradeable->isTaGrading()
            && $graded_gradeable->isTaGradingComplete()
            && $gradeable->isTaGradeReleased()
        ) {
            return "";
        }


        $param = array_merge($param, [
            'gradeable_id' => $gradeable->getId(),
            'hide_test_details' => $gradeable->getAutogradingConfig()->getHideTestDetails(),
            'incomplete_autograding' => $version_instance !== null ? !$version_instance->isAutogradingComplete() : false,
            'display_version' => $display_version,
            'show_testcases' => $show_testcases,
            'show_incentive_message' => $show_incentive_message
        ]);

        $this->core->getOutput()->addInternalJs('confetti.js');
        $this->core->getOutput()->addInternalJs('submission-page.js');
        return $this->core->getOutput()->renderTwigTemplate('submission/homework/AutogradingResultsBox.twig', $param);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param AutoGradedVersion|null $version_instance
     * @param bool $show_hidden
     * @return string
     */
    private function renderVersionBox(GradedGradeable $graded_gradeable, $version_instance, bool $show_hidden): string {
        $gradeable = $graded_gradeable->getGradeable();
        $autograding_config = $gradeable->getAutogradingConfig();
        $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();
        $active_version_number = $auto_graded_gradeable->getActiveVersion();
        $display_version = 0;

        $version_data = array_map(function (AutoGradedVersion $version) use ($gradeable) {
            return [
                'points' => $version->getNonHiddenPoints(),
                'days_late' => $gradeable->isStudentSubmit() && $gradeable->hasDueDate() ? $version->getDaysLate() : 0
            ];
        }, $auto_graded_gradeable->getAutoGradedVersions());

        //sort array by version number after values have been mapped
        ksort($version_data);

        $param = [];
        $show_incentive_message = false;
        $history = null;

        if ($version_instance !== null) {
            $display_version = $version_instance->getVersion();

            if ($version_instance->isAutogradingComplete()) {
                $history = $version_instance->getLatestHistory();
            }

            $files = $version_instance->getFiles();

            $param = array_merge($param, [
                'is_notebook' => $autograding_config->isNotebookGradeable(),
                'submission_time' => DateUtils::dateTimeToString($version_instance->getSubmissionTime()),
                'days_late' => $version_instance->getDaysLate(),
                'num_autogrades' => $version_instance->getHistoryCount(),
                'files' => array_merge($files['submissions'], $files['checkout']),
                'display_version_days_late' => $version_instance->getDaysLate(),
                'autograder_machine' => $version_instance->getAutograderMachine(),
            ]);

            if ($history !== null) {
                $my_first_access_time = "";
                if ($history->getFirstAccessTime() !== null) {
                    $my_first_access_time = DateUtils::dateTimeToString($history->getFirstAccessTime());
                }
                $param = array_merge($param, [
                    'results' => 0,
                    'grade_time' => $history->getGradeTime(),
                    'first_access_time' => $my_first_access_time,
                    'grading_finished' => DateUtils::dateTimeToString($history->getGradingFinished()),
                    'wait_time' => $history->getWaitTime(),
                    'access_duration' => $history->getAccessDuration(),
                    'revision' => $history->getVcsRevision(),
                ]);
            }
        }

        $failed_file = '';
        $file_count = 0;
        // See if the grade has succeeded or failed
        if (array_key_exists('files', $param)) {
            $file_count = count($param['files']);
            if ($file_count === 1) {
                foreach ($param['files'] as $file) {
                    if (str_contains($file['relative_name'], 'failed')) {
                        $failed_file = file_get_contents($file['path']);
                    // Exclude the Exception error message
                        $failed_file = substr(strstr($failed_file, "\n"), 3);
                    }
                }
                // Arbitrary size, currently bigger than all of the failed files, but
                // could be increased if the failed files need more tips/messages
                $failed_file = (strlen($failed_file) > 1000) ? substr($failed_file, 0, 1000) : $failed_file;
            }
        }

        // If its not git checkout
        $can_download = !$gradeable->isVcs();

        $active_same_as_graded = true;
        if ($active_version_number !== 0 || $display_version !== 0) {
            if ($graded_gradeable->hasTaGradingInfo() && $graded_gradeable->isTaGradingComplete()) {
                $active_same_as_graded = $graded_gradeable->getTaGradedGradeable()->getGradedVersion() === $active_version_number;
            }
        }

        $cancel_url = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'version' ,'0']);
        $change_version_url = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'version', $display_version]);
        $view_version_url = $this->core->buildCourseUrl(['gradeable', $gradeable->getId()]) . '/';

        $param = array_merge($param, [
            'failed_file' => $failed_file,
            'file_count' => $file_count,
            'gradeable_id' => $gradeable->getId(),
            'student_download' => !$gradeable->isVcs() && $gradeable->canStudentDownload(),
            'hide_test_details' => $gradeable->getAutogradingConfig()->getHideTestDetails(),
            'has_manual_grading' => $gradeable->isTaGrading(),
            'incomplete_autograding' => $version_instance !== null ? !$version_instance->isAutogradingComplete() : false,
            'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
            'team_assignment' => $gradeable->isTeamAssignment(),
            'team_members' => $gradeable->isTeamAssignment() ? $graded_gradeable->getSubmitter()->getTeam()->getMemberList() : [],
            'team_name' => $gradeable->isTeamAssignment() ? $graded_gradeable->getSubmitter()->getTeam()->getTeamName() : '',
            'display_version' => $display_version,
            'active_version' => $active_version_number,
            'cancel_url' => $cancel_url,
            'vcs_subdirectory' => $gradeable->getVcsSubdirectory(),
            'change_version_url' => $change_version_url,
            'view_version_url' => $view_version_url,
            'versions' => $version_data,
            'total_points' => $autograding_config->getTotalNonHiddenNonExtraCredit(),
            'allowed_late_days' => $gradeable->getLateDays(),
            'ta_grades_released' => $gradeable->isTaGradeReleased(),
            'is_vcs' => $gradeable->isVcs(),
            'can_change_submissions' => $gradeable->isStudentSubmit(),
            'can_see_all_versions' => $gradeable->isStudentSubmit(),
            'active_same_as_graded' => $active_same_as_graded,
            'ta_grades_incomplete' => $gradeable->isTaGrading() && $gradeable->isTaGradeReleased() && !$graded_gradeable->isTaGradingComplete(),
            'csrf_token' => $this->core->getCsrfToken(),
            'date_time_format' => $this->core->getConfig()->getDateTimeFormat()->getFormat('gradeable_with_seconds'),
            'after_ta_open' => $gradeable->getGradeStartDate() < $this->core->getDateTimeNow()
        ]);

        $this->core->getOutput()->addInternalJs('confetti.js');
        return $this->core->getOutput()->renderTwigTemplate('submission/homework/CurrentVersionBox.twig', $param);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param bool $grade_inquiry_available
     * @return string
     */
    private function renderTAResultsBox(GradedGradeable $graded_gradeable, bool $grade_inquiry_available): string {

        $rendered_ta_results = '';
        $been_ta_graded = false;
        if ($graded_gradeable->isTaGradingComplete()) {
            $been_ta_graded = true;
            $rendered_ta_results = $this->core->getOutput()->renderTemplate(
                'AutoGrading',
                'showTAResults',
                $graded_gradeable->getTaGradedGradeable(),
                $grade_inquiry_available,
                $graded_gradeable->getAutoGradedGradeable()->getActiveVersionInstance()->getFiles()
            );
        }

        return $this->core->getOutput()->renderTwigTemplate('submission/homework/TAResultsBox.twig', [
            'been_ta_graded' => $been_ta_graded,
            'rendered_ta_results' => $rendered_ta_results]);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param bool $grade_inquiry_available
     * @return string
     */
    private function renderPeerResultsBox(GradedGradeable $graded_gradeable, bool $grade_inquiry_available): string {

        $rendered_peer_results = '';
        $been_peer_graded = false;
        // TODO: For now, Peer and TA grading completeness are synonymous.
        if ($graded_gradeable->isTaGradingComplete()) {
            $been_peer_graded = true;
            $rendered_peer_results = $this->core->getOutput()->renderTemplate(
                'AutoGrading',
                'showPeerResults',
                $graded_gradeable->getTaGradedGradeable(),
                $grade_inquiry_available,
                $graded_gradeable->getAutoGradedGradeable()->getActiveVersionInstance()->getFiles()
            );
        }

        return $this->core->getOutput()->renderTwigTemplate('submission/homework/PeerResultsBox.twig', [
            'been_peer_graded' => $been_peer_graded,
            'rendered_peer_results' => $rendered_peer_results]);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param bool $can_inquiry
     * @return string
     */
    private function renderGradeInquiryBox(GradedGradeable $graded_gradeable, bool $can_inquiry): string {
        return $this->core->getOutput()->renderTwigTemplate('submission/homework/GradeInquiryBox.twig', [
            'graded_gradeable' => $graded_gradeable,
            'can_inquiry' => $can_inquiry
        ]);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param bool $can_inquiry
     * @return string
     */
    public function showGradeInquiryDiscussion(GradedGradeable $graded_gradeable, bool $can_inquiry): string {
        $grade_inquiry_per_component_allowed = $graded_gradeable->getGradeable()->isGradeInquiryPerComponentAllowed();
        $is_inquiry_open = $graded_gradeable->getGradeable()->isGradeInquiryOpen();
        $grade_inquiry_message = $this->core->getConfig()->getGradeInquiryMessage();
        $grade_inquiry_url = $this->core->buildCourseUrl([
            'gradeable',
            $graded_gradeable->getGradeable()->getId(),
            'grade_inquiry',
            'new'
        ]);
        $change_request_status_url = $this->core->buildCourseUrl([
            'gradeable',
            $graded_gradeable->getGradeable()->getId(),
            'grade_inquiry',
            'toggle_status'
        ]);
        $make_grade_inquiry_post_url = $this->core->buildCourseUrl([
            'gradeable',
            $graded_gradeable->getGradeable()->getId(),
            'grade_inquiry',
            'post'
        ]);

        $grade_inquiries = $graded_gradeable->getGradeInquiries();
        $gradeable_components = $graded_gradeable->getGradeable()->getComponents();
        // initialize grade inquiries array with all posts grade inquiry to aggregate all posts
        $grade_inquiries_twig_array = [];
        if (!empty($grade_inquiries)) {
            $grade_inquiries_twig_array[0] = ['posts' => []];
            $queries = $this->core->getQueries();
            $grade_inquiry_posts = $queries->getGradeInquiryDiscussions($grade_inquiries);
            foreach ($grade_inquiries as $grade_inquiry) {
                $gc_id = $grade_inquiry->getGcId() ?? 0;
                $gc_title = '';
                if ($gc_id != 0) {
                    $component = $graded_gradeable->getGradeable()->getComponent($gc_id);
                    $gc_title = $component->getTitle();
                }


                $grade_inquiry_posts_for_id = $grade_inquiry_posts[$grade_inquiry->getId()];
                $author_user_ids = array_map(function ($post) {
                    return $post["user_id"];
                }, $grade_inquiry_posts_for_id);
                $author_user_groups = $queries->getAuthorUserGroups(array_values($author_user_ids));

                $instructor_full_access = [];
                $limited_access_grader = [];

                foreach ($author_user_groups as $author) {
                    $limited_access_grader[$author["user_id"]] = $author["user_group"] === User::GROUP_LIMITED_ACCESS_GRADER;
                    $instructor_full_access[$author["user_id"]] = $author["user_group"] <= User::GROUP_FULL_ACCESS_GRADER;
                }

                // format posts
                $posts = [];
                foreach ($grade_inquiry_posts[$grade_inquiry->getId()] as $post) {
                    if (empty($post)) {
                        break;
                    }
                    $is_staff = $queries->isStaffPost($post['user_id']);

                    $is_limited_access_grader = $limited_access_grader[$post['user_id']];
                    $is_instructor_or_full_access_grader = $instructor_full_access[$post['user_id']];
                    $given_name = $queries->getUserById($post['user_id'])->getDisplayedGivenName();
                    $family_name = $queries->getUserById($post['user_id'])->getDisplayedFamilyName();
                    $name = $given_name;
                    if ($is_limited_access_grader) {
                        $name = $given_name . " " . substr($family_name, 0, 1) . ".";
                    }
                    if ($is_instructor_or_full_access_grader) {
                        $name = $given_name . ' ' . $family_name;
                    }

                    $date = DateUtils::parseDateTime($post['timestamp'], $this->core->getConfig()->getTimezone());
                    $content = $post['content'];
                    $post_id = $post['id'];
                    $posts[] = [
                        'is_staff' => $is_staff,
                        'date' => DateUtils::convertTimeStamp($this->core->getUser(), $date->format('c'), $this->core->getConfig()->getDateTimeFormat()->getFormat('gradeable')),
                        'date_sort' => $date,
                        'name' => $name,
                        'content' => $content,
                        'gc_title' => $gc_title,
                        'id' => $post_id
                    ];
                }

                if ($gc_id != 0) {
                    // grade inquiry object
                    $grade_inquiry_twig_object = [
                        'id' => $grade_inquiry->getId(),
                        'gc_id' => $gc_id,
                        'status' => $grade_inquiry->getStatus(),
                        'posts' => $posts
                    ];
                    // add grade inquiry to grade inquiries array
                    $grade_inquiries_twig_array[$gc_id] = $grade_inquiry_twig_object;
                }
                else {
                    $grade_inquiries_twig_array[0]['id'] = $grade_inquiry->getId();
                    $grade_inquiries_twig_array[0]['gc_id'] = $gc_id;
                    $grade_inquiries_twig_array[0]['status'] = $grade_inquiry->getStatus();
                }
                $grade_inquiries_twig_array[0]['posts'] = array_merge($grade_inquiries_twig_array[0]['posts'], $posts);
            }
            // sort "all" tab posts chronologically
            usort($grade_inquiries_twig_array[0]['posts'], function ($post1, $post2) {
                if ($post1['date_sort'] == $post2['date_sort']) {
                    return 0;
                }
                return ($post1['date_sort'] < $post2['date_sort']) ? -1 : 1;
            });
        }

        // construct components array for tabs
        $components_twig_array = [];
        if ($grade_inquiry_per_component_allowed) {
            foreach ($gradeable_components as $component) {
                $component_object = [
                    'id' => $component->getId(),
                    'title' => $component->getTitle(),
                ];
                $components_twig_array[] = $component_object;
            }
        }
        $components_twig_array[] = ['id' => 0, 'title' => 'All'];

        return $this->core->getOutput()->renderTwigTemplate('submission/grade_inquiry/Discussion.twig', [
            'course' => $this->core->getConfig()->getCourse(),
            'term' => $this->core->getConfig()->getTerm(),
            'grade_inquiries' => $grade_inquiries_twig_array,
            'grade_inquiry_url' => $grade_inquiry_url,
            'change_request_status_url' => $change_request_status_url,
            'make_request_post_url' => $make_grade_inquiry_post_url,
            'has_submission' => $graded_gradeable->hasSubmission(),
            'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
            'g_id' => $graded_gradeable->getGradeable()->getId(),
            'grade_inquiry_message' => $grade_inquiry_message,
            'can_inquiry' => $can_inquiry,
            'is_inquiry_valid' => $graded_gradeable->getGradeable()->isGradeInquirySettingsValid(),
            'is_inquiry_yet_to_start' => ($graded_gradeable->getGradeable()->isGradeInquiryYetToStart() || !$graded_gradeable->getGradeable()->isTaGradeReleased()),
            'is_inquiry_open' => $is_inquiry_open,
            'is_grading' => $this->core->getUser()->accessGrading(),
            'grade_inquiry_per_component_allowed' => $grade_inquiry_per_component_allowed,
            'gradeable_components' => $components_twig_array,
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }

    /**
     * @param array $post
     * @param GradedGradeable $graded_gradeable
     * @return string
     */

    public function renderSingleGradeInquiryPost(array $post, GradedGradeable $graded_gradeable): string {
        $grade_inquiry_per_component_allowed = $graded_gradeable->getGradeable()->isGradeInquiryPerComponentAllowed();
        $queries = $this->core->getQueries();
        $author_user_id = [$post["user_id"]];
        $author_user_group = $queries->getAuthorUserGroups($author_user_id)[0];
        $limited_access_grader = $author_user_group['user_group'] === User::GROUP_LIMITED_ACCESS_GRADER;
        $instructor_full_access = $author_user_group['user_group'] <= User::GROUP_FULL_ACCESS_GRADER;
        $family_name = $queries->getUserById($post['user_id'])->getDisplayedFamilyName();
        $given_name = $queries->getUserById($post['user_id'])->getDisplayedGivenName();
        $name  = $given_name;
        if ($limited_access_grader) {
            $name = $given_name . " " . substr($family_name, 0, 1) . ".";
        }
        if ($instructor_full_access) {
            $name = $given_name . ' ' . $family_name;
        }

        $is_staff = $queries->isStaffPost($post['user_id']);
        $date = DateUtils::parseDateTime($post['timestamp'], $this->core->getConfig()->getTimezone());
        $content = $post['content'];
        $post_id = $post['id'];

        $gc_id = $post['gc_id'];
        $gc_title = '';
        if (!is_null($gc_id)) {
            $gradeable_component = $graded_gradeable->getGradeable()->getComponent($gc_id);
            $gc_title = $gradeable_component->getTitle();
        }

        return $this->core->getOutput()->renderTwigTemplate('submission/grade_inquiry/Post.twig', [
            'post' => [
                'is_staff' => $is_staff,
                'date' => DateUtils::convertTimeStamp($this->core->getUser(), $date->format('c'), $this->core->getConfig()->getDateTimeFormat()->getFormat('gradeable')),
                'date_sort' => $date,
                'name' => $name,
                'content' => $content,
                'gc_title' => $gc_title,
                'id' => $post_id
            ],
            'grade_inquiry_per_component_allowed' => $grade_inquiry_per_component_allowed,
            'component' => [
                'id' => $gc_id
            ]
        ]);
    }
}
