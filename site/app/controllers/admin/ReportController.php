<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\Output;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\LateDayInfo;
use app\models\gradeable\LateDays;
use app\models\gradeable\Mark;
use app\models\gradeable\Submitter;
use app\models\GradeSummary;
use app\models\User;

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

    public function generateGradeSummary(GradedGradeable $gg, LateDays $ld) {
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
                $this->addLateDays($ld->getLateDayInfoByGradeable($g), $entry);
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
        $user_gradeables = [];
        $team_gradeables = [];
        foreach ($this->core->getQueries()->getGradeableConfigs(null) as $g) {
            if ($g->isTeamAssignment()) {
                $team_gradeables = $g;
            } else {
                $user_gradeables[] = $g;
            }
        }

        // Array of graded gradeables, first indexed by gradeable id, then by user id (so multiple entries per team)
        $team_graded_gradeables = [];

        // Get the team gradeables first and, unfortunately, fully cache them
        foreach ($this->core->getQueries()->getGradedGradeables($team_gradeables, null, null, $sort_keys) as $gg) {
            /** @var GradedGradeable $gg */
            $team_graded_gradeables[$gg->getGradeableId()] = [];
            foreach ($gg->getSubmitter()->getTeam()->getMemberUserIds() as $user_id) {
                $team_graded_gradeables[$gg->getGradeableId()][$user_id] = $gg;
            }
        }

        // Array of graded gradeables for active user
        $user_graded_gradeables = [];
        foreach ($this->core->getQueries()->getGradedGradeables($user_gradeables, null, null, $sort_keys) as $gg) {
            /** @var GradedGradeable $gg */
            $gradeable = $gg->getGradeable();

            if ($current_user !== $gg->getSubmitter()->getId()) {
                if ($current_user !== null) {
                    // Merge the user gradeables with the team gradeables
                    $user_graded_gradeables = array_merge($user_graded_gradeables, array_map(function($arr) use ($team_gradeables, $current_user) {
                        $ggs = [];
                        foreach ($team_gradeables as $team_gradeable) {
                            /** @var Gradeable $team_gradeable */
                            // if the user doesn't have a team, MAKE THE USER A SUBMITTER
                            $ggs[] = $arr[$team_gradeable->getId()][$current_user] ?? new GradedGradeable($this->core, $team_gradeable, new Submitter($this->core, $current_user), []);
                        }
                    }, $team_graded_gradeables));

                    // Sort by due date.  Since our two lists are sorted, we can easily merge-sort
                    $graded_gradeables = [];
                    $i_team = 0;
                    $i_user = 0;
                    for($i = 0; $i < count($team_gradeables) + count($user_gradeables); ++$i) {
                        if ($team_gradeables[$i_team] < $user_gradeables[$i_user]) {
                            // TODO:
                        }
                    }
                    $late_days = new LateDays($this->core, $current_user, array_filter($graded_gradeables, function (GradedGradeable $gg) {
                        return $gg->getGradeable()->getType() === GradeableType::ELECTRONIC_FILE;
                    }));
                    foreach ($graded_gradeables as $ugg) {
                        $bucket = ucwords($gg->getGradeable()->getSyllabusBucket());
                        $user[$bucket][] = $this->generateGradeSummary($ugg, $late_days);
                    }
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
        }

        // Make sure to save the last user too
        // TODO: see the $current_user !== null if block. that code needs to go here too
        file_put_contents(FileUtils::joinPaths($base_path, $current_user . '_summary.json'), FileUtils::encodeJson($user));
        $this->core->addSuccessMessage("Successfully Generated Grade Summaries");
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }

    private function addLateDays(LateDayInfo $ldi, &$entry) {
        $status = $ldi->getStatus();

        if($status === LateDayInfo::STATUS_BAD) {
            $entry['score'] = 0;
        }

        // The report may need this to be different from the 'pretty' version returned from $ldi->getStatusMessage()
        $status_message = 'ERROR';
        switch($status) {
            case LateDayInfo::STATUS_GOOD:
                $status_message = 'Good';
                break;
            case LateDayInfo::STATUS_LATE:
                $status_message = 'Late';
                break;
            case LateDayInfo::STATUS_BAD:
                $status_message = 'Bad';
                break;
            case LateDayInfo::STATUS_NO_ACTIVE_VERSION:
                $status_message = 'No Submission';
                break;
        }
        $entry['status'] = $status_message;

        $late_days_charged = $ldi->getLateDaysCharged();
        if ($late_days_charged > 0) {

            // TODO:  DEPRECATE THIS FIELD
            $entry['days_late'] = $late_days_charged;

            // REPLACED BY:
            $entry['days_after_deadline'] = $ldi->getDaysLate();
            $entry['extensions'] = $ldi->getLateDayException();
            $entry['days_charged'] = $ldi->getLateDaysCharged();
        }
        else {
            $entry['days_late'] = 0;
        }
    }
}

