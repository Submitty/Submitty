<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\Output;
use app\models\Gradeable;
use app\models\GradeSummary;

/*
use app\report\CSVReportView;
use app\report\GradeSummaryView;
*/
class ReportController extends AbstractController {
    public function run() {
        switch($_REQUEST['action']) {
            case 'csv':
                $this->generateCSVReport();
                break;
            case 'summary':
                $this->generateGradeSummaries();
                break;
            case 'reportpage':
            default:
                $this->showReportPage();
                break;
        }
    }

    public function showReportPage() {
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }

    /** Generates and offers download of CSV grade report */
    public function generateCSVReport() {
        $current_user = null;
        $row = [];
        $csv = "";
        $order_by = [
            'g.g_syllabus_bucket',
            'g.g_id'
        ];

        //Gradeable iterator will append one gradeable score per loop pass.
        foreach ($this->core->getQueries()->getGradeablesIterator(null, true, 'registration_section', 'u.user_id', null, $order_by) as $gradeable) {
            if ($current_user !== $gradeable->getUser()->getId()) {
                if (!is_null($current_user)) {
                    //Previous pass completed an entire row.  Push that row to CSV data text.
                    $csv .= implode(',', $row) . PHP_EOL;
                }

                //Prepare new user row
                $current_user = $gradeable->getUser()->getId();
                $row['User ID'] = $gradeable->getUser()->getId();
                $row['First Name'] = (empty($gradeable->getUser()->getPreferredFirstName())) ? $gradeable->getUser()->getFirstName() : $gradeable->getUser()->getPreferredFirstName();
                $row['Last Name'] = $gradeable->getUser()->getLastName();
                $row['Registration Section'] = $gradeable->getUser()->getRegistrationSection();
            }

            //Append one gradeable score to row.  Scores are indexed by gradeable's ID.
            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                //Should any case prove true, something is wrong with electronic gradeable.  Grade is zero.
                switch(true) {
                //Active version fails validation AND uses TA grading.
                case !$gradeable->validateVersions() && $gradeable->useTAGrading():
                //Late assignment (status is "Bad")
                case $gradeable->getLateDays() - $gradeable->getLateDayExceptions() > $gradeable->getAllowedLateDays():
                    $row[$gradeable->getId()] = 0;
                    break;
                //Gradeable is OK.  Collect grade.
                default:
                    $row[$gradeable->getId()] = max(0, $gradeable->getGradedAutoGraderPoints() + $gradeable->getGradedTAPoints());
                }
            }
            else {
                //Gradeable is not an electronic file.  Collect TA grading.
                $row[$gradeable->getId()] = max(0, $gradeable->getGradedTAPoints());
            }
        } // End gradeable iterator loop

