<?php

namespace app\views\submission;

use app\libraries\DateUtils;
use app\models\gradeable\AutoGradedTestcase;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\Component;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedComponent;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\SubmissionTextBox;
use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;

class HomeworkView extends AbstractView {

    public function unbuiltGradeable(Gradeable $gradeable) {
        return $this->core->getOutput()->renderTwigTemplate('error/UnbuiltGradeable.twig', [
            'title' => $gradeable->getTitle()
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable
     * @param \app\models\Gradeable $old_gradeable
     * @param int $display_version
     * @param int $late_days_use
     * @param int $extensions
     * @param bool $canViewWholeGradeable
     * @return string
     */
    public function showGradeable(Gradeable $gradeable, $graded_gradeable, \app\models\Gradeable $old_gradeable, int $display_version, int $late_days_use, int $extensions, bool $canViewWholeGradeable = false) {
        $return = '';

        $this->core->getOutput()->addInternalJs('drag-and-drop.js');

        $version_instance = null;
        if ($graded_gradeable !== null) {
            $version_instance = $graded_gradeable->getAutoGradedGradeable()->getAutoGradedVersions()[$display_version] ?? null;
        }

        $return .= $this->renderLateDayMessage($old_gradeable, $extensions);

        // showing submission if user is grader or student can submit
        if ($this->core->getUser()->accessGrading() || $gradeable->isStudentSubmit()) {
            $return .= $this->renderSubmitBox($gradeable, $graded_gradeable, $version_instance, $late_days_use);
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
        } else {
            $return .= $this->renderVersionBox($graded_gradeable, $version_instance, $canViewWholeGradeable);
        }

        $regrade_available = $this->core->getConfig()->isRegradeEnabled()
            && $gradeable->isTaGradeReleased()
            && $gradeable->isTaGrading()
            && $graded_gradeable !== null
            && $graded_gradeable->isTaGradingComplete()
            && $gradeable->isRegradeOpen()
            && $submission_count !== 0;
            
        if ($gradeable->isTaGradeReleased()
            && $gradeable->isTaGrading()
            && $submission_count !== 0
            && $active_version !== 0) {
            $return .= $this->renderTAResultsBox($graded_gradeable, $regrade_available);
        }
        if ($regrade_available) {
            $return .= $this->renderRegradeBox($graded_gradeable);
        }
        return $return;
    }

    /**
     * TODO: waiting on late day model class before converting this method
     * @param \app\models\Gradeable $gradeable
     * @param int $extensions
     * @return string
     */
    public function renderLateDayMessage(\app\models\Gradeable $gradeable, int $extensions) {
        $order_by = [
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END'
        ];
        $total_late_used = 0;
        $curr_late = 0;
        $late_day_budget = 0;
        foreach ($this->core->getQueries()->getGradeablesIterator(null, $gradeable->getUser()->getId(), 'registration_section', 'u.user_id', 0, $order_by) as $g) {
            $g->calculateLateDays($total_late_used);
            $total_late_used-=$g->getLateDayExceptions();
            $curr_late = $g->getStudentAllowedLateDays();
            if($g->getId() === $gradeable->getId()){
                $late_day_budget = $curr_late-$total_late_used;
            }
        }
        $late_days_remaining = $curr_late - $total_late_used;
        $active_days_late = $gradeable->getActiveVersion() == 0 ? 0 : $gradeable->getActiveDaysLate();
        $would_be_days_late = $gradeable->getWouldBeDaysLate();
        $late_days_allowed = $gradeable->getAllowedLateDays();
        $active_version = $gradeable->getActiveVersion();

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
        $active_days_charged = max(0, $active_days_late - $extensions);

        // ------------------------------------------------------------
        // IF STUDENT HAS ALREADY SUBMITTED AND THE ACTIVE VERSION IS LATE, PRINT LATE DAY INFORMATION FOR THE ACTIVE VERSION
        if ($active_version >= 1 && $active_days_late > 0) {
            // BAD STATUS - AUTO ZERO BECAUSE INSUFFICIENT LATE DAYS REMAIN
            if ($active_days_charged > $late_day_budget) {
                $error = true;
                $messages[] = ['type' => 'too_few_remain', 'info' => [
                    'late' => $active_days_late,
                    'remaining' => $late_days_remaining
                ]];
            } // BAD STATUS - AUTO ZERO BECAUSE TOO MANY LATE DAYS USED ON THIS ASSIGNMENT
            else if ($active_days_charged > $late_days_allowed) {
                $error = true;
                $messages[] = ['type' => 'too_many_used', 'info' => [
                    'late' => $active_days_late,
                    'charged' => $active_days_charged,
                    'allowed' => $late_days_allowed
                ]];
            } // LATE STATUS
            else {
                $messages[] = ['type' => 'late', 'info' => [
                    'late' => $active_days_late,
                    'charged' => $active_days_charged,
                    'remaining' => $late_days_remaining
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
            if ($active_version < 1 ||
                ($new_late_charged <= $late_days_remaining &&
                    $new_late_charged <= $late_days_allowed)) {

                // PRINT WOULD BE HOW MANY DAYS LATE
                $messages[] = ['type' => 'would_late', 'info' => [
                    'late' => $would_be_days_late
                ]];

                // SUBMISSION NOW WOULD BE BAD STATUS -- INSUFFICIENT LATE DAYS
                if ($new_late_charged > $late_days_remaining) {
                    $messages[] = ['type' => 'would_too_few_remain', 'info' => [
                        'remaining' => $late_days_remaining
                    ]];
                    $error = true;
                    $messages[] = ['type' => 'would_get_zero'];
                } // SUBMISSION NOW WOULD BE BAD STATUS -- EXCEEDS LIMIT FOR THIS ASSIGNMENT
                else if ($new_late_charged > $late_days_allowed) {
                    $messages[] = ['type' => 'would_too_many_used', 'info' => [
                        'allowed' => $late_days_allowed
                    ]];
                    $error = true;
                    $messages[] = ['type' => 'would_get_zero'];
                } // SUBMISSION NOW WOULD BE LATE
                else {
                    $new_late_days_remaining = $late_days_remaining + $active_days_charged - $new_late_charged;
                    $messages[] = ['type' => 'would_allowed', 'info' => [
                        'charged' => $new_late_charged,
                        'remaining' => $new_late_days_remaining
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

        return $this->core->getOutput()->renderTwigTemplate('submission/homework/LateDayMessage.twig', [
            'messages' => $messages,
            'error' => $error
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable
     * @param AutoGradedVersion|null $version_instance
     * @param int $late_days_use
     * @return string
     */
    private function renderSubmitBox(Gradeable $gradeable, $graded_gradeable, $version_instance, int $late_days_use): string {
        $student_page = $gradeable->isStudentPdfUpload();
        $students_full = [];
        $textboxes = $gradeable->getAutogradingConfig()->getTextboxes();
        $old_files = [];
        $display_version = 0;

        if ($this->core->getUser()->accessGrading()) {
            $students = $this->core->getQueries()->getAllUsers();
            $student_ids = array();
            foreach ($students as $student) {
                $student_ids[] = $student->getId();
            }

            // FIXME: this works, but does not really use new model.  We don't need all that much of the info anyway
            $gradeables = $this->core->getQueries()->getGradeables($gradeable->getId(), $student_ids);
            $students_version = array();
            foreach ($gradeables as $g) {
                $students_version[] = array($g->getUser(), $g->getHighestVersion());
            }

            $students_full = array();
            foreach ($students_version as $student_pair) {
                /* @var User $student */
                $student = $student_pair[0];

                $student_entry = array('value' => $student->getId(),
                    'label' => $student->getDisplayedFirstName() . ' ' . $student->getLastName() . ' <' . $student->getId() . '>');

                if ($student_pair[1] !== 0) {
                    $student_entry['label'] .= ' (' . $student_pair[1] . ' Prev Submission)';
                }

                $students_full[] = $student_entry;
            }
        }

        $image_data = [];
        if (!$gradeable->isVcs()) {
            foreach ($textboxes as $textbox) {
                foreach ($textbox->getImages() as $image) {
                    $image_name = $image['name'];
                    $imgPath = FileUtils::joinPaths(
                        $this->core->getConfig()->getCoursePath(),
                        'test_input',
                        $gradeable->getId(),
                        $image_name
                    );
                    $content_type = FileUtils::getContentType($imgPath);
                    if (substr($content_type, 0, 5) === 'image') {
                        // Read image path, convert to base64 encoding
                        $textBoxImageData = base64_encode(file_get_contents($imgPath));
                        // Format the image SRC:  data:{mime};base64,{data};
                        $textBoximagesrc = 'data: ' . mime_content_type($imgPath) . ';charset=utf-8;base64,' . $textBoxImageData;
                        // insert the sample image data

                        $image_data[$image_name] = $textBoximagesrc;
                    }
                }
            }

            if($version_instance !== null) {
                $display_version = $version_instance->getVersion();
                for ($i = 1; $i <= $gradeable->getAutogradingConfig()->getNumParts(); $i++) {
                    foreach ($version_instance->getPartFiles($i) as $file) {
                        $size = number_format($file['size'] / 1024, 2);
                        // $escape_quote_filename = str_replace('\'','\\\'',$file['name']);
                        if (substr($file['relative_name'], 0, strlen("part{$i}/")) === "part{$i}/") {
                            $escape_quote_filename = str_replace('\'', '\\\'', substr($file['relative_name'], strlen("part{$i}/")));
                        } else {
                            $escape_quote_filename = str_replace('\'', '\\\'', $file['relative_name']);
                        }

                        $old_files[] = ['name' => $escape_quote_filename, 'size' => $size, 'part' => $i];
                    }
                }
            }
        }

        $component_names = array_map(function(Component $component) {
            return $component->getTitle();
        }, $gradeable->getComponents());
        
        $textbox_data = array_map(function(SubmissionTextBox $text_box) {
            return $text_box->toArray();
        }, $textboxes);

        $highest_version = $graded_gradeable !== null ? $graded_gradeable->getAutoGradedGradeable()->getHighestVersion() : 0;

        // instructors can access this page even if they aren't on a team => don't create errors
        $my_team = $graded_gradeable !== null ? $graded_gradeable->getSubmitter()->getTeam() : "";
        $my_repository = $graded_gradeable !== null ? $gradeable->getRepositoryPath($this->core->getUser(),$my_team) : "";

        $DATE_FORMAT = "m/d/Y @ H:i";

        return $this->core->getOutput()->renderTwigTemplate('submission/homework/SubmitBox.twig', [
            'gradeable_id' => $gradeable->getId(),
            'gradeable_name' => $gradeable->getTitle(),
            'formatted_due_date' => $gradeable->getSubmissionDueDate()->format($DATE_FORMAT),
            'part_names' => $gradeable->getAutogradingConfig()->getPartNames(),
            'is_vcs' => $gradeable->isVcs(),
            'vcs_subdirectory' => $gradeable->getVcsSubdirectory(),
            'repository_path' => $my_repository,
            // This is only used as a placeholder, so the who loads this page is the 'user' unless the
            //  client overrides the user
            'user_id' => $this->core->getUser()->getId(),
            'has_assignment_message' => $gradeable->getAutogradingConfig()->getAssignmentMessage() !== '',
            'assignment_message' => $gradeable->getAutogradingConfig()->getAssignmentMessage(),
            'allowed_late_days' => $gradeable->getLateDays(),
            'num_text_boxes' => $gradeable->getAutogradingConfig()->getNumTextBoxes(),
            'max_submissions' => $gradeable->getAutogradingConfig()->getMaxSubmissions(),
            'display_version' => $display_version,
            'highest_version' => $highest_version,
            'student_page' => $student_page,
            'students_full' => $students_full,
            'late_days_use' => $late_days_use,
            'old_files' => $old_files,
            'textboxes' => $textbox_data,
            'image_data' => $image_data,
            'component_names' => $component_names,
            'upload_message' => $this->core->getConfig()->getUploadMessage()
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    private function renderBulkUploadBox(Gradeable $gradeable): string {
        $all_directories = $gradeable->getSplitPdfFiles();

        $files = [];

        $count = 1;
        $count_array = array();
        foreach ($all_directories as $timestamp => $content) {
            $dir_files = $content['files'];

            foreach ($dir_files as $filename => $details) {
                $clean_timestamp = str_replace('_', ' ', $timestamp);
                $path = rawurlencode(htmlspecialchars($details['path']));
                if (strpos($filename, 'cover') === false) {
                    continue;
                }
                // get the full filename for PDF popout
                // add 'timestamp / full filename' to count_array so that path to each filename is to the full PDF, not the cover
                $filename = rawurlencode(htmlspecialchars($filename));
                $url = $this->core->buildUrl([
                    'component' => 'misc',
                    'page' => 'display_file',
                    'dir' => 'split_pdf',
                    'file' => $filename,
                    'path' => $path,
                    'ta_grading' => 'false'
                ]);
                $filename_full = str_replace('_cover.pdf', '.pdf', $filename);
                $path_full = str_replace('_cover.pdf', '.pdf', $path);
                $url_full = $this->core->buildUrl([
                    'component' => 'misc',
                    'page' => 'display_file',
                    'dir' => 'uploads',
                    'file' => $filename_full,
                    'path' => $path_full,
                    'ta_grading' => 'false'
                ]);
                $count_array[$count] = FileUtils::joinPaths($timestamp, rawurlencode($filename_full));
                //decode the filename after to display correctly for users
                $filename_full = rawurldecode($filename_full);
                $files[] = [
                    'clean_timestamp' => $clean_timestamp,
                    'filename_full' => $filename_full,
                    'url' => $url,
                    'url_full' => $url_full,
                ];
                $count++;
            }
        }

        return $this->core->getOutput()->renderTwigTemplate('submission/homework/BulkUploadBox.twig', [
            'gradeable_id' => $gradeable->getId(),
            'team_assignment' => $gradeable->isTeamAssignment(),
            'max_team_size' => $gradeable->getTeamSizeMax(),
            'count_array' => $count_array,
            'files' => $files,
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
        return $this->core->getOutput()->renderTwigTemplate('submission/homework/NoSubmissionBox.twig', [
            'team_assignment' => $team_assignment,
            'member_list' => $member_list
        ]);
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

        $version_data = array_map(function(AutoGradedVersion $version) {
            return [
                'points' => $version->getNonHiddenPoints(),
                'days_late' => $version->getDaysLate()
            ];
        }, $auto_graded_gradeable->getAutoGradedVersions());

        //sort array by version number after values have been mapped
        ksort($version_data);

        $param = [];
        $show_testcases = false;
        $show_incentive_message = false;
        $history = null;

        if ($version_instance !== null) {
            $display_version = $version_instance->getVersion();
            $history = $version_instance->getLatestHistory();

            foreach ($version_instance->getTestcases() as $testcase) {
                if ($testcase->canView()) {
                    $show_testcases = true;
                    break;
                }
            }

            $show_incentive_message = $autograding_config->hasEarlySubmissionIncentive()
                && $active_version_number > 0
                && $version_instance->getEarlyIncentivePoints() >= $autograding_config->getEarlySubmissionMinimumPoints()
                && $version_instance->getDaysEarly() > $autograding_config->getEarlySubmissionMinimumDaysEarly();

            $param = array_merge($param, [
                'in_queue' => $version_instance->isQueued(),
                'grading' => $version_instance->isGrading(),
                'submission_time' => DateUtils::dateTimeToString($version_instance->getSubmissionTime()),
                'days_late' => $version_instance->getDaysLate(),
                'num_autogrades' => $version_instance->getHistoryCount(),
                'files' => $version_instance->getFiles(),
                'display_version_days_late' => $version_instance->getDaysLate(),
                'result_text' => $this->core->getOutput()->renderTemplate('AutoGrading', 'showResults', $version_instance, $show_hidden)
            ]);

            if ($history !== null) {
                $param = array_merge($param, [
                    'results' => 0,
                    'grade_time' => $history->getGradeTime(),
                    'grading_finished' => DateUtils::dateTimeToString($history->getGradingFinished()),
                    'wait_time' => $history->getWaitTime(),
                    'revision' => $history->getVcsRevision(),
                ]);
            }

            if($version_instance->isQueued()) {
                $param = array_merge($param, [
                    'queue_pos' => $version_instance->getQueuePosition(),
                    'queue_total' => $this->core->getGradingQueue()->getQueueCount()
                ]);
            }
        }

        // if not active version and student cannot see any more than active version
        $can_download = !$gradeable->isVcs()
            && $gradeable->isStudentDownload()
            && ($active_version_number === $display_version || $gradeable->isStudentDownloadAnyVersion());

        $active_same_as_graded = true;
        if ($active_version_number !== 0 || $display_version !== 0) {
            if ($graded_gradeable->hasTaGradingInfo()) {
                $active_same_as_graded = $graded_gradeable->getTaGradedGradeable()->getGradedVersion() === $active_version_number;
            }
        }

        $cancel_url = $this->core->buildUrl([
            'component' => 'student',
            'action' => 'update',
            'gradeable_id' => $gradeable->getId(),
            'new_version' => 0
        ]);

        $change_version_url = $this->core->buildUrl([
            'component' => 'student',
            'action' => 'update',
            'gradeable_id' => $gradeable->getId(),
            'new_version' => $display_version
        ]);

        $view_version_url = $this->core->buildUrl([
            'component' => 'student',
            'gradeable_id' => $gradeable->getId(),
            'gradeable_version' => ''
        ]);

        $check_refresh_submission_url = $this->core->buildUrl([
            'component' => 'student',
            'page' => 'submission',
            'action' => 'check_refresh',
            'gradeable_id' => $gradeable->getId(),
            'gradeable_version' => $display_version
        ]);

        $param = array_merge($param, [
            'gradeable_id' => $gradeable->getId(),
            'has_manual_grading' => $gradeable->isTaGrading(),
            // TODO: change this to submitter ID when the MiscController uses new model
            'user_id' => $this->core->getUser()->getId(),
            'team_assignment' => $gradeable->isTeamAssignment(),
            'team_members' => $gradeable->isTeamAssignment() ? $graded_gradeable->getSubmitter()->getTeam()->getMemberList() : [],
            'display_version' => $display_version,
            'active_version' => $active_version_number,
            'cancel_url' => $cancel_url,
            'change_version_url' => $change_version_url,
            'view_version_url' => $view_version_url,
            'check_refresh_submission_url' => $check_refresh_submission_url,
            'versions' => $version_data,
            'total_points' => $autograding_config->getTotalNonHiddenNonExtraCredit(),
            'allowed_late_days' => $gradeable->getLateDays(),

            'ta_grades_released' => $gradeable->isTaGradeReleased(),
            'is_vcs' => $gradeable->isVcs(),
            'can_download' => $can_download,
            'can_change_submissions' => $this->core->getUser()->accessGrading() || $gradeable->isStudentSubmit(),
            'can_see_all_versions' => $this->core->getUser()->accessGrading() || $gradeable->isStudentDownloadAnyVersion(),
            'show_testcases' => $show_testcases,
            'active_same_as_graded' => $active_same_as_graded,
            'show_incentive_message' => $show_incentive_message
        ]);

        return $this->core->getOutput()->renderTwigTemplate('submission/homework/CurrentVersionBox.twig', $param);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param bool $regrade_available
     * @return string
     */
    private function renderTAResultsBox(GradedGradeable $graded_gradeable, bool $regrade_available): string {
        $rendered_ta_results = '';
        $been_ta_graded = false;
        if ($graded_gradeable->isTaGradingComplete()) {
            $been_ta_graded = true;
            $rendered_ta_results = $this->core->getOutput()->renderTemplate('AutoGrading', 'showTAResultsNew',
                $graded_gradeable->getTaGradedGradeable(), $regrade_available, $graded_gradeable->getAutoGradedGradeable()->getActiveVersionInstance()->getFiles());
        }
        return $this->core->getOutput()->renderTwigTemplate('submission/homework/TAResultsBox.twig', [
            'been_ta_graded' => $been_ta_graded,
            'rendered_ta_results' => $rendered_ta_results
        ]);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @return string
     */
    private function renderRegradeBox(GradedGradeable $graded_gradeable): string {
        return $this->core->getOutput()->renderTwigTemplate('submission/homework/RegradeBox.twig', [
            'graded_gradeable' => $graded_gradeable
        ]);
    }
    
    /**
     * @param GradedGradeable $graded_gradeable
     * @return string
     */
    public function showRegradeDiscussion(GradedGradeable $graded_gradeable): string {
        $regrade_message = $this->core->getConfig()->getRegradeMessage();
        if (!$graded_gradeable->hasRegradeRequest() && !$this->core->getUser()->accessGrading()) {
            $btn_type = 'request';
            $url = $this->core->buildUrl(array('component' => 'student',
                'gradeable_id' => $graded_gradeable->getGradeable()->getId(),
                'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
                'action' => 'request_regrade',
            ));
            $action = 'request_regrade';
        } else if ($this->core->getUser()->accessGrading()) {
            if(!$graded_gradeable->hasRegradeRequest()){
                //incase a TA/instructor wants to open a regrade discussion with a student
                $btn_type = 'request';
                $url = $this->core->buildUrl(array('component' => 'student',
                    'gradeable_id' => $graded_gradeable->getGradeable()->getId(),
                    'submitter_id' => $this->core->getUser()->getId(),
                    'action' => 'request_regrade',
                 ));
                $action = 'request_regrade';
            }
            else if ($graded_gradeable->hasActiveRegradeRequest()) {
                $btn_type = 'admin_open';
                $url = $this->core->buildUrl(array('component' => 'student',
                    'gradeable_id' => $graded_gradeable->getGradeable()->getId(),
                    'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
                    'action' => 'make_request_post',
                    'resolved' => false
                ));
                $action = 'make_request_post_admin';
            } else {
                $btn_type = 'admin_closed';
                $url = $this->core->buildUrl(array('component' => 'student',
                    'gradeable_id' => $graded_gradeable->getGradeable()->getId(),
                    'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
                    'action' => 'make_request_post',
                    'resolved' => true
                ));
                $action = 'make_request_post_admin';
            }
        } else if ($graded_gradeable->hasActiveRegradeRequest()) {
            $btn_type = 'pending';
            $url = $this->core->buildUrl(array('component' => 'student',
                'gradeable_id' => $graded_gradeable->getGradeable()->getId(),
                'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
                'action' => 'make_request_post',
            ));
            $action = 'make_request_post';
        } else {
            $btn_type = 'completed';
            $url = $this->core->buildUrl(array('component' => 'student',
                'gradeable_id' => $graded_gradeable->getGradeable()->getId(),
                'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
                'action' => 'make_request_post',
            ));
            $action = 'request_regrade';
        }

        $posts = [];

        if ($graded_gradeable->hasRegradeRequest()) {
            $threads = $this->core->getQueries()->getRegradeDiscussion($graded_gradeable->getRegradeRequest());
            foreach ($threads as $thread) {
                if (empty($threads)) break;
                $is_staff = $this->core->getQueries()->isStaffPost($thread['user_id']);
                $name = $this->core->getQueries()->getUserById($thread['user_id'])->getDisplayedFirstName();
                $date = DateUtils::parseDateTime($thread['timestamp'], $this->core->getConfig()->getTimezone());
                $content = $thread['content'];
                $posts[] = [
                    'is_staff' => $is_staff,
                    'date' => date_format($date, 'm/d/Y g:i A'),
                    'name' => $name,
                    'content' => $content
                ];

            }
        }
        return $this->core->getOutput()->renderTwigTemplate('submission/regrade/Discussion.twig', [
            'btn_type' => $btn_type,
            'url' => $url,
            'action' => $action,
            'posts' => $posts,
            'gradeable_id' => $graded_gradeable->getGradeableId(),
            'thread_id' => $graded_gradeable->hasRegradeRequest() ? $graded_gradeable->getRegradeRequest()->getId() : 0,
            'submitter_id' => $graded_gradeable->getSubmitter()->getId(),
            'regrade_message' => $regrade_message
        ]);
    }
}
