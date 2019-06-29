<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\Output;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\LateDayInfo;
use app\models\gradeable\LateDays;
use app\models\gradeable\Mark;
use app\models\gradeable\Submitter;
use app\models\User;
use Symfony\Component\Routing\Annotation\Route;

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

    /**
     * @Route("/{_semester}/{_course}/reports")
     */
    public function showReportPage() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("This account cannot access admin pages");
        }

        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }

    /**
     * Generates grade summary files for every user
     *
     * @Route("/{_semester}/{_course}/reports/summaries")
     * @Route("/api/{_semester}/{_course}/reports/summaries", methods={"POST"})
     */
    public function generateGradeSummaries() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("This account cannot access admin pages");
        }

        $base_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'all_grades');
        $g_sort_keys = [
            'type',
            'CASE WHEN submission_due_date IS NOT NULL THEN submission_due_date ELSE g.g_grade_released_date END',
            'g_id',
        ];
        $gg_sort_keys = [
            'user_id',
        ];

        // Generate the reports
        $this->generateReportInternal($g_sort_keys, $gg_sort_keys, function ($a, $b, $c) use ($base_path) {
            $this->saveUserToFile($base_path, $a, $b, $c);
            return null;
        });
        $this->core->addSuccessMessage("Successfully Generated Grade Summaries");
        $this->core->redirect($this->core->buildNewCourseUrl(['reports']));
        return $this->core->getOutput()->renderJsonSuccess();
    }

    /**
     * Generates and offers download of CSV grade report
     *
     * @Route("/{_semester}/{_course}/reports/csv")
     */
    public function generateCSVReport() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("This account cannot access admin pages");
        }

        $g_sort_keys = [
            'syllabus_bucket',
            'g_id',
        ];
        $gg_sort_keys = [
            'registration_section',
            'user_id',
        ];

        // Generate the reports
        $rows = $this->generateReportInternal($g_sort_keys, $gg_sort_keys, function ($a, $b, $c) {
            return $this->generateCSVRow($a, $b, $c);
        });

        // Concatenate the CSV
        $csv = '';
        if (count($rows) > 0) {
            // Header row
            $csv = implode(',', array_keys(reset($rows))) . PHP_EOL;
            // Content rows
            foreach ($rows as $row) {
                $csv .= implode(',', $row) . PHP_EOL;
            }
        }
        //Send csv data to file download.  Filename: "{course}_csvreport_{date/time stamp}.csv"
        $this->core->getOutput()->renderFile($csv, $this->core->getConfig()->getCourse() . "_csvreport_" . date("ymdHis") . ".csv");
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
            foreach ($gg->getSubmitter()->getTeam()->getMemberUserIds() as $user_id) {
                $team_graded_gradeables[$gg->getGradeableId()][$user_id] = $gg;
            }
        }

        return $team_graded_gradeables;
    }

    /**
     * Fetches all gradeables from the database and splits them based on teamness
     * @param array|null $sort_keys the keys used to sort the gradeables
     * @return array [gradeables, user gradeables, team gradeables]
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

    private function genDummyGradedGradeable(Gradeable $gradeable, Submitter $submitter) {
        $gg = new GradedGradeable($this->core, $gradeable, $submitter, []);
        $gg->setAutoGradedGradeable(new AutoGradedGradeable($this->core, $gg, []));
        return $gg;
    }

    /**
     * Merges user and team graded gradeables for a user
     * @param Gradeable[] $gradeables
     * @param User $user
     * @param GradedGradeable[] $user_graded_gradeables User graded gradeables indexed by gradeable id
     * @param array $team_graded_gradeables See cacheTeamGradedGradeables
     * @return GradedGradeable[] array of graded gradeables in the order of $gradeables, indexed by gradeable id.
     *  NOTE: If there are any team gradeables that user has no team for, they get a new, blank gradeable with the user as the submitter
     */
    private function mergeGradedGradeables(array $gradeables, User $user, array $user_graded_gradeables, array $team_graded_gradeables) {
        $ggs = [];
        foreach ($gradeables as $g) {
            /** @var Gradeable $g */
            if ($g->isTeamAssignment()) {
                // if the user doesn't have a team, MAKE THE USER A SUBMITTER
                $ggs[] = $team_graded_gradeables[$g->getId()][$user->getId()] ?? $this->genDummyGradedGradeable($g, new Submitter($this->core, $user));
            } else {
                $ggs[] = $user_graded_gradeables[$g->getId()];
            }
        }
        return $ggs;
    }

    /**
     * A general purpose function for generating reports for all users, grouped by user
     * @param string[]|null $gradeable_sort_keys
     * @param string[]|null $graded_gradeable_sort_keys
     * @param \Closure $per_user_callback callback that accepts (User, GradedGradeable[], LateDays)
     * @return array list of results of $per_user_callback, indexed by user id
     */
    private function generateReportInternal($gradeable_sort_keys, $graded_gradeable_sort_keys, $per_user_callback) {
        /** @var User $current_user */
        $current_user = null;
        $results = [];

        // get all gradeables and cache team graded gradeables
        list($all_gradeables, $user_gradeables, $team_gradeables) = $this->getSplitGradeables($gradeable_sort_keys);
        $team_graded_gradeables = $this->cacheTeamGradedGradeables($team_gradeables);

        //Gradeable iterator will append one gradeable score per loop pass.
        $user_graded_gradeables = [];

        // Method to call the callback with the required parameters
        $call_callback = function ($all_gradeables, User $current_user, $user_graded_gradeables, $team_graded_gradeables, $per_user_callback) {
            $ggs = $this->mergeGradedGradeables($all_gradeables, $current_user, $user_graded_gradeables, $team_graded_gradeables);
            $late_days = new LateDays($this->core, $current_user, $ggs);
            return $per_user_callback($current_user, $ggs, $late_days);
        };
        foreach ($this->core->getQueries()->getGradedGradeables($user_gradeables, null, null, $graded_gradeable_sort_keys) as $gg) {
            /** @var GradedGradeable $gg */
            if ($current_user === null || $current_user->getId() !== $gg->getSubmitter()->getId()) {
                if ($current_user !== null) {
                    // Previous pass completed an entire row
                    $results[$current_user->getId()] = $call_callback($all_gradeables, $current_user, $user_graded_gradeables, $team_graded_gradeables, $per_user_callback);
                }

                //Prepare for the new user
                $current_user = $gg->getSubmitter()->getUser();
                $user_graded_gradeables = [];
            }
            $user_graded_gradeables[$gg->getGradeableId()] = $gg;
        }

        // If there are only team gradeables, check to see that all users were properly covered
        //  If there are users on no teams, then they won't get a result from the above loop...
        if (count($all_gradeables) > 0 && count($user_gradeables) === 0) {
            //... So we must find them and give them dummy results
            foreach ($this->core->getQueries()->getAllUsers() as $u) {
                if (!isset($results[$u->getId()])) {
                    // This user had no results, so generate results
                    $ggs = $this->mergeGradedGradeables($all_gradeables, $u, [], $team_graded_gradeables);
                    $late_days = new LateDays($this->core, $u, $ggs);
                    $results[$current_user->getId()] = $per_user_callback($u, $ggs, $late_days);
                }
            }
        }

        // Remember to do the callback on the last user
        if ($current_user !== null) {
            $results[$current_user->getId()] = $call_callback($all_gradeables, $current_user, $user_graded_gradeables, $team_graded_gradeables, $per_user_callback);
        }
        return $results;
    }

    /**
     * Generates a CSV row for a user
     * @param User $user The user the grades are for
     * @param GradedGradeable[] The list of graded gradeables, indexed by gradeable id
     * @param LateDays $late_days The late day info for these graded gradeables
     * @return array
     */
    private function generateCSVRow(User $user, array $ggs, LateDays $late_days) {
        $row = [];

        $row['User ID'] = $user->getId();
        $row['First Name'] = $user->getDisplayedFirstName();
        $row['Last Name'] = $user->getDisplayedLastName();
        $row['Registration Section'] = $user->getRegistrationSection();

        foreach ($ggs as $gg) {
            /** @var GradedGradeable $gg */
            //Append one gradeable score to row.  Scores are indexed by gradeable's ID.
            $row[$gg->getGradeableId()] = $gg->getTotalScore();

            // Check if the score should be a zero
            if ($gg->getGradeable()->getType() === GradeableType::ELECTRONIC_FILE) {
                if ($gg->getGradeable()->isTaGrading() && ($gg->getOrCreateTaGradedGradeable()->hasVersionConflict() || !$gg->isTaGradingComplete())) {
                    // Version conflict or incomplete grading, so zero score
                    $row[$gg->getGradeableId()] = 0;
                } else if ($late_days->getLateDayInfoByGradeable($gg->getGradeable())->getStatus() === LateDayInfo::STATUS_BAD) {
                    // BAD submission, so zero score
                    $row[$gg->getGradeableId()] = 0;
                }
            }
        }
        return $row;
    }

    /**
     * Saves all user data to a file
     * @param string $base_path the base path to store the reports
     * @param User $user The user the report is for
     * @param GradedGradeable[] The list of graded gradeables, indexed by gradeable id
     * @param LateDays $late_days The late day info for these graded gradeables
     */
    private function saveUserToFile(string $base_path, User $user, array $ggs, LateDays $late_days) {

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

        // Add team members to output
        if ($g->isTeamAssignment()) {
            $entry['team_members'] = $gg->getSubmitter()->isTeam() ? $gg->getSubmitter()->getTeam()->getMemberUserIds()
                : $gg->getSubmitter()->getId(); // If the user isn't on a team, the only member is themselves
        }

        $entry['score'] = $gg->getTotalScore();

        // Add information special to electronic file submissions
        if ($g->getType() === GradeableType::ELECTRONIC_FILE) {
            // Add information based on late day status
            $ldi = $ld->getLateDayInfoByGradeable($g);
            if ($ldi !== null) {
                // Zero score if BAD status
                if ($ldi->getStatus() === LateDayInfo::STATUS_BAD) {
                    $entry['score'] = 0;
                }

                // The report needs this to be different from the 'pretty' version returned from $ldi->getStatusMessage()
                $entry['status'] = $this->getLateStatusMessage($ldi);

                // Only include late day info if the submission was late
                $late_days_charged = $ldi->getLateDaysCharged();
                if ($late_days_charged > 0) {
                    $entry['days_after_deadline'] = $ldi->getDaysLate();
                    $entry['extensions'] = $ldi->getLateDayException();
                    $entry['days_charged'] = $late_days_charged;
                }
            }

            // Add score breakdown
            $ta_gg = $gg->getOrCreateTaGradedGradeable();
            $entry['overall_comment'] = $ta_gg->getOverallComment();

            // Only split up scores if electronic gradeables
            $entry['autograding_score'] = $gg->getAutoGradingScore();
            $entry['tagrading_score'] = $gg->getTaGradingScore();

            // If the grading isn't complete or there are conflicts in which version is graded,
            //  let the user know that
            if ($g->isTaGrading() && ($ta_gg->hasVersionConflict() || !$ta_gg->isComplete())) {
                $entry['score'] = 0;
                $entry['autograding_score'] = 0;
                $entry['tagrading_score'] = 0;
                if (!$gg->getSubmitter()->isTeam() && $gg->getGradeable()->isTeamAssignment()) {
                    // This is sort of a hack.  Submitters for team assignments should always be teams,
                    //  but to keep the rest of the report generation sane, they can be users if the
                    //  user is not on a team
                    $entry['note'] = 'User is not on a team';
                } else if (!$ta_gg->isComplete()) {
                    $entry['note'] = 'This has not been graded yet.';
                } else {
                    $entry['note'] = 'Score is set to 0 because there are version conflicts.';
                }
            }
        }

        // Component/marks
        $entry['components'] = [];
        foreach ($g->getComponents() as $component) {
            $gcc = $gg->getOrCreateTaGradedGradeable()->getGradedComponentContainer($component);

            // We need to convert to the old model single-grader format for rainbow grades
            $gc = null;
            foreach ($gcc->getGradedComponents() as $gc_) {
                $gc = $gc_;
                // Get the only graded component and short circuit
                break;
            }
            //
            // For each $gc in $gcc
            //

            $inner = [
                'title' => $component->getTitle()
            ];
            if ($component->isText()) {
                $inner['comment'] = $gc !== null ? $gc->getComment() : '';
            } else {
                $inner['score'] = $gc !== null ? $gc->getTotalScore() : 0.0;
                $inner['default_score'] = $component->getDefault();
                $inner['upper_clamp'] = $component->getUpperClamp();
                $inner['lower_clamp'] = $component->getLowerClamp();
            }

            if ($g->getType() === GradeableType::ELECTRONIC_FILE) {
                $marks = [];
                if ($gc !== null) {
                    $marks = array_map(function (Mark $m) {
                        return ['points' => $m->getPoints(), 'note' => $m->getTitle()];
                    }, $gc->getMarks());

                    if ($gc->hasCustomMark()) {
                        $marks[] = ['points' => $gc->getScore(), 'note' => $gc->getComment()];
                    }
                }

                $inner['marks'] = $marks;
            }
            $entry['components'][] = $inner;

            // end for
        }
        return $entry;
    }

    /**
     * Gets the status message for a from a LateDayInfo status message
     * @param LateDayInfo $ldi
     * @return string
     */
    private function getLateStatusMessage(LateDayInfo $ldi) {
        switch ($ldi->getStatus()) {
            case LateDayInfo::STATUS_GOOD:
                return 'Good';
            case LateDayInfo::STATUS_LATE:
                return 'Late';
            case LateDayInfo::STATUS_BAD:
                return 'Bad';
            case LateDayInfo::STATUS_NO_ACTIVE_VERSION:
                if ($ldi->getGradedGradeable()->getAutoGradedGradeable()->hasSubmission()) {
                    return 'Cancelled';
                } else {
                    return 'Unsubmitted';
                }
            default:
                return 'ERROR';
        }
    }
}

