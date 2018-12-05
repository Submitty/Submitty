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

    /**
     * Loads all team graded gradeables
     * @param Gradeable[] $team_gradeables
     * @return array array, indexed by gradeable id, of arrays, indexed by user id, of team graded gradeables
     */
    private function cacheTeamGradedGradeables(array $team_gradeables) {
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

        return $team_graded_gradeables;
    }

    /**
     * Fetches all gradeables from the database and splits them based on teamness
     * @param array|null $sort_keys the keys used to sort the gradeables
     * @return array [gradeables, user gradeables, team gradeables
     */
    private function getSplitGradeables($sort_keys) {
        $user_gradeables = [];
        $team_gradeables = [];
        $all_gradeables = [];
        foreach ($this->core->getQueries()->getGradeableConfigs(null, $sort_keys) as $g) {
            $all_gradeables[] = $g;
            if ($g->isTeamAssignment()) {
                $team_gradeables[] = $g;
            } else {
                $user_gradeables[] = $g;
            }
        }
        return [$all_gradeables, $user_gradeables, $team_gradeables];
    }

    /**
     * Merges user and team graded gradeables for a user
     * @param Gradeable[] $gradeables
     * @param User $user
     * @param GradedGradeable[] $user_graded_gradeables User graded gradeables indexed by gradeable id
     * @param array $team_graded_gradeables See cacheTeamGradedGradeables
     * @return GradedGradeable[] array of graded gradeables in the order of $gradeables
     */
    private function mergeGradedGradeables(array $gradeables, User $user, array $user_graded_gradeables, array $team_graded_gradeables) {
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
        return $ggs;
    }

    /** Generates and offers download of CSV grade report */
    public function generateCSVReport() {
        /** @var User $current_user */
        $current_user = null;
        $rows = [];
        $g_sort_keys = [
            'syllabus_bucket',
            'g_id',
        ];
        $gg_sort_keys = [
            'registration_section',
            'user_id',
        ];

        // get all gradeables and cache team graded gradeables
        list($all_gradeables, $user_gradeables, $team_gradeables) = $this->getSplitGradeables($g_sort_keys);
        $team_graded_gradeables = $this->cacheTeamGradedGradeables($team_gradeables);

        //Gradeable iterator will append one gradeable score per loop pass.
        $user_graded_gradeables = [];
        foreach ($this->core->getQueries()->getGradedGradeables($user_gradeables, null, null, $gg_sort_keys) as $gg) {
            /** @var GradedGradeable $gg */
            if ($current_user === null || $current_user->getId() !== $gg->getSubmitter()->getId()) {
                if ($current_user !== null) {
                    // Previous pass completed an entire row
                    $rows[] = $this->generateCSVRow($all_gradeables, $current_user, $user_graded_gradeables, $team_graded_gradeables);
                }

                //Prepare for the new user row
                $current_user = $gg->getSubmitter()->getUser();
                $user_graded_gradeables = [];
            }
            $user_graded_gradeables[$gg->getGradeableId()] = $gg;
        }

        $csv = '';
        if (count($rows) > 0) {
            // Header row
            $csv = implode(',', array_keys($rows[0])) . PHP_EOL;
            // Content rows
            foreach ($rows as $row) {
                $csv .= implode(',', $row) . PHP_EOL;
            }
        }
        //Send csv data to file download.  Filename: "{course}_csvreport_{date/time stamp}.csv"
        $this->core->getOutput()->renderFile($csv, $this->core->getConfig()->getCourse() . "_csvreport_" . date("ymdHis") . ".csv");
    }

    /**
     * Generates a CSV row for a user
     * @param Gradeable[] $gradeables the gradeables to use for the report
     * @param User $user The user the grades are for
     * @param GradedGradeable[] $user_graded_gradeables The user graded gradeables, indexed by gradeable id
     * @param array $team_graded_gradeables The team graded gradeables, see cacheTeamGradedGradeables
     * @return array
     */
    private function generateCSVRow(array $gradeables, User $user, array $user_graded_gradeables, array $team_graded_gradeables) {
        $row = [];
        $ggs = $this->mergeGradedGradeables($gradeables, $user, $user_graded_gradeables, $team_graded_gradeables);
        $late_days = new LateDays($this->core, $user, $ggs);

        $row['User ID'] = $user->getId();
        $row['First Name'] = $user->getDisplayedFirstName();
        $row['Last Name'] = $user->getDisplayedLastName();
        $row['Registration Section'] = $user->getRegistrationSection();

        foreach ($ggs as $gg) {
            //Append one gradeable score to row.  Scores are indexed by gradeable's ID.
            $row[$gg->getGradeableId()] = $gg->getTotalScore();

            // Check if the score should be a zero
            if ($gg->getGradeable()->getType() === GradeableType::ELECTRONIC_FILE) {
                if ($gg->getGradeable()->isTaGrading() && $gg->getOrCreateTaGradedGradeable()->hasVersionConflict()) {
                    // Version conflict, so zero score
                    $row[$gg->getGradeableId()] = 0;
                } else if ($late_days->getLateDayInfoByGradeable($gg->getGradeable())->getStatus() === LateDayInfo::STATUS_BAD) {
                    // BAD submission, so zero score
                    $row[$gg->getGradeableId()] = 0;
                }
            }
        }
        return $row;
    }

    /** Generates grade summary files for every user */
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

        // get all gradeables and cache team graded gradeables
        list($all_gradeables, $user_gradeables, $team_gradeables) = $this->getSplitGradeables($g_sort_keys);
        $team_graded_gradeables = $this->cacheTeamGradedGradeables($team_gradeables);

        /** @var User $current_user */
        $current_user = null;
        // Array of graded gradeables for active user
        $user_graded_gradeables = [];
        // Get all graded gradeables for user gradeables, grouping by user
        foreach ($this->core->getQueries()->getGradedGradeables($user_gradeables, null, null, $gg_sort_keys) as $gg) {
            /** @var GradedGradeable $gg */

            if ($current_user === null || $current_user->getId() !== $gg->getSubmitter()->getUser()->getId()) {
                if ($current_user !== null) {
                    $this->saveUserToFile($base_path, $current_user, $all_gradeables, $user_graded_gradeables, $team_graded_gradeables);
                }
                $current_user = $gg->getSubmitter()->getUser();
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

    /**
     * Generates a grade summary entry for a graded gradeable
     * @param GradedGradeable $gg
     * @param LateDays $ld
     * @return array
     */
    public function generateGradeSummary(GradedGradeable $gg, LateDays $ld) {
        $g = $gg->getGradeable();

        $entry = [
            'id' => $g->getId(),
            'name' => $g->getTitle(),
            'gradeable_type' => GradeableType::typeToString($g->getType()),
            'grade_released_date' => $g->getGradeReleasedDate()->format('Y-m-d H:i:s O'),
        ];

        $entry['score'] = $gg->getTotalScore();
        $entry['autograding_score'] = $gg->getAutoGradingScore();
        $entry['tagrading_score'] = $gg->getTaGradingScore();
        $this->addLateDays($ld->getLateDayInfoByGradeable($g), $entry);

        if ($g->getType() === GradeableType::ELECTRONIC_FILE) {
            $ta_gg = $gg->getOrCreateTaGradedGradeable();
            $entry['overall_comment'] = $ta_gg->getOverallComment();

            if ($g->isTaGrading() && $ta_gg->hasVersionConflict()) {
                $entry['score'] = 0;
                $entry['autograding_score'] = 0;
                $entry['tagrading_score'] = 0;
                if (!$ta_gg->isComplete()) {
                    $entry['note'] = 'This has not been graded yet.';
                } elseif ($gg->getAutoGradedGradeable()->getActiveVersion() !== 0) {
                    $entry['note'] = 'Score is set to 0 because there are version conflicts.';
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

    /**
     * Saves all user data to a file
     * @param string $base_path the base path to store the reports
     * @param User $user The user the report is for
     * @param Gradeable[] $gradeables All gradeables for the report
     * @param Gradeable[] $user_graded_gradeables The user's graded gradeables, indexed by gradeable id
     * @param array $team_graded_gradeables The team graded gradeables, see cacheTeamGradedGradeables
     */
    private function saveUserToFile(string $base_path, User $user, array $gradeables, array $user_graded_gradeables, array $team_graded_gradeables) {
        // Merge the user gradeables with the team gradeables
        $ggs = $this->mergeGradedGradeables($gradeables, $user, $user_graded_gradeables, $team_graded_gradeables);
        $late_days = new LateDays($this->core, $user, $ggs);

        $user_data = [];
        $user_data['user_id'] = $user->getId();
        $user_data['legal_first_name'] = $user->getLegalFirstName();
        $user_data['preferred_first_name'] = $user->getPreferredFirstName();
        $user_data['legal_last_name'] = $user->getLegalLastName();
        $user_data['preferred_last_name'] = $user->getPreferredLastName();
        $user_data['registration_section'] = $user->getRegistrationSection();
        $user_data['default_allowed_late_days'] = $this->core->getConfig()->getDefaultStudentLateDays();
        $user_data['last_update'] = date("l, F j, Y");

        foreach ($ggs as $gg) {
            $bucket = ucwords($gg->getGradeable()->getSyllabusBucket());
            $user_data[$bucket][] = $this->generateGradeSummary($gg, $late_days);
        }
        file_put_contents(FileUtils::joinPaths($base_path, $user->getId() . '_summary.json'), FileUtils::encodeJson($user_data));
    }

    /**
     * Gets the status message for a from a LateDayInfo status message
     * @param $status
     * @return string
     */
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

    /**
     * Adds late day information to a report entry
     * TODO: functions that take ref parameters like this are sinful
     * @param LateDayInfo|null $ldi
     * @param $entry
     */
    private function addLateDays($ldi, &$entry) {
        if ($ldi === null) {
            return;
        }
        if (!$ldi->hasLateDaysInfo()) {
            $entry['days_late'] = 0;
            $entry['status'] = 'unsubmitted';
            return;
        }

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

