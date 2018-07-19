<?php

namespace app\views\submission;

use app\models\Gradeable;
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
     * @param Gradeable $gradeable
     * @param int $late_days_use
     * @param int $extensions
     * @param bool $canViewWholeGradeable
     * @return string
     */
    public function showGradeable($gradeable, $late_days_use, $extensions, $canViewWholeGradeable = false) {
        $return = "";

        $this->core->getOutput()->addInternalJs("drag-and-drop.js");

        $return .= $this->renderLateDayMessage($gradeable, $extensions);
        // showing submission if user is grader or student can submit
        if ($this->core->getUser()->accessGrading() || $gradeable->getStudentSubmit()) {
            $return .= $this->renderSubmitBox($gradeable, $late_days_use);
        }
        $all_directories = $gradeable->getUploadsFiles();
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

        if ($gradeable->getSubmissionCount() === 0) {
            $return .= $this->renderNoSubmissionBox($gradeable);
        } else {
            $return .= $this->renderCurrentVersionBox($gradeable, $canViewWholeGradeable);
        }
        if ($gradeable->taGradesReleased() && $gradeable->useTAGrading() && $gradeable->getSubmissionCount() !== 0 && $gradeable->getActiveVersion()) {
            $return .= $this->renderTAResultsBox($gradeable);
        }
        if ($this->core->getConfig()->isRegradeEnabled() && $gradeable->taGradesReleased() && $gradeable->useTAGrading() && $gradeable->beenTAgraded() && $gradeable->getSubmissionCount() !== 0) {
            $return .= $this->renderRegradeBox($gradeable);
        }
        return $return;
    }

    /**
     * @param Gradeable $gradeable
     * @param int $extensions
     * @return string
     */
    public function renderLateDayMessage(Gradeable $gradeable, int $extensions) {
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
     * @param Gradeable $gradeable
     * @param int $late_days_use
     * @return string
     */
    private function renderSubmitBox(Gradeable $gradeable, int $late_days_use): string {
        $student_page = false;
        $students_full = [];
        $textboxes = [];
        $old_files = [];

        if ($this->core->getUser()->accessGrading()) {
            $students = $this->core->getQueries()->getAllUsers();
            $student_ids = array();
            foreach ($students as $student) {
                $student_ids[] = $student->getId();
            }

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

        if (!$gradeable->useVcsCheckout()) {
            for ($i = 0; $i < $gradeable->getNumTextBoxes(); $i++) {
                $textbox = $gradeable->getTextboxes()[$i];
                $textbox['index'] = $i;

                if (!isset($textbox['images']) || !is_array($textbox['images'])) {
                    $textbox['images'] = [];
                }

                foreach ($textbox['images'] as &$currImage) {
                    $currImageName = $currImage["image_name"];
                    $imgPath = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "test_input", $gradeable->getId(), $currImageName);
                    $content_type = FileUtils::getContentType($imgPath);
                    if (substr($content_type, 0, 5) === "image") {
                        // Read image path, convert to base64 encoding
                        $textBoxImageData = base64_encode(file_get_contents($imgPath));
                        // Format the image SRC:  data:{mime};base64,{data};
                        $textBoximagesrc = 'data: ' . mime_content_type($imgPath) . ';charset=utf-8;base64,' . $textBoxImageData;
                        // insert the sample image data

                        $currImage['src'] = $textBoximagesrc;
                    }
                }
                unset($currImage);

                $textboxes[] = $textbox;
            }
            // does this gradeable have parts assigned by students
            foreach ($gradeable->getComponents() as $question) {
                if (is_array($question)) {
                    $page_num = $question[0]->getPage();
                } else {
                    $page_num = $question->getPage();
                }
                if ($page_num === -1) {
                    $student_page = true;
                    break;
                }
            }

            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                foreach ($gradeable->getPreviousFiles($i) as $file) {
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

        return $this->core->getOutput()->renderTwigTemplate("submission/homework/SubmitBox.twig", [
            "gradeable" => $gradeable,
            "student_page" => $student_page,
            "students_full" => $students_full,
            "late_days_use" => $late_days_use,
            "old_files" => $old_files,
            "textboxes" => $textboxes,
            "upload_message" => $this->core->getConfig()->getUploadMessage()
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    private function renderBulkUploadBox(Gradeable $gradeable): string {
        $all_directories = $gradeable->getUploadsFiles();

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
     * @param Gradeable $gradeable
     * @return string
     */
    private function renderNoSubmissionBox(Gradeable $gradeable): string {
        return $this->core->getOutput()->renderTwigTemplate("submission/homework/NoSubmissionBox.twig", [
            "gradeable" => $gradeable
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @param bool $canViewWholeGradeable
     * @return string
     */
    private function renderCurrentVersionBox(Gradeable $gradeable, bool $canViewWholeGradeable): string {
        $current_version = $gradeable->getCurrentVersion();

        // if not active version and student cannot see any more than active version
        $can_download = !$gradeable->useVcsCheckout() && $gradeable->getStudentDownload() && !($gradeable->getCurrentVersionNumber() !== $gradeable->getActiveVersion() && !$gradeable->getStudentAnyVersion());

        $files = ($gradeable->useVcsCheckout()) ? $gradeable->getVcsFiles() : $gradeable->getSubmittedFiles();
        foreach ($files as &$file) {
            if (isset($file['size'])) {
                $file['size'] = number_format($file['size'] / 1024, 2);
            } else {
                $file['size'] = number_format(-1);
            }
        }
        unset($file); //Clean up reference

        $results = $gradeable->getResults();

        $show_testcases = false;
        foreach ($gradeable->getTestcases() as $testcase) {
            if ($testcase->viewTestcase()) {
                $show_testcases = true;
                break;
            }
        }

        $active_same_as_graded = true;
        if ($gradeable->getActiveVersion() !== 0 || $gradeable->getCurrentVersionNumber() !== 0) {
            foreach ($gradeable->getComponents() as $component) {
                if ($component->getGradedVersion() !== $gradeable->getActiveVersion() && $component->getGradedVersion() !== -1) {
                    $active_same_as_graded = false;
                    break;
                }
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("submission/homework/CurrentVersionBox.twig", [
            "gradeable" => $gradeable,
            "current_version" => $current_version,
            "can_download" => $can_download,
            "files" => $files,
            "results" => $results,
            "show_testcases" => $show_testcases,
            "canViewWholeGradeable" => $canViewWholeGradeable,
            "active_same_as_graded" => $active_same_as_graded,
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