        //Push final row to csv.
        $csv .= implode(',', $row) . PHP_EOL;
        //Prepend header, which are the array indices of a row.
        $csv = implode(',', array_keys($row)) . PHP_EOL . $csv;
        //Send csv data to file download.  Filename: "{course}_csvreport_{date/time stamp}.csv"
        $this->core->getOutput()->renderFile($csv, $this->core->getConfig()->getCourse() . "_csvreport_" . date("ymdHis") . ".csv");
    }

    public function generateGradeSummaries() {
        $base_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'all_grades');
        $current_user = null;
        $total_late_used = 0;
        $user = [];
        $order_by = [
            'g.g_gradeable_type',
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END'
        ];
        foreach ($this->core->getQueries()->getGradeablesIterator(null, true, 'registration_section', 'u.user_id', null, $order_by) as $gradeable) {
            /** @var \app\models\Gradeable $gradeable */
            if ($current_user !== $gradeable->getUser()->getId()) {
                if ($current_user !== null) {
                    file_put_contents(FileUtils::joinPaths($base_path, $current_user.'_summary.json'), FileUtils::encodeJson($user));
                }
                $current_user = $gradeable->getUser()->getId();
                $user = [];
                $user['user_id'] = $gradeable->getUser()->getId();
                $user['legal_first_name'] = $gradeable->getUser()->getFirstName();
                $user['preferred_first_name'] = $gradeable->getUser()->getPreferredFirstName();
                $user['last_name'] = $gradeable->getUser()->getLastName();
                $user['registration_section'] = $gradeable->getUser()->getRegistrationSection();
                $user['default_allowed_late_days'] = $this->core->getConfig()->getDefaultStudentLateDays();
                $user['last_update'] = date("l, F j, Y");
                $total_late_used = 0;
            }
            $bucket = ucwords($gradeable->getBucket());
            if (!isset($user[$bucket])) {
                $user[$bucket] = [];
            }

            $autograding_score = $gradeable->getGradedAutoGraderPoints();
            $ta_grading_score = $gradeable->getGradedTAPoints();

            $entry = [
                'id' => $gradeable->getId(),
                'name' => $gradeable->getName(),
                'gradeable_type' => GradeableType::typeToString($gradeable->getType()),
                'grade_released_date' => $gradeable->getGradeReleasedDate()->format('Y-m-d H:i:s O'),
            ];

            if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
                $entry['score'] = max(0, $ta_grading_score);
            }
            else {
                $entry['overall_comment'] = $gradeable->getOverallComment();

                if ($gradeable->validateVersions() || !$gradeable->useTAGrading()) {
                    $entry['score'] = max(0, $autograding_score + $ta_grading_score);
                    $entry['autograding_score'] = $autograding_score;
                    $entry['tagrading_score'] = $ta_grading_score;
                    $this->addLateDays($gradeable, $entry, $total_late_used);
                }
                else {
                    $entry['score'] = 0;
                    $entry['autograding_score'] = 0;
                    $entry['tagrading_score'] = 0;
                    if ($gradeable->validateVersions(-1)) {
                        $entry['note'] = 'This has not been graded yet.';
                        // can't be late if not submitted
                        $entry['days_late'] = 0;
                        $entry['status'] = 'unsubmitted';
                    }
                    elseif ($gradeable->getActiveVersion() !== 0) {
                        $entry['note'] = 'Score is set to 0 because there are version conflicts.';
                        $this->addLateDays($gradeable, $entry, $total_late_used);
                    }
                }
            }

            $entry['components'] = [];
            foreach ($gradeable->getComponents() as $component) {
                $inner = [
                    'title' => $component->getTitle()
                ];

                if ($component->getIsText()) {
                    $inner['comment'] = $component->getComment();
                }
                else {
                    $inner['score'] = $component->getGradedTAPoints();
                    $inner['default_score'] = $component->getDefault();
                    $inner['upper_clamp'] = $component->getUpperClamp();
                    $inner['lower_clamp'] = $component->getLowerClamp();
                }

                if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                    $marks = [];
                    if ($component->getHasMarks()) {
                        foreach ($component->getMarks() as $mark) {
                            if ($mark->getHasMark()) {
                                $marks[] = ['points' => $mark->getPoints(), 'note' => $mark->getNote()];
                            }
                        }
                    }

                    if (!empty($component->getComment()) || $component->getScore() != 0) {
                        $marks[] = ['points' => $component->getScore(), 'note' => $component->getComment()];
                    }
                    $inner['marks'] = $marks;
                }
                $entry['components'][] = $inner;
            }

            $user[$bucket][] = $entry;
        }

        file_put_contents(FileUtils::joinPaths($base_path, $current_user.'_summary.json'), FileUtils::encodeJson($user));
        $this->core->addSuccessMessage("Successfully Generated Grade Summaries");
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }

    private function addLateDays(Gradeable $gradeable, &$entry, &$total_late_used) {
        $late_days_used = $gradeable->getLateDays() - $gradeable->getLateDayExceptions();
        $status = 'Good';
        $late_flag = false;

        if ($late_days_used > 0) {
            $status = "Late";
            $late_flag = true;
        }
        //If late days used - extensions applied > allowed per assignment then status is "Bad..."
        if ($late_days_used > $gradeable->getAllowedLateDays()) {
            $status = "Bad";
            $late_flag = false;
        }
        // If late days used - extensions applied > allowed per term then status is "Bad..."
        // Do a max(0, ...) to protect against the case where the student's late days goes down
        // during the semester and they've already used late days
        if ($late_days_used > max(0, $gradeable->getStudentAllowedLateDays() - $total_late_used)) {
            $status = "Bad";
            $late_flag = false;
        }

        //A submission cannot be late and bad simultaneously. If it's late calculate late days charged. Cannot
        //be less than 0 in cases of excess extensions. Decrement remaining late days.
        if ($late_flag) {
            $curr_late_charged = $late_days_used;
            $curr_late_charged = ($curr_late_charged < 0) ? 0 : $curr_late_charged;
            $total_late_used += $curr_late_charged;
        }

        if($status === 'Bad') {
            $entry['score'] = 0;
        }

        $entry['status'] = $status;

        if ($late_flag && $late_days_used > 0) {

            // TODO:  DEPRECATE THIS FIELD
            $entry['days_late'] = $late_days_used;

            // REPLACED BY:
            $entry['days_after_deadline'] = $gradeable->getLateDays();
            $entry['extensions'] = $gradeable->getLateDayExceptions();
            $entry['days_charged'] = $late_days_used;
        }
        else {
            $entry['days_late'] = 0;
        }
    }
}

