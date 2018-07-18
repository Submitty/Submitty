<?php

namespace app\views\submission;

use app\models\gradeable\AutoGradedTestcase;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedComponent;
use app\models\gradeable\GradedGradeable;
use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;

class HomeworkView extends AbstractView {

    public function unbuiltGradeable(Gradeable $gradeable) {
        return $this->core->getOutput()->renderTwigTemplate("error/UnbuiltGradeable.twig", [
            "gradeable" => $gradeable
        ]);
    }

    /**
     * FIXME: will throw exception when admin tries to access with no team on team assignments
     * @param GradedGradeable $graded_gradeable
     * @param int $late_days_use
     * @param int $extensions
     * @param bool $canViewWholeGradeable
     * @return string
     */
    public function showGradeable(GradedGradeable $graded_gradeable, int $display_version, int $late_days_use, int $extensions, bool $canViewWholeGradeable = false) {
        $return = "";

        $this->core->getOutput()->addInternalJs("drag-and-drop.js");
        $gradeable = $graded_gradeable->getGradeable();

        // FIXME: uncomment when working
        // $return .= $this->renderLateDayMessage($graded_gradeable, $extensions);

        // showing submission if user is grader or student can submit
        if ($this->core->getUser()->accessGrading() || $gradeable->isStudentSubmit()) {
            $return .= $this->renderSubmitBox($graded_gradeable, $late_days_use);
        }
        $all_directories = $gradeable->getSplitPdfFiles();
        if ($this->core->getUser()->accessGrading() && count($all_directories) > 0) {
            $return .= $this->renderBulkUploadBox($gradeable);
        }

        /*
          See #1624 and #1967

          if (!$this->core->getOutput()->bufferOutput()) {
              echo $return;
              $return = "";
          }
         */

        $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();
        $submission_count = $auto_graded_gradeable !== null ? $auto_graded_gradeable->getHighestVersion() : 0;
        $active_version = $auto_graded_gradeable !== null ? $auto_graded_gradeable->getActiveVersion() : 0;
        if ($submission_count === 0) {
            $return .= $this->renderNoSubmissionBox($graded_gradeable);
        } else {
            $return .= $this->renderVersionBox($graded_gradeable, $display_version, $canViewWholeGradeable);
        }

        if ($gradeable->isTaGradeReleased()
            && $gradeable->isTaGrading()
            && $submission_count !== 0
            && $active_version !== 0) {
            $return .= $this->renderTAResultsBox($graded_gradeable);
        }
        if ($this->core->getConfig()->isRegradeEnabled()
            && $gradeable->isTaGradeReleased()
            && $gradeable->isTaGrading()
            && $graded_gradeable->isTaGradingComplete()
            && $submission_count !== 0) {
            $return .= $this->renderRegradeBox($graded_gradeable);
        }
        return $return;
    }

