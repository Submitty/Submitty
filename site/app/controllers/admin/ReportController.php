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

/**
 * Class ReportController
 * @package app\controllers\admin
 *
 */
class ReportController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
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
            if ($current_user !== $current_user->getId()) {
                if (!is_null($current_user)) {
                    //Previous pass completed an entire row.  Push that row to CSV data text.
                    $csv .= implode(',', $row) . PHP_EOL;
                }

                //Prepare new user row
                $current_user = $current_user->getId();
                $row['User ID'] = $current_user->getId();
                $row['First Name'] = $current_user->getDisplayedFirstName();
                $row['Last Name'] = $current_user->getDisplayedLastName();
                $row['Registration Section'] = $current_user->getRegistrationSection();
            }

            //Append one gradeable score to row.  Scores are indexed by gradeable's ID.
            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                //Should any case prove true, something is wrong with electronic gradeable.  Grade is zero.
                switch (true) {
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
            } else {
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
        $g_sort_keys = [
            'gradeable_type',
            'CASE WHEN submission_due_date IS NOT NULL THEN submission_due_date ELSE grade_released_date END',
            'g_id',
        ];
        $gg_sort_keys = [
            'user_id',
        ];

        // Get all gradeables and split into user/team
        $user_gradeables = [];
        $team_gradeables = [];
        $all_gradeables = [];
        foreach ($this->core->getQueries()->getGradeableConfigs(null, $g_sort_keys) as $g) {
            $all_gradeables[] = $g;
            if ($g->isTeamAssignment()) {
                $team_gradeables[] = $g;
            } else {
                $user_gradeables[] = $g;
            }
        }

        // Array of team graded gradeables, first indexed by gradeable id, then by user id (so multiple entries per team)
        $team_graded_gradeables = [];

        // Get the team gradeables first and, unfortunately, fully cache them
        foreach ($this->core->getQueries()->getGradedGradeables($team_gradeables) as $gg) {
            /** @var GradedGradeable $gg */
            $team_graded_gradeables[$gg->getGradeableId()] = [];
            foreach ($gg->getSubmitter()->getTeam()->getMemberUserIds() as $user_id) {
                $team_graded_gradeables[$gg->getGradeableId()][$user_id] = $gg;
            }
        }

        /** @var User $current_user */
        $current_user = null;
        // Array of graded gradeables for active user
        $user_graded_gradeables = [];
        // Get all graded gradeables for user gradeables, grouping by user
        foreach ($this->core->getQueries()->getGradedGradeables($user_gradeables, null, null, $gg_sort_keys) as $gg) {
            /** @var GradedGradeable $gg */

            if ($current_user !== $gg->getSubmitter()->getUser()) {
                if ($current_user !== null) {
                    $this->saveUserToFile($base_path, $current_user, $all_gradeables, $user_graded_gradeables, $team_graded_gradeables);
                }
                $current_user = $gg->getSubmitter()->getUser();
                $user = [];
                $user['user_id'] = $current_user->getId();
                $user['legal_first_name'] = $current_user->getLegalFirstName();
                $user['preferred_first_name'] = $current_user->getPreferredFirstName();
                $user['legal_last_name'] = $current_user->getLegalLastName();
                $user['preferred_last_name'] = $current_user->getPreferredLastName();
                $user['registration_section'] = $current_user->getRegistrationSection();
                $user['default_allowed_late_days'] = $this->core->getConfig()->getDefaultStudentLateDays();
                $user['last_update'] = date("l, F j, Y");
                $user_graded_gradeables = [];
            }
            $user_graded_gradeables[$gg->getGradeableId()] = $gg;
        }

        // Make sure to save the last user too
        if ($current_user !== null) {
            $this->saveUserToFile($base_path, $current_user, $all_gradeables, $user_graded_gradeables, $team_graded_gradeables);
        }
        $this->core->addSuccessMessage("Successfully Generated Grade Summaries");
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
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
                    $this->addLateDays($ld->getLateDayInfoByGradeable($gg->getGradeable()), $entry);
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

    private function saveUserToFile(string $base_path, User $user, array $gradeables, array $user_graded_gradeables, array $team_graded_gradeables) {
        // Merge the user gradeables with the team gradeables
        $ggs = [];
        foreach ($gradeables as $g) {
            /** @var Gradeable $g */
            if ($g->isTeamAssignment()) {
                // if the user doesn't have a team, MAKE THE USER A SUBMITTER
                $ggs[] = $team_graded_gradeables[$g->getId()][$user->getId()] ?? new GradedGradeable($this->core, $g, new Submitter($this->core, $user), []);
            } else {
                $ggs[] = $user_graded_gradeables[$g->getId()];
            }
        }

        $late_days = new LateDays($this->core, $user, array_filter($ggs, function (GradedGradeable $gg) {
            return $gg->getGradeable()->getType() === GradeableType::ELECTRONIC_FILE;
        }));
        foreach ($ggs as $gg) {
            $bucket = ucwords($gg->getGradeable()->getSyllabusBucket());
            $user[$bucket][] = $this->generateGradeSummary($gg, $late_days);
        }
        file_put_contents(FileUtils::joinPaths($base_path, $user->getId() . '_summary.json'), FileUtils::encodeJson($user));
    }


    private function getLateStatusMessage($status) {
        switch ($status) {
            case LateDayInfo::STATUS_GOOD:
                return 'Good';
            case LateDayInfo::STATUS_LATE:
                 return 'Late';
            case LateDayInfo::STATUS_BAD:
                return 'Bad';
            case LateDayInfo::STATUS_NO_ACTIVE_VERSION:
                return 'No Submission';
            default:
                return 'ERROR';
        }
    }

    private function addLateDays(LateDayInfo $ldi, &$entry) {
        $status = $ldi->getStatus();

        if ($status === LateDayInfo::STATUS_BAD) {
            $entry['score'] = 0;
        }

        // The report may need this to be different from the 'pretty' version returned from $ldi->getStatusMessage()
        $entry['status'] = $this->getLateStatusMessage($status);

        $late_days_charged = $ldi->getLateDaysCharged();
        if ($late_days_charged > 0) {

            // TODO:  DEPRECATE THIS FIELD
            $entry['days_late'] = $late_days_charged;

            // REPLACED BY:
            $entry['days_after_deadline'] = $ldi->getDaysLate();
            $entry['extensions'] = $ldi->getLateDayException();
            $entry['days_charged'] = $ldi->getLateDaysCharged();
        } else {
            $entry['days_late'] = 0;
        }
    }
}

