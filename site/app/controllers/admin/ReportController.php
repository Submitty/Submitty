<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\Output;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Mark;
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
                $row['First Name'] = $gradeable->getUser()->getDisplayedFirstName();
                $row['Last Name'] = $gradeable->getUser()->getDisplayedLastName();
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

    public function generateGradeSummary(GradedGradeable $gg) {
        $g = $gg->getGradeable();
        $autograding_score = $gg->getAutoGradedGradeable()->hasActiveVersion() ? $gg->getAutoGradedGradeable()->getTotalPoints() : 0;
        $ta_grading_score = $gg->hasTaGradingInfo() ? $gg->getTaGradedGradeable()->getTotalScore() : 0;

        $entry = [
            'id' => $g->getId(),
            'name' => $g->getTitle(),
            'gradeable_type' => GradeableType::typeToString($g->getType()),
            'grade_released_date' => $g->getGradeReleasedDate()->format('Y-m-d H:i:s O'),
        ];

        if ($g->getType() !== GradeableType::ELECTRONIC_FILE) {
            $entry['score'] = max(0, $ta_grading_score);
        } else {
            $ta_gg = $gg->getOrCreateTaGradedGradeable();
            $entry['overall_comment'] = $ta_gg->getOverallComment();

            if (!$ta_gg->hasVersionConflict() || !$g->isTaGrading()) {
                $entry['score'] = max(0, $autograding_score + $ta_grading_score);
                $entry['autograding_score'] = $autograding_score;
                $entry['tagrading_score'] = $ta_grading_score;
                $this->addLateDays($g, $entry);
            } else {
                $entry['score'] = 0;
                $entry['autograding_score'] = 0;
                $entry['tagrading_score'] = 0;
                if (!$ta_gg->isComplete()) {
                    $entry['note'] = 'This has not been graded yet.';
                    // can't be late if not submitted
                    $entry['days_late'] = 0;
                    $entry['status'] = 'unsubmitted';
                } elseif ($gg->getAutoGradedGradeable()->getActiveVersion() !== 0) {
                    $entry['note'] = 'Score is set to 0 because there are version conflicts.';
                    $this->addLateDays($g, $entry);
                }
            }
        }

        // Component/marks
        $entry['components'] = [];
        foreach ($g->getComponents() as $component) {
            $gcc = $gg->getOrCreateTaGradedGradeable()->getGradedComponentContainer($component);

            // iterate through each component container so we can account for peer grading
            foreach ($gcc->getGradedComponents() as $gc) {
                $inner = [
                    'title' => $component->getTitle()
                ];
                if ($component->isText()) {
                    $inner['comment'] = $gc->getComment();
                } else {
                    $inner['score'] = $gc->getTotalScore();
                    $inner['default_score'] = $component->getDefault();
                    $inner['upper_clamp'] = $component->getUpperClamp();
                    $inner['lower_clamp'] = $component->getLowerClamp();
                }

                if ($g->getType() === GradeableType::ELECTRONIC_FILE) {
                    $marks = array_map(function (Mark $m) {
                        return ['points' => $m->getPoints(), 'note' => $m->getTitle()];
                    }, $gc->getMarks());

                    if ($gc->hasCustomMark()) {
                        $marks[] = ['points' => $gc->getScore(), 'note' => $gc->getComment()];
                    }
                    $inner['marks'] = $marks;
                }
               $entry['components'][] = $inner;
            }
        }
        return $entry;
    }
    public function generateGradeSummaries() {
        $base_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'all_grades');
        $current_user = null;
        $user = [];
        $sort_keys = [
            'user_id',
            'g.g_gradeable_type',
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END',
            'g.g_id'
        ];

        // Get all gradeables
        $gradeables = [];
        foreach ($this->core->getQueries()->getGradeableConfigs(null) as $g) {
            $gradeables[] = $g;
        }

        // Array of graded gradeables, first indexed by gradeable id, then by user id (so multiple entries per team)
        $team_graded_gradeables = [];

        // Array of graded gradeables for active user
        $user_graded_gradeables = [];
        foreach ($this->core->getQueries()->getGradedGradeables($gradeables, null, null, $sort_keys) as $gg) {
            /** @var GradedGradeable $gg */
            if ($gg->getGradeable()->isTeamAssignment()) {
                $team_graded_gradeables[$getGradeableId]
            }
            if ($current_user !== $gradeable->getUser()->getId()) {
                if ($current_user !== null) {
                    file_put_contents(FileUtils::joinPaths($base_path, $current_user . '_summary.json'), FileUtils::encodeJson($user));
                }
                $current_user = $gradeable->getUser()->getId();
                $user = [];
                $user['user_id'] = $gradeable->getUser()->getId();
                $user['legal_first_name'] = $gradeable->getUser()->getLegalFirstName();
                $user['preferred_first_name'] = $gradeable->getUser()->getPreferredFirstName();
                $user['legal_last_name'] = $gradeable->getUser()->getLegalLastName();
                $user['preferred_last_name'] = $gradeable->getUser()->getPreferredLastName();
                $user['registration_section'] = $gradeable->getUser()->getRegistrationSection();
                $user['default_allowed_late_days'] = $this->core->getConfig()->getDefaultStudentLateDays();
                $user['last_update'] = date("l, F j, Y");
                $user_graded_gradeables = [];
            }
            $user_graded_gradeables[] = $gg;

            $bucket = ucwords($g->getSyllabusBucket());
            if (!isset($user[$bucket])) {
                $user[$bucket] = [];
            }
            $user[$bucket][] = $this->generateGradeSummary($gg);
        }

        file_put_contents(FileUtils::joinPaths($base_path, $current_user . '_summary.json'), FileUtils::encodeJson($user));
        $this->core->addSuccessMessage("Successfully Generated Grade Summaries");
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }

    // TODO: make this use the new late days model
    private function addLateDays(Gradeable $gradeable, &$entry) {
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