    /**
    // TODO: waiting on late days model first
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
            $messages[] = ["type" => "extension", "info" => [
                "extensions" => $extensions
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
                $messages[] = ["type" => "too_few_remain", "info" => [
                    "late" => $active_days_late,
                    "remaining" => $late_days_remaining
                ]];
            } // BAD STATUS - AUTO ZERO BECAUSE TOO MANY LATE DAYS USED ON THIS ASSIGNMENT
            else if ($active_days_charged > $late_days_allowed) {
                $error = true;
                $messages[] = ["type" => "too_many_used", "info" => [
                    "late" => $active_days_late,
                    "charged" => $active_days_charged,
                    "allowed" => $late_days_allowed
                ]];
            } // LATE STATUS
            else {
                $messages[] = ["type" => "late", "info" => [
                    "late" => $active_days_late,
                    "charged" => $active_days_charged,
                    "remaining" => $late_days_remaining
                ]];
            }
            if ($error) {
                //You're going to get a zero
                $messages[] = ["type" => "getting_zero"];
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
                $messages[] = ["type" => "would_late", "info" => [
                    "late" => $would_be_days_late
                ]];

                // SUBMISSION NOW WOULD BE BAD STATUS -- INSUFFICIENT LATE DAYS
                if ($new_late_charged > $late_days_remaining) {
                    $messages[] = ["type" => "would_too_few_remain", "info" => [
                        "remaining" => $late_days_remaining
                    ]];
                    $error = true;
                    $messages[] = ["type" => "would_get_zero"];
                } // SUBMISSION NOW WOULD BE BAD STATUS -- EXCEEDS LIMIT FOR THIS ASSIGNMENT
                else if ($new_late_charged > $late_days_allowed) {
                    $messages[] = ["type" => "would_too_many_used", "info" => [
                        "allowed" => $late_days_allowed
                    ]];
                    $error = true;
                    $messages[] = ["type" => "would_get_zero"];
                } // SUBMISSION NOW WOULD BE LATE
                else {
                    $new_late_days_remaining = $late_days_remaining + $active_days_charged - $new_late_charged;
                    $messages[] = ["type" => "would_allowed", "info" => [
                        "charged" => $new_late_charged,
                        "remaining" => $new_late_days_remaining
                    ]];
                }
            }
        }

        // ------------------------------------------------------------
        // IN CASE OF AUTOMATIC ZERO, MAKE THE MESSAGE RED
        if ($error == true) {
            $messages[] = ["type" => "contact_instructor", "info" => [
                "extensions" => $extensions
            ]];
        }

        return $this->core->getOutput()->renderTwigTemplate("submission/homework/LateDayMessage.twig", [
            "messages" => $messages,
            "error" => $error
        ]);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param int $late_days_use
     * @return string
     */
    private function renderSubmitBox(GradedGradeable $graded_gradeable, int $late_days_use): string {
        $gradeable = $graded_gradeable->getGradeable();
        $student_page = $gradeable->isStudentPdfUpload();
        $students_full = [];
        $textboxes = $gradeable->getAutogradingConfig()->getTextboxes();
        $old_files = [];

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
                $students_version[] = array($g->getUser(), $g->getActiveVersion());
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
                        "test_input",
                        $gradeable->getId(),
                        $image_name
                    );
                    $content_type = FileUtils::getContentType($imgPath);
                    if (substr($content_type, 0, 5) === "image") {
                        // Read image path, convert to base64 encoding
                        $textBoxImageData = base64_encode(file_get_contents($imgPath));
                        // Format the image SRC:  data:{mime};base64,{data};
                        $textBoximagesrc = 'data: ' . mime_content_type($imgPath) . ';charset=utf-8;base64,' . $textBoxImageData;
                        // insert the sample image data

                        $image_data[$image_name] = $textBoximagesrc;
                    }
                }
            }

            $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();
            $version = $auto_graded_gradeable !== null ? $auto_graded_gradeable->getActiveVersion() : null;
            if($version !== null) {
                for ($i = 1; $i <= $gradeable->getAutogradingConfig()->getNumParts(); $i++) {
                    foreach ($auto_graded_gradeable->getAutoGradedVersions()[$version]->getPartFiles($i) as $file) {
                        $size = number_format($file['size'] / 1024, 2);
                        // $escape_quote_filename = str_replace('\'','\\\'',$file['name']);
                        if (substr($file['relative_name'], 0, strlen("part{$i}/")) === "part{$i}/") {
                            $escape_quote_filename = str_replace('\'', '\\\'', substr($file['relative_name'], strlen("part{$i}/")));
                        } else {
                            $escape_quote_filename = str_replace('\'', '\\\'', $file['relative_name']);
                        }

                        $old_files[] = ["name" => $escape_quote_filename, "size" => $size, "part" => $i];
                    }
                }
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("submission/homework/SubmitBox.twig", [
            "gradeable" => $gradeable,
            "student_page" => $student_page,
            "students_full" => $students_full,
            "late_days_use" => $late_days_use,
            "old_files" => $old_files,
            "textboxes" => $textboxes,
            "image_data" => $image_data,
            "upload_message" => $this->core->getConfig()->getUploadMessage()
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
            $dir_files = $content["files"];

            foreach ($dir_files as $filename => $details) {
                $clean_timestamp = str_replace("_", " ", $timestamp);
                $path = rawurlencode(htmlspecialchars($details["path"]));
                if (strpos($filename, "cover") === false) {
                    continue;
                }
                // get the full filename for PDF popout
                // add "timestamp / full filename" to count_array so that path to each filename is to the full PDF, not the cover
                $filename = rawurlencode(htmlspecialchars($filename));
                $url = $this->core->getConfig()->getSiteUrl() . "&component=misc&page=display_file&dir=uploads&file=" . $filename . "&path=" . $path . "&ta_grading=false";
                $filename_full = str_replace("_cover.pdf", ".pdf", $filename);
                $path_full = str_replace("_cover.pdf", ".pdf", $path);
                $url_full = $this->core->getConfig()->getSiteUrl() . "&component=misc&page=display_file&dir=uploads&file=" . $filename_full . "&path=" . $path_full . "&ta_grading=false";
                $count_array[$count] = FileUtils::joinPaths($timestamp, rawurlencode($filename_full));
                //decode the filename after to display correctly for users
                $filename_full = rawurldecode($filename_full);
                $files[] = [
                    "clean_timestamp" => $clean_timestamp,
                    "filename_full" => $filename_full,
                    "url" => $url,
                    "url_full" => $url_full,
                ];
                $count++;
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("submission/homework/BulkUploadBox.twig", [
            "gradeable" => $gradeable,
            "count_array" => $count_array,
            "files" => $files,
        ]);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @return string
     */
    private function renderNoSubmissionBox(GradedGradeable $graded_gradeable): string {
        return $this->core->getOutput()->renderTwigTemplate("submission/homework/NoSubmissionBox.twig", [
            'team_assignment' => $graded_gradeable->getGradeable()->isTeamAssignment(),
            'member_list' => $graded_gradeable->getSubmitter()->getTeam()->getMemberList()
        ]);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param int $display_version
     * @param bool $show_hidden
     * @return string
     */
    private function renderVersionBox(GradedGradeable $graded_gradeable, int $display_version, bool $show_hidden): string {
        $gradeable = $graded_gradeable->getGradeable();
        $autograding_config = $gradeable->getAutogradingConfig();
        if ($graded_gradeable->hasAutoGradingInfo()) {
            $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();
            $active_version_number = $auto_graded_gradeable->getActiveVersion();
            $version_instance = $auto_graded_gradeable->getAutoGradedVersions()[$display_version] ?? null;

            $testcase_array = array_map(function(AutoGradedTestcase $testcase) {
                return [
                    'name' => $testcase->getTestcase()->getName(),
                    'hidden' => $testcase->getTestcase()->isHidden(),
                    'has_details' => $testcase->getTestcase()->getDetails() !== '',
                    'details' => $testcase->getTestcase()->getDetails(),
                    'has_points' => $testcase->getTestcase()->getPoints() !== 0,
                    'extra_credit' => $testcase->getTestcase()->isExtraCredit(),
                    'view_testcase_message' => $testcase->getTestcase()->canViewTestcaseMessage(),
                    'points_total' => $testcase->getTestcase()->getPoints(),
                    'points' => $testcase->getPoints(),
                    'can_view' => $testcase->canView(),
                    'testcase_message' => $testcase->getMessage()
                ];
            }, $version_instance->getTestcases());

            if ($version_instance === null) {
                // Sanity check for debugging
                throw new \InvalidArgumentException('Requested version out of bounds!');
            }

            // if not active version and student cannot see any more than active version
            $can_download = !$gradeable->isVcs()
                && $gradeable->isStudentDownload()
                && ($active_version_number === $display_version || $gradeable->isStudentDownloadAnyVersion());

            $num_visible_testcases = 0;
            foreach ($version_instance->getTestcases() as $testcase) {
                if ($testcase->canView()) {
                    $num_visible_testcases++;
                }
            }
            $active_same_as_graded = true;
            if ($active_version_number !== 0 || $display_version !== 0) {
                if ($graded_gradeable->hasTaGradingInfo()) {
                    /** @var GradedComponent[] $graded_components */
                    foreach ($graded_gradeable->getTaGradedGradeable()->getGradedComponents() as $graded_components) {
                        foreach ($graded_components as $component_grade) {
                            if ($component_grade->getGradedVersion() !== $active_version_number
                                && $component_grade->getGradedVersion() !== -1) {
                                $active_same_as_graded = false;
                                break;
                            }
                        }
                    }
                }
            }

            // Grafted from AutoGradingView::showResults
            //

            $has_badges = false;

            $nonhidden_earned = 0;
            $nonhidden_max = 0;
            $hidden_earned = 0;
            $hidden_max = 0;
            $show_hidden_breakdown = false;
            $display_hidden = false;

            if ($autograding_config->getTotalNonHidden() >= 0) {
                $has_badges = true;

                $nonhidden_earned = $version_instance->getNonHiddenPoints();
                $nonhidden_max = $autograding_config->getTotalNonHiddenNonExtraCredit();
                $hidden_earned = $version_instance->getTotalPoints();
                $hidden_max = $autograding_config->getTotalNonExtraCredit();

                $show_hidden_breakdown = ($version_instance->getNonHiddenNonExtraCredit() + $version_instance->getHiddenNonExtraCredit() > $autograding_config->getTotalNonHiddenNonExtraCredit()) && $show_hidden;

                $display_hidden = false;
                if ($gradeable->isTaGradeReleased()) {
                    foreach ($version_instance->getTestcases() as $testcase) {
                        if (!$testcase->canView()) continue;
                        if ($testcase->getTestcase()->isHidden()) {
                            $display_hidden = true;
                            break;
                        }
                    }
                }
            }

            //
            // End Graft

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

            $check_refresh_submission_url = $this->core->buildUrl([
                'component' => 'student',
                'page' => 'submission',
                'action' => 'check_refresh',
                'gradeable_id' => $gradeable->getId(),
                'gradeable_version' => $display_version
            ]);

            $show_incentive_message = $autograding_config->hasEarlySubmissionIncentive()
                && $active_version_number > 0
                && $version_instance->getEarlyIncentivePoints() >= $autograding_config->getEarlySubmissionMinimumPoints()
                && $version_instance->getDaysEarly() > $autograding_config->getEarlySubmissionMinimumDaysEarly();

            $history = $version_instance->getLatestHistory();
            $param = [
                "team_assignment" => $gradeable->isTeamAssignment(),
                "team_members" => $gradeable->isTeamAssignment() ? $graded_gradeable->getSubmitter()->getTeam()->getMemberList() : [],
                "display_version" => $display_version,
                "active_version" => $active_version_number,
                "cancel_url" => $cancel_url,
                "change_version_url" => $change_version_url,
                'check_refresh_submission_url' => $check_refresh_submission_url,
                "files" => $version_instance->getFiles(),
                'testcases' => $testcase_array,

                'gradeable_id',
                'user_id',

                "num_visible_testcases" => $num_visible_testcases,
                "show_hidden_breakdown" => $show_hidden_breakdown,
                "display_hidden" => $display_hidden,
                "nonhidden_earned" => $nonhidden_earned,
                "nonhidden_max" => $nonhidden_max,
                "hidden_earned" => $hidden_earned,
                "hidden_max" => $hidden_max,
                "has_badges" => $has_badges,
                'ta_grades_released' => $gradeable->isTaGradeReleased(),

                'is_ta_grading_complete' => $graded_gradeable->isTaGradingComplete(),
                "is_vcs" => $gradeable->isVcs(),
                "can_download" => $can_download,
                "cant_change_submissions" => $this->core->getUser()->accessGrading() || $gradeable->isStudentSubmit(),
                "cant_see_all_versions" => $this->core->getUser()->accessGrading() || $gradeable->isStudentDownloadAnyVersion(),
                "show_testcases" => $num_visible_testcases > 0,
                "show_hidden" => $show_hidden,
                "active_same_as_graded" => $active_same_as_graded,
                'show_incentive_message' => $show_incentive_message,
                'in_queue' => $version_instance->isQueued(),
                'grading' => $version_instance->isGrading()
            ];
            if ($history !== null) {
                $param = array_merge($param, [
                    'results' => 0,
                    'grade_time' => $history->getGradeTime(),
                    'num_autogrades' => $version_instance->getHistoryCount(),
                    'grading_finished' => $history->getGradingFinished(),
                    'wait_time' => $history->getWaitTime(),
                    'revision' => $history->getVcsRevision(),
                    'submission_time' => $version_instance->getSubmissionTime(),
                    'days_late' => $version_instance->getDaysLate()
                ]);
            }

            if($version_instance->isQueued()) {
                $param = array_merge($param, [
                    'queue_pos' => $version_instance->getQueuePosition(),
                    'queue_total' => $this->core->getGradingQueue()->getQueueCount()
                ]);
            }

            return $this->core->getOutput()->renderTwigTemplate("submission/homework/CurrentVersionBox.twig", $param);
        }
        return $this->core->getOutput()->renderTwigTemplate("submission/homework/CurrentVersionBox.twig", [
            "gradeable" => $gradeable,
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    private function renderTAResultsBox(Gradeable $gradeable): string {
        return $this->core->getOutput()->renderTwigTemplate("submission/homework/TAResultsBox.twig", [
            "gradeable" => $gradeable
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    private function renderRegradeBox(Gradeable $gradeable): string {
        return $this->core->getOutput()->renderTwigTemplate("submission/homework/RegradeBox.twig", [
            "gradeable" => $gradeable
        ]);
    }
    
    /**
     * @param Gradeable $gradeable
     * @return string
     */
    public function showRegradeDiscussion(Gradeable $gradeable): string {
        $regradeMessage = $this->core->getConfig()->getRegradeMessage();
        if ($gradeable->getRegradeStatus() === 0) {
            $btn_type = "request";
            $url = $this->core->buildUrl(array('component' => 'student',
                'action' => 'request_regrade',
                'gradeable_id' => $gradeable->getId(),
                'student_id' => $gradeable->getUser()->getId(),
                'regrade_id' => $this->core->getQueries()->getRegradeRequestID($gradeable->getId(), $gradeable->getUser()->getId())
            ));
            $action = 'request_regrade';
        } 
        else if($this->core->getUser()->accessGrading()){
            if($gradeable->getRegradeStatus() === -1){
                $btn_type = "admin_open";
                    $url = $this->core->buildUrl(array('component' => 'student',
                        'action' => 'make_request_post',
                        'gradeable_id' => $gradeable->getId(),
                        'user_id' => $this->core->getUser()->getId(),
                        'regrade_id' => $this->core->getQueries()->getRegradeRequestID($gradeable->getId(), $gradeable->getUser()->getId()),
                        'resolved' => false
                    ));
                $action = 'make_request_post_admin';
            }
            else{
                $btn_type = "admin_closed";
                    $url = $this->core->buildUrl(array('component' => 'student',
                        'action' => 'make_request_post',
                        'gradeable_id' => $gradeable->getId(),
                        'user_id' => $this->core->getUser()->getId(),
                        'regrade_id' => $this->core->getQueries()->getRegradeRequestID($gradeable->getId(), $gradeable->getUser()->getId()),
                        'resolved' => true
                    ));
                $action = 'make_request_post_admin';
            }
        }
        else if ($gradeable->getRegradeStatus() === -1) {
            $btn_type = "pending";
            $url = $this->core->buildUrl(array('component' => 'student',
                'action' => 'make_request_post',
                'gradeable_id' => $gradeable->getId(),
                'user_id' => $gradeable->getUser()->getId(),
                'regrade_id' => $this->core->getQueries()->getRegradeRequestID($gradeable->getId(), $gradeable->getUser()->getId())
            ));
            $action = 'make_request_post';
        } else {
            $btn_type = "completed";
            $url = $this->core->buildUrl(array('component' => 'student',
                'gradeable_id' => $gradeable->getId(),
                'user_id' => $gradeable->getUser()->getId(),
                'action' => 'make_request_post',
                'regrade_id' => $this->core->getQueries()->getRegradeRequestID($gradeable->getId(), $gradeable->getUser()->getId())
            ));
            $action = 'request_regrade';
        }
        $thread_id = $this->core->getQueries()->getRegradeRequestID($gradeable->getId(), $gradeable->getUser()->getId());
        $threads = $this->core->getQueries()->getRegradeDiscussion($thread_id);

        $posts = [];

        foreach ($threads as $thread) {
            if (empty($threads)) break;
            $is_staff = $this->core->getQueries()->isStaffPost($thread['user_id']);
            $name = $this->core->getQueries()->getUserById($thread['user_id'])->getDisplayedFirstName();
            $date = date_create($thread['timestamp']);
            $content = $thread['content'];
            $posts[] = [
                "is_staff" => $is_staff,
                "date" => date_format($date, "m/d/Y g:i A"),
                "name" => $name,
                "content" => $content
            ];

        }
        return $this->core->getOutput()->renderTwigTemplate("submission/regrade/Discussion.twig", [
            "btn_type" => $btn_type,
            "url" => $url,
            "action" => $action,
            "posts" => $posts,
            "gradeable" => $gradeable,
            "thread_id" => $thread_id,
            "regradeMessage" => $regradeMessage
        ]);
    }
}
