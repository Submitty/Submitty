<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\entities\poll\Poll;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\routers\AccessControl;
use app\libraries\response\DownloadResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\PollUtils;
use app\libraries\Utils;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\LateDayInfo;
use app\models\gradeable\LateDays;
use app\models\gradeable\Mark;
use app\models\gradeable\Submitter;
use app\models\User;
use Symfony\Component\Routing\Annotation\Route;
use app\models\RainbowCustomization;
use app\exceptions\ValidationException;

/**
 * Class ReportController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */
class ReportController extends AbstractController {
    const MAX_AUTO_RG_WAIT_TIME = 45;       // Time in seconds a call to autoRainbowGradesStatus should
                                            // wait for the job to complete before timing out and returning failure

    private $all_overrides = [];

    #[Route("/courses/{_semester}/{_course}/reports")]
    public function showReportPage() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("This account cannot access admin pages");
        }

        $this->core->getOutput()->enableMobileViewport();
        $json = null;
        $customization_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "rainbow_grades", "manual_customization.json");
        if (file_exists($customization_path)) {
            $json = file_get_contents($customization_path);
        }
        $this->core->getOutput()->renderOutput(['admin', 'Report'], 'showReportUpdates', $json);
    }

    /**
     * Generates grade summary files for every user
     */
    #[Route("/courses/{_semester}/{_course}/reports/summaries")]
    #[Route("/api/courses/{_semester}/{_course}/reports/summaries", methods: ["POST"])]
    public function generateGradeSummaries() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("This account cannot access admin pages");
        }

        $base_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'all_grades');

        // Check that the directory is writable, fail if not
        if (!is_writable($base_path)) {
            $this->core->addErrorMessage('Unable to write to the grade summaries directory');
            $this->core->redirect($this->core->buildCourseUrl(['reports']));
        }

        $poll_base_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'polls');

        // Check that the directory is writable, fail if not
        if ($this->core->getConfig()->isPollsEnabled() && !is_writable($poll_base_path)) {
            $this->core->addErrorMessage('Unable to write to the poll summary directory');
            $this->core->redirect($this->core->buildCourseUrl(['reports']));
        }

        $url_base_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'base_url.json');
        $base_url = $this->core->getConfig()->getBaseUrl();
        $term = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        // Encode $base_url as a json string
        $data = [
            'base_url' => $base_url,
            'term' => $term,
            'course' => $course
        ];

        // Encode the data as a JSON string
        $json_data = json_encode($data, JSON_PRETTY_PRINT);

        // Write the JSON string to the file
        if (!file_put_contents($url_base_path, $json_data)) {
            $this->core->addErrorMessage('Unable to write to base_url.json');
            $this->core->redirect($this->core->buildCourseUrl(['reports', 'rainbow_grades_customization']));
        }

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

        if ($this->core->getConfig()->isPollsEnabled()) {
            $this->generatePollSummaryInternal($poll_base_path);
        }

        $this->core->addSuccessMessage("Successfully Generated Grade Summaries");
        $this->core->redirect($this->core->buildCourseUrl(['reports', 'rainbow_grades_customization']));
        return $this->core->getOutput()->renderJsonSuccess();
    }


    public function getGradeSummariesLastRun() {

        // Build path to the grade summaries folder
        $summaries_dir = $this->core->getConfig()->getCoursePath() . '/reports/all_grades';

        // Get contents of directory
        $files = scandir($summaries_dir);

        // Get file count
        // Subtract 2 to account for '.' and '..'
        $file_count = count($files) - 2;

        // If folder is empty return never
        if ($file_count == 0) {
            return 'Never';
        }
        else {
            // Else folder has contents return the time stamp off the first file
            // Get file modification time of first student json
            $time_stamp = filemtime($summaries_dir . '/' . $files[2]);

            // Format it
            $time_stamp = new \DateTime("@$time_stamp");
            $time_stamp->setTimezone($this->core->getConfig()->getTimezone());

            return DateUtils::convertTimeStamp($this->core->getUser(), $time_stamp->format('c'), $this->core->getConfig()->getDateTimeFormat()->getFormat('gradeable'));
        }
    }

    /**
     * Generates and offers download of CSV grade report
     */
    #[Route("/courses/{_semester}/{_course}/reports/csv")]
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
        $rows = $this->generateReportInternal($g_sort_keys, $gg_sort_keys, function (User $a, array $b, LateDays $c) {
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
        /** @var GradedGradeable $gg */
        foreach ($this->core->getQueries()->getGradedGradeables($team_gradeables) as $gg) {
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
            }
            else {
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
                $graded_gradeable = $team_graded_gradeables[$g->getId()][$user->getId()] ?? $this->genDummyGradedGradeable($g, new Submitter($this->core, $user));
            }
            else {
                $graded_gradeable = $user_graded_gradeables[$g->getId()];
            }

            if ($graded_gradeable !== null) {
                $graded_gradeable->setOverriddenGrades($this->all_overrides[$user->getId()][$graded_gradeable->getGradeableId()] ?? null);
                $ggs[] = $graded_gradeable;
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
        [$all_gradeables, $user_gradeables, $team_gradeables] = $this->getSplitGradeables($gradeable_sort_keys);
        $team_graded_gradeables = $this->cacheTeamGradedGradeables($team_gradeables);

        //Gradeable iterator will append one gradeable score per loop pass.
        $user_graded_gradeables = [];

        $all_late_days = [];
        foreach ($this->core->getQueries()->getLateDayUpdates(null) as $row) {
            if (!isset($all_late_days[$row['user_id']])) {
                $all_late_days[$row['user_id']] = [];
            }
            $all_late_days[$row['user_id']][] = $row;
        }

        $this->all_overrides = $this->core->getQueries()->getAllOverriddenGrades();

        // Method to call the callback with the required parameters
        $call_callback = function ($all_gradeables, User $current_user, $user_graded_gradeables, $team_graded_gradeables, $per_user_callback) use ($all_late_days) {
            $ggs = $this->mergeGradedGradeables($all_gradeables, $current_user, $user_graded_gradeables, $team_graded_gradeables);
            $late_days = new LateDays($this->core, $current_user, $ggs, $all_late_days[$current_user->getId()] ?? []);
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
                    $late_days = new LateDays($this->core, $u, $ggs, $all_late_days[$u->getId()] ?? []);
                    $results[$u->getId()] = $per_user_callback($u, $ggs, $late_days);
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
     * @param GradedGradeable[] $ggs The list of graded gradeables, indexed by gradeable id
     * @param LateDays $late_days The late day info for these graded gradeables
     * @return array
     */
    private function generateCSVRow(User $user, array $ggs, LateDays $late_days) {
        $row = [];

        $row['User ID'] = $user->getId();
        $row['Given Name'] = $user->getDisplayedGivenName();
        $row['Family Name'] = $user->getDisplayedFamilyName();
        $row['Registration Section'] = $user->getRegistrationSection();

        foreach ($ggs as $gg) {
            /** @var GradedGradeable $gg */
            //Append one gradeable score to row.  Scores are indexed by gradeable's ID.
            $row[$gg->getGradeableId()] = $gg->getTotalScore();
            $ldi = $late_days->getLateDayInfoByGradeable($gg->getGradeable());
            if (!$gg->hasOverriddenGrades()) {
                // Check if the score should be a zero
                if ($gg->getGradeable()->getType() === GradeableType::ELECTRONIC_FILE) {
                    if ($gg->getGradeable()->isTaGrading() && ($gg->getOrCreateTaGradedGradeable()->hasVersionConflict() || !$gg->isTaGradingComplete())) {
                        // Version conflict or incomplete grading, so zero score
                        $row[$gg->getGradeableId()] = 0;
                    }
                    elseif ($ldi !== null && $ldi->getStatus() === LateDayInfo::STATUS_BAD) {
                        // BAD submission, so zero score
                        $row[$gg->getGradeableId()] = 0;
                    }
                }
            }
        }
        return $row;
    }

    /**
     * Saves all user data to a file
     * @param string $base_path the base path to store the reports
     * @param User $user The user the report is for
     * @param GradedGradeable[] $ggs The list of graded gradeables, indexed by gradeable id
     * @param LateDays $late_days The late day info for these graded gradeables
     */
    private function saveUserToFile(string $base_path, User $user, array $ggs, LateDays $late_days) {

        $user_data = [];
        $user_data['user_id'] = $user->getId();
        $user_data['user_numeric_id'] = $user->getNumericId();
        $user_data['legal_given_name'] = $user->getLegalGivenName();
        $user_data['preferred_given_name'] = $user->getPreferredGivenName();
        $user_data['legal_family_name'] = $user->getLegalFamilyName();
        $user_data['preferred_family_name'] = $user->getPreferredFamilyName();
        $user_data['registration_section'] = $user->getRegistrationSection();
        $user_data['course_section_id'] = $user->getCourseSectionId();
        $user_data['rotating_section'] = $user->getRotatingSection();
        $user_data['registration_type'] = $user->getRegistrationType();
        $user_data['default_allowed_late_days'] = $this->core->getConfig()->getDefaultStudentLateDays();
        $user_data['last_update'] = date("l, F j, Y h:i A T");

        foreach ($ggs as $gg) {
            $bucket = ucwords($gg->getGradeable()->getSyllabusBucket());
            $user_data[$bucket][] = $this->generateGradeSummary($gg, $user, $late_days);
        }

        file_put_contents(FileUtils::joinPaths($base_path, $user->getId() . '_summary.json'), FileUtils::encodeJson($user_data));
    }

    /**
     * Generates a summary of all polls over a semester if polling is enabled
     * @param string $base_path the base path to store the report
     */
    private function generatePollSummaryInternal(string $base_path): void {
        /** @var \app\repositories\poll\PollRepository */
        $repo = $this->core->getCourseEntityManager()->getRepository(Poll::class);
        $polls = $repo->findAllWithAllResponses();
        $polls_data = [];
        foreach ($polls as $poll) {
            $responses = [];
            /** @var \app\entities\poll\Response */
            foreach ($poll->getUserResponses() as $response) {
                if (!array_key_exists($response->getStudentId(), $responses)) {
                    $responses[$response->getStudentId()] = [];
                }
                $responses[$response->getStudentId()][] = $response->getOption()->getOrderId();
            }

            $polls_data[] = [
                "id" => $poll->getId(),
                "responses" => $responses
            ];
        }
        FileUtils::writeJsonFile(FileUtils::joinPaths($base_path, "poll_responses.json"), $polls_data);
        FileUtils::writeJsonFile(FileUtils::joinPaths($base_path, "poll_questions.json"), PollUtils::getPollExportData($polls));
    }

    /**
     * Generates a grade summary entry for a graded gradeable
     * @param GradedGradeable $gg
     * @param LateDays $ld
     * @return array
     */
    public function generateGradeSummary(GradedGradeable $gg, User $user, LateDays $ld) {
        $g = $gg->getGradeable();

        $entry = [
            'id' => $g->getId(),
            'name' => $g->getTitle(),
            'gradeable_type' => GradeableType::typeToString($g->getType()),
            'grade_released_date' => $g->hasReleaseDate() ? $g->getGradeReleasedDate()->format('Y-m-d H:i:s O') : $g->getSubmissionOpenDate()->format('Y-m-d H:i:s O'),
        ];

        if ($g->isGradeInquiryAllowed()) {
            // Export the grade inquiry status
            if ($gg->hasGradeInquiry()) {
                if ($gg->hasActiveGradeInquiry()) {
                    $entry['inquiry'] = 'Open';
                }
                else {
                    $entry['inquiry'] = 'Resolved';
                }
            }
            else {
                $entry['inquiry'] = 'None';
            }
        }

        // Add team members to output
        if ($g->isTeamAssignment()) {
            $entry['team_members'] = $gg->getSubmitter()->isTeam() ? $gg->getSubmitter()->getTeam()->getMemberUserIds()
                : $gg->getSubmitter()->getId(); // If the user isn't on a team, the only member is themselves
        }

        $entry['score'] = $gg->getTotalScore();

        $ldi = $ld->getLateDayInfoByGradeable($g);

        if ($gg->hasOverriddenGrades()) {
            $entry['status'] = 'Overridden';
            $entry['comment'] = $gg->getOverriddenComment();
        }
        else {
            // Add information special to electronic file submissions
            if ($g->getType() === GradeableType::ELECTRONIC_FILE) {
                if ($ldi !== null) {
                    // Zero score if BAD status
                    if ($ldi->getStatus() === LateDayInfo::STATUS_BAD) {
                        $entry['score'] = 0;
                    }

                    // The report needs this to be different from the 'pretty' version returned from $ldi->getStatusMessage()
                    $entry['status'] = $this->getLateStatusMessage($ldi);

                    // Only include late day info if the submission was late
                    $late_days_charged = $ldi->getLateDaysCharged();
                    $late_day_exceptions = $ldi->getLateDayException();
                    if ($late_day_exceptions > 0) {
                        $entry['late_day_exceptions'] = $late_day_exceptions;
                        $entry['reason_for_exception'] = $ldi->getReasonForException();
                    }
                    if ($late_days_charged > 0) {
                        $entry['days_after_deadline'] = $ldi->getDaysLate();
                        $entry['days_charged'] = $late_days_charged;
                    }
                }

                // Add score breakdown
                $ta_gg = $gg->getOrCreateTaGradedGradeable();
                // an array where keys are userids and values are overall comments
                $entry['overall_comments'] = $ta_gg->getOverallComments();

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
                    }
                    elseif (!$ta_gg->isComplete()) {
                        $entry['note'] = 'This has not been graded yet.';
                    }
                    else {
                        $entry['note'] = 'Score is set to 0 because there are version conflicts.';
                        $entry['version_conflict'] = 'true';
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
                }
                else {
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
                }
                else {
                    return 'Unsubmitted';
                }
            default:
                return 'ERROR';
        }
    }

    /**
     * Writes to the rainbow grades customization file
     *
     * @return array<string, mixed>
     */
    #[Route("/courses/{_semester}/{_course}/reports/rainbow_grades_customization_save", methods: ["POST"])]
    #[Route("/api/courses/{_semester}/{_course}/reports/rainbow_grades_customization_save", methods: ["POST"])]
    public function writeToCustomization(): array {
        // Build a new model, pull in defaults for the course
        $customization = new RainbowCustomization($this->core);
        $customization->buildCustomization();

        if (isset($_POST["json_string"])) {
            try {
                $customization->processForm($_POST['json_string']);
                return $this->core->getOutput()->renderJsonSuccess();
            }
            catch (\Exception $e) {
                $msg = 'Error processing form';
            }
        }
        else {
            $msg = 'No JSON string provided';
        }

        return $this->core->getOutput()->renderJsonError($msg);
    }

    /**
     * Generates the rainbow grades customization page or writes to the customization file
     * based on the existence of the "json_string" request body.
     *
     * @return array<string, mixed>|null
     */
    #[Route("/courses/{_semester}/{_course}/reports/rainbow_grades_customization", methods: ["GET"])]
    #[Route("/api/courses/{_semester}/{_course}/reports/rainbow_grades_customization", methods: ["POST"])]
    public function generateCustomization(): array| null {
        //Build a new model, pull in defaults for the course
        $customization = new RainbowCustomization($this->core);
        $customization->buildCustomization();

        if (isset($_POST["json_string"])) {
            // Handle user input (the form) being submitted
            try {
                $customization->processForm($_POST['json_string']);

                // Finally, send the requester back the information
                return $this->core->getOutput()->renderJsonSuccess("Successfully wrote gui_customization.json file");
            }
            catch (ValidationException $e) {
                //Use this to handle any invalid/inconsistent input exceptions thrown during processForm()
                return $this->core->getOutput()->renderJsonFail('See "data" for details', $e->getDetails());
            }
            catch (\Exception $e) {
                //Catches any other exceptions, should be "unexpected" issues
                return $this->core->getOutput()->renderJsonError($e->getMessage());
            }
        }
        else {
            $this->core->getOutput()->addInternalJs('rainbow-customization.js');
            $this->core->getOutput()->addInternalCss('rainbow-customization.css');
            $this->core->getOutput()->addInternalCss('grade-report.css');
            $this->core->getOutput()->addBreadcrumb('Rainbow Grades Customization');
            $this->core->getOutput()->addSelect2WidgetCSSAndJs();
            $students = $this->core->getQueries()->getAllUsers();
            $student_full = Utils::getAutoFillData($students);
            $this->core->getOutput()->enableMobileViewport();
            $gradeables = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
            // Print the form
            $this->core->getOutput()->renderTwigOutput('admin/RainbowCustomization.twig', [
                'summaries_url' => $this->core->buildCourseUrl(['reports', 'summaries']),
                'grade_summaries_last_run' => $this->getGradeSummariesLastRun(),
                'manual_customization_download_url' => $this->core->buildCourseUrl(['reports', 'rainbow_grades_customization', 'manual_download']),
                'gui_customization_download_url' => $this->core->buildCourseUrl(['reports', 'rainbow_grades_customization', 'gui_download']),
                'customization_upload_url' => $this->core->buildCourseUrl(['reports', 'rainbow_grades_customization', 'upload']),
                "manual_customization_exists" => $customization->doesManualCustomizationExist(),
                "uses_manual_customization" => $customization->usesManualCustomization(),
                "customization_data" => $customization->getCustomizationData(),
                "available_buckets" => $customization->getAvailableBuckets(),
                'bucket_counts' => $customization->getBucketCounts(),
                'bucket_remove_lowest' => $customization->getBucketRemoveLowest(),
                "used_buckets" => $customization->getUsedBuckets(),
                'display_benchmarks' => $customization->getDisplayBenchmarks(),
                'benchmark_percents' => (array) $customization->getBenchmarkPercent(),
                'benchmarks_with_input_fields' => ['lowest_a-', 'lowest_b-', 'lowest_c-', 'lowest_d'],
                'final_cutoff_input_fields' => ["A", "A-", "B+", "B", "B-", "C+", "C", "C-", "D+", "D"],
                'allowed_grades' => ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F'],
                'final_cutoff' => (array) $customization->getFinalCutoff(),
                'display' => $customization->getDisplay(),
                'display_description' => $customization->getDisplayDescription(),
                'sections_and_labels' => (array) $customization->getSectionsAndLabels(),
                'omit_section_from_statistics' => $customization->getOmittedSections(),
                'bucket_percentages' => $customization->getBucketPercentages(),
                'messages' => $customization->getMessages(),
                'plagiarism' => $customization->getPlagiarism(),
                'manual_grade' => $customization->getManualGrades(),
                'warning' => $customization->getPerformanceWarnings(),
                "gradeables" => $gradeables,
                "student_full" => $student_full,
                'per_gradeable_curves' => $customization->getPerGradeableCurves(),
                'limited_functionality_mode' => !$this->core->getQueries()->checkIsInstructorInCourse(
                    $this->core->getConfig()->getVerifiedSubmittyAdminUser(),
                    $this->core->getConfig()->getCourse(),
                    $this->core->getConfig()->getTerm()
                ),
                'csrfToken' => $this->core->getCsrfToken(),
            ]);
        }

        return null;
    }


    #[Route("/courses/{_semester}/{_course}/reports/build_form", methods: ['POST'])]
    #[Route("/api/courses/{_semester}/{_course}/reports/build_form", methods: ['POST'])]
    public function executeBuildForm(): JsonResponse {
        // Configure json to go into jobs queue
        $job_json = [
            'job' => 'RunAutoRainbowGrades',
            'source' => isset($_POST['source']) ? $_POST['source'] : 'submitty_gui',
            'semester' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse(),
        ];

        // Encode
        $job_json = json_encode($job_json, JSON_PRETTY_PRINT);

        // Create path to new jobs queue json

        $path = '/var/local/submitty/daemon_job_queue/auto_rainbow_' .
            $this->core->getConfig()->getTerm() .
            '_' .
            $this->core->getConfig()->getCourse() .
            '.json';

        // Place in queue
        file_put_contents($path, $job_json);
        return JsonResponse::getSuccessResponse();
    }


    #[Route("/courses/{_semester}/{_course}/reports/rainbow_grades_customization/upload", methods: ["POST"])]
    public function uploadRainbowConfig() {
        if (empty($_FILES) || !isset($_FILES['config_upload'])) {
            $msg = 'Upload failed: No file to upload';
            $this->core->addErrorMessage($msg);
            return JsonResponse::getErrorResponse($msg);
        }

        $upload = $_FILES['config_upload'];
        if (!isset($upload['tmp_name']) || trim($upload['tmp_name']) === '') {
            $msg = 'Upload failed: Empty tmp name for file';
            $this->core->addErrorMessage($msg);
            return JsonResponse::getErrorResponse($msg);
        }

        $rainbow_grades_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "rainbow_grades");
        $destination_path = FileUtils::joinPaths($rainbow_grades_dir, 'manual_customization.json');

        // this is changed from move_uploaded_file to copy because of permission issue (bits not carried over)
        // copy is expensive, but we are OK because it is small file.
        // setgid (sticky-bit) gets ignored and doesn't inherit the parent (rainbowgrades dir) permissions
        // known issue: look https://www.php.net/manual/en/function.move-uploaded-file.php for more details
        if (!copy($upload['tmp_name'], $destination_path) || !file_exists($destination_path)) {
            $msg = 'Upload failed: Could not copy file';
            $this->core->addErrorMessage($msg);
            return JsonResponse::getErrorResponse($msg);
        }

        $this->core->addSuccessMessage('Rainbow Grades Customization uploaded');

        return JsonResponse::getSuccessResponse([
            'customization_path' => $rainbow_grades_dir,
            'manual_customization_exists' => true
        ]);
    }


    #[Route("/courses/{_semester}/{_course}/reports/rainbow_grades_customization/gui_download", methods: ["GET"])]
    public function downloadGUIRainbowConfig(): MultiResponse|DownloadResponse {
        $rainbow_grades_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "rainbow_grades");
        $file_path = FileUtils::joinPaths($rainbow_grades_dir, 'gui_customization.json');

        if (file_exists($file_path)) {
            return DownloadResponse::getDownloadResponse(
                file_get_contents($file_path),
                'gui_customization.json',
                "application/json"
            );
        }
        else {
            $msg = 'Download failed: File not found';
            $this->core->addErrorMessage($msg);
            $redirect_url = $this->core->buildCourseUrl(['reports', 'rainbow_grades_customization']);
            return new MultiResponse(
                JsonResponse::getErrorResponse($msg),
                null,
                new RedirectResponse($redirect_url)
            );
        }
    }


    #[Route("/courses/{_semester}/{_course}/reports/rainbow_grades_customization/manual_download", methods: ["GET"])]
    public function downloadRainbowConfig(): MultiResponse|DownloadResponse {
        $rainbow_grades_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "rainbow_grades");
        $file_path = FileUtils::joinPaths($rainbow_grades_dir, 'manual_customization.json');

        if (file_exists($file_path)) {
            return DownloadResponse::getDownloadResponse(
                file_get_contents($file_path),
                'manual_customization.json',
                "application/json"
            );
        }
        else {
            $msg = 'Download failed: File not found';
            $this->core->addErrorMessage($msg);
            $redirect_url = $this->core->buildCourseUrl(['reports', 'rainbow_grades_customization']);
            return new MultiResponse(
                JsonResponse::getErrorResponse($msg),
                null,
                new RedirectResponse($redirect_url)
            );
        }
    }


    #[Route('/courses/{_semester}/{_course}/reports/rainbow_grades_customization/manual_or_gui', methods: ['POST'])]
    #[Route('/api/courses/{_semester}/{_course}/reports/rainbow_grades_customization/manual_or_gui', methods: ['POST'])]
    public function setRainbowGradeCustomization(): JsonResponse {

        // Extract the value from $_POST
        $selectedValue = $_POST['selected_value'] ?? null;

        if ($selectedValue === null || trim($selectedValue) === '') {
            $msg = 'Invalid request: No selected value provided.';
            return JsonResponse::getErrorResponse($msg);
        }

        $rainbow_grades_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "rainbow_grades");
        $customization_dest = FileUtils::joinPaths($rainbow_grades_dir, 'customization.json');

        // Determine the source file based on the selected value
        switch ($selectedValue) {
            case 'manual':
                $customization_src = FileUtils::joinPaths($rainbow_grades_dir, 'manual_customization.json');
                break;
            case 'gui':
                $customization_src = FileUtils::joinPaths($rainbow_grades_dir, 'gui_customization.json');
                break;
            default:
                $msg = 'Invalid request: Unknown selected value.';
                return JsonResponse::getErrorResponse($msg);
        }

        // Copy the source file to the destination
        if (!copy($customization_src, $customization_dest)) {
            $msg = 'File copy failed: Could not copy file.';
            return JsonResponse::getErrorResponse($msg);
        }

        $msg = 'Rainbow Grades Customization set successfully';

        return JsonResponse::getSuccessResponse([
            'selected_value' => $selectedValue,
            'message' => $msg,
        ]);
    }



    #[Route('/courses/{_semester}/{_course}/reports/rainbow_grades_status', methods: ['POST'])]
    #[Route('/api/courses/{_semester}/{_course}/reports/rainbow_grades_status', methods: ['POST'])]
    public function autoRainbowGradesStatus() {
        // Create path to the file we expect to find in the jobs queue
        $jobs_file = '/var/local/submitty/daemon_job_queue/auto_rainbow_' .
            $this->core->getConfig()->getTerm() .
            '_' .
            $this->core->getConfig()->getCourse() .
            '.json';
        // Create path to 'processing' file in jobs queue
        $processing_jobs_file = '/var/local/submitty/daemon_job_queue/PROCESSING_auto_rainbow_' .
            $this->core->getConfig()->getTerm() .
            '_' .
            $this->core->getConfig()->getCourse() .
            '.json';

        // Get the max time to wait before timing out
        $max_wait_time = self::MAX_AUTO_RG_WAIT_TIME;

        // Check the jobs queue every second to see if the job has finished yet
        while (file_exists($jobs_file) && $max_wait_time) {
            sleep(1);
            $max_wait_time--;
            clearstatcache();
        }

        // Jobs queue daemon actually changes the name of the job by prepending PROCESSING onto the filename
        // We must also wait for that file to be removed
        // Check the jobs queue every second to see if the job has finished yet
        while (file_exists($processing_jobs_file) && $max_wait_time) {
            sleep(1);
            $max_wait_time--;
            clearstatcache();
        }

        // Check the course auto_debug_output.txt to ensure no exceptions were thrown
        $debug_output_path = '/var/local/submitty/courses/' .
            $this->core->getConfig()->getTerm() . '/' .
            $this->core->getConfig()->getCourse() .
            '/rainbow_grades/auto_debug_output.txt';

        // Look over the output file to see if any part of the process failed
        try {
            $failure_detected = FileUtils::areWordsInFile($debug_output_path, ['Exception', 'Aborted', 'failed']);
        }
        catch (\Exception $e) {
            $failure_detected = true;
        }

        $debug_contents = file_get_contents($debug_output_path);
        $debug_contents = trim($debug_contents);
        $was_successful = str_ends_with($debug_contents, 'Done');

        if ($max_wait_time && $failure_detected === false && $was_successful) {
            return JsonResponse::getSuccessResponse($debug_contents);
        }
        else {
            return JsonResponse::getFailResponse($debug_contents);
        }
    }

    /**
     * Generate full rainbow grades view for instructors
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/gradebook")]
    public function displayGradebook() {
        $grade_path = $this->core->getConfig()->getCoursePath() . "/rainbow_grades/output.html";
        $grade_summaries_last_run = $this->getGradeSummariesLastRun();
        $grade_file = null;
        if (file_exists($grade_path)) {
            $grade_file = file_get_contents($grade_path);
        }

        return MultiResponse::webOnlyResponse(
            new WebResponse(
                ['admin', 'Report'],
                'showFullGradebook',
                $grade_file,
                $grade_summaries_last_run
            )
        );
    }

    /**
     * Generate a custom filename for the downloaded CSV file
     */
    private function generateCustomFilename(): string {
        $course = $this->core->getConfig()->getCourse();
        $timestamp = DateUtils::getFileNameTimeStamp();
        return "{$course}_rainbow_grades_{$timestamp}.csv";
    }


    /**
     * Download CSV file for Rainbow Grades
     */
    #[Route("/courses/{_semester}/{_course}/reports/rainbow_grades_csv")]
    public function downloadRainbowGradesCSVFile(): ?DownloadResponse {
        // Path to the CSV file for Rainbow Grades
        $csvFilePath = FileUtils::joinPaths(
            '/var/local/submitty/courses',
            $this->core->getConfig()->getTerm(),
            $this->core->getConfig()->getCourse(),
            'rainbow_grades',
            'output.csv'
        );


        // Check if the file exists
        if (file_exists($csvFilePath)) {
            return DownloadResponse::getDownloadResponse(
                file_get_contents($csvFilePath),
                $this->generateCustomFilename(),
                "application/csv"
            );
        }
        else {
            // Handle the case where the file does not exist
            $this->core->getOutput()->showError($csvFilePath . " was not found or was not readable.\nMaybe you have not <a\thref='./rainbow_grades_customization'>generated the rainbow grades</a> yet?");
            return null;
        }
    }

    /**
     * Save the most up-to-date GUI customization file for courses not using
     * manual customizations.
     *
     * @return JsonResponse
     */
    #[Route("/api/courses/{_semester}/{_course}/reports/nightly_rainbow_grades_save", methods: ["POST"])]
    public function saveGUICustomizations(): JsonResponse {
        $customization = new RainbowCustomization($this->core);
        $customization->buildCustomization();
        if ($customization->usesManualCustomization()) {
            return JsonResponse::getErrorResponse("Manual customization is currently in use.");
        }
        $json_data = $this->buildGuiCustomizationJson($customization);
        $customization->processForm($json_data);
        return JsonResponse::getSuccessResponse(json_decode($json_data, true));
    }

    /**
     * Build the GUI customization JSON from the combined database and existing
     * customization file state.
     *
     * @param RainbowCustomization $customization
     * @return string JSON string
     */
    private function buildGuiCustomizationJson(RainbowCustomization $customization): string {
        $json = [
            'section' => (array) $customization->getSectionsAndLabels(),
            'omit_section_from_stats' => $customization->getOmittedSections(),
            'display_benchmark' => array_values(array_map(
                fn($b) => $b['id'],
                array_filter(
                    $customization->getDisplayBenchmarks(),
                    fn($b) => $b['isUsed'] && isset($b['id']) && is_string($b['id'])
                )
            )),
            'messages' => $customization->getMessages(),
            'display' => array_values(array_map(
                fn($d) => $d['id'],
                array_filter(
                    $customization->getDisplay(),
                    fn($d) => isset($d['isUsed'], $d['id']) && $d['isUsed'] && is_string($d['id'])
                )
            )),
            'benchmark_percent' => $customization->getBenchmarkPercent(),
            'final_cutoff' => $customization->getFinalCutoff(),
            'gradeables' => $this->buildGradeablesArray($customization),
            'plagiarism' => $customization->getPlagiarism(),
            'manual_grade' => $customization->getManualGrades(),
            'warning' => $customization->getPerformanceWarnings(),
        ];
        return json_encode($json, JSON_PRETTY_PRINT);
    }

    /**
     * Build the gradeables array for the customization JSON, including per-gradeable
     * curves and percent overrides if present.
     *
     * @param RainbowCustomization $customization
     * @return array<int, array<string, array<int, array<string, mixed>>|float|int|string>>
     */
    private function buildGradeablesArray(RainbowCustomization $customization): array {
        $customization_data = $customization->getCustomizationData();
        $bucket_counts = $customization->getBucketCounts();
        $bucket_remove_lowest = $customization->getBucketRemoveLowest();
        $bucket_percentages = $customization->getBucketPercentages();
        $per_gradeable_curves = $customization->getPerGradeableCurves();
        $used_buckets = $customization->getUsedBuckets();
        $gradeables = [];
        foreach ($used_buckets as $bucket) {
            $bucket_gradeables = $customization_data[$bucket] ?? [];
            $ids = [];
            foreach ($bucket_gradeables as $g) {
                // Base gradeable data
                $gradeable = [
                    'max' => $g['max_score'],
                    'release_date' => $g['grade_release_date'],
                    'id' => $g['id'],
                ];
                // Per-gradeable percent override
                if ($g['override_percent'] === true) {
                    $gradeable['percent'] = $g['percent'] / 100.0;
                }
                // Per-gradeable curves
                if (isset($per_gradeable_curves[$bucket][$g['id']])) {
                    $gradeable['curve'] = array_values(
                        array_filter(
                            $per_gradeable_curves[$bucket][$g['id']],
                            fn($curve) => $curve !== ''
                        )
                    );
                }
                array_push($ids, $gradeable);
            }
            $gradeables[] = [
                'type' => $bucket,
                'count' => $bucket_counts[$bucket] ?? count($ids),
                'remove_lowest' => $bucket_remove_lowest[$bucket] ?? 0,
                'percent' => (float) ($bucket_percentages[$bucket] ?? 0) / 100.0,
                'ids' => $ids,
            ];
        }
        return $gradeables;
    }
}
