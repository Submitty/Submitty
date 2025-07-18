<?php

declare(strict_types=1);

namespace app\controllers\admin;

use app\entities\plagiarism\PlagiarismRunAccess;
use app\exceptions\DatabaseException;
use app\exceptions\FileNotFoundException;
use app\exceptions\FileWriteException;
use app\exceptions\ValidationException;
use app\libraries\response\ResponseInterface;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\plagiarism\PlagiarismUtils;
use app\libraries\routers\AccessControl;
use Exception;
use DateTime;
use Symfony\Component\Routing\Annotation\Route;
use app\models\User;
use app\views\admin\PlagiarismView;
use app\entities\plagiarism\PlagiarismConfig;

/**
 * Class PlagiarismController
 * @package app\controllers\admin
 */
#[AccessControl(role: "INSTRUCTOR")]
class PlagiarismController extends AbstractController {
    /**
     * This function validates a given gradeable and config ID to make sure they are valid.
     * @param string $gradeable_id
     * @param int $config_id
     * @throws Exception
     */
    private function verifyGradeableAndConfigAreValid(string $gradeable_id, int $config_id): void {
        $em = $this->core->getCourseEntityManager();
        $config = $em->getRepository(PlagiarismConfig::class)
                     ->findOneBy(["gradeable_id" => $gradeable_id, "config_id" => $config_id]);

        if ($config === null) {
            throw new Exception("Error: Invalid plagiarism configuration");
        }
    }


    /**
     * @param string $gradeable_id
     * @param int $config_id
     * @return string
     */
    private function getConfigDirectoryPath(string $gradeable_id, int $config_id): string {
        return FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen", $gradeable_id, $config_id);
    }


    /**
     * @param string $gradeable_id
     * @param int $config_id
     * @param string $user_id
     * @param int $version
     * @return string
     */
    private function getSubmissionPath(string $gradeable_id, int $config_id, string $user_id, int $version): string {
        return FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen", $gradeable_id, $config_id, "users", $user_id, $version);
    }

    /**
     * @param string $gradeable_id
     * @param int $config_id
     * @param string $source_gradeable
     * @param string $user_id
     * @param int $version
     * @return string
     */
    private function getOtherGradeablePath(string $gradeable_id, int $config_id, string $source_gradeable, string $user_id, int $version): string {
        return FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen", $gradeable_id, $config_id, "other_gradeables", $source_gradeable, $user_id, $version);
    }


    /**
     * @param string $gradeable_id
     * @param int $config_id
     * @return string
     */
    private function getQueuePath(string $gradeable_id, int $config_id): string {
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        $daemon_job_queue_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue");
        return FileUtils::joinPaths($daemon_job_queue_path, "lichen__{$semester}__{$course}__{$gradeable_id}__{$config_id}.json");
    }


    /**
     * @param string $gradeable_id
     * @param int $config_id
     * @return string
     */
    private function getProcessingQueuePath(string $gradeable_id, int $config_id): string {
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        $daemon_job_queue_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue");
        return FileUtils::joinPaths($daemon_job_queue_path, "PROCESSING_lichen__{$semester}__{$course}__{$gradeable_id}__{$config_id}.json");
    }

    /**
     * Returns the user group associated with the current signed-in user
     *
     * @return int
     */
    private function getCurrentUserGroup(): int {
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        $group = filegroup(FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "courses", $semester, $course));
        if (!$group) {
            throw new FileNotFoundException("Error: Unable to find course directory for current user");
        }
        return $group;
    }

    /**
     * Gets a list of courses which have the same group as the current course and are thus eligible other terms
     * @return array
     * @throws Exception
     */
    private function getOtherSemesterCourses(): array {
        $this_semester = $this->core->getConfig()->getTerm();
        $this_course = $this->core->getConfig()->getCourse();
        $valid_courses = $this->core->getQueries()->getOtherCoursesWithSameGroup($this_semester, $this_course);
        $ret = [];
        foreach ($valid_courses as $item) {
            $ret[] = "{$item['term']} {$item['course']}";
        }
        sort($ret);
        return $ret;
    }

    /**
     * Get a list of gradeables for the given term+course
     * @param string $term
     * @param string $course
     * @param string $this_gradeable
     * @return array
     * @throws Exception
     */
    private function getOtherOtherGradeables(string $term, string $course, string $this_gradeable): array {
        // check for backwards crawling
        if (str_contains($term, '..') || str_contains($course, '..')) {
            throw new Exception('Error: path contains invalid component ".."');
        }

        // check to make sure the group is the same as the group for the current course
        $this_course_group = filegroup($this->core->getConfig()->getCoursePath());
        if (!$this_course_group) {
            throw new Exception("Unable to obtain group for current course");
        }
        if ($this_course_group !== @filegroup(FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "courses", $term, $course))) {
            throw new Exception("Group for requested course {$term}/{$course} does not match group for current course");
        }

        // actually do the collection of gradeables here
        $gradeables = [];
        foreach (scandir(FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "courses", $term, $course, "submissions")) as $gradeable) {
            if ($gradeable !== '.' && $gradeable !== '..' && ($term !== $this->core->getConfig()->getTerm() || $course !== $this->core->getConfig()->getCourse() || $gradeable !== $this_gradeable)) {
                $gradeables[] = $gradeable;
            }
        }
        sort($gradeables);
        return $gradeables;
    }

    /**
     * @param array $usernames
     * @return array
     */
    private function getIgnoreSubmissionType(array $usernames): array {
        $ignore = [];
        $ignore[0] = []; // array of user categories to be ignored
        $ignore[1] = []; // array of user_id in the category "Others"
        foreach ($usernames as $user_id) {
            $user_obj = $this->core->getQueries()->getUserById($user_id);
            if ($user_obj != null) {
                switch ($user_obj->getGroup()) {
                    case User::GROUP_INSTRUCTOR:
                        if (!in_array("instructors", $ignore[0])) {
                            array_push($ignore[0], "instructors");
                        }
                        break;
                    case User::GROUP_FULL_ACCESS_GRADER:
                        if (!in_array("full_access_graders", $ignore[0])) {
                            array_push($ignore[0], "full_access_graders");
                        }
                        break;
                    case User::GROUP_LIMITED_ACCESS_GRADER:
                        if (!in_array("limited_access_graders", $ignore[0])) {
                            array_push($ignore[0], "limited_access_graders");
                        }
                        break;
                    default:
                        if (!in_array("others", $ignore[0])) {
                            array_push($ignore[0], "others");
                        }
                        array_push($ignore[1], $user_id);
                }
            }
        }
        return $ignore;
    }

    /**
     * @param string $gradeable_id
     * @param int $config_id
     * @return array
     * @throws Exception
     */
    private function getOverallRankings(string $gradeable_id, int $config_id): array {
        $file_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "overall_ranking.txt");
        if (!file_exists($file_path)) {
            throw new Exception("Unable to read overall ranking file for gradeable {$gradeable_id} config {$config_id}");
        }

        $content = file_get_contents($file_path);
        $content = trim($content);
        $ranking = preg_split('/\R/', $content);
        $ranking_array = [];
        foreach ($ranking as $row) {
            $ranking_array[] = preg_split('/\s+/', $row);
        }

        return $ranking_array;
    }

    /**
     * Returns a ranking of users by percent match with user 1 (used for determining the rightmost dropdown list)
     * @param string $gradeable_id
     * @param int $config_id
     * @param string $user_id_1
     * @param int $user_1_version
     * @return array
     * @throws Exception
     */
    private function getRankingsForUser(string $gradeable_id, int $config_id, string $user_id_1, int $user_1_version): array {
        $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $user_1_version), "ranking.txt");
        if (!file_exists($file_path)) {
            throw new Exception("Unable to read ranking file for {$user_id_1} version {$user_1_version} in gradeable {$gradeable_id} config {$config_id}");
        }

        $content = file_get_contents($file_path);
        $content = trim($content);
        $ranking = preg_split('/\R/', $content);
        $ranking_array = [];
        foreach ($ranking as $row) {
            if (strlen(trim($row)) !== 0) { // filter out whitespace-only rows
                $ranking_array[] = preg_split('/\s+/', $row);
            }
        }
        return $ranking_array;
    }


    /**
     * @param string $gradeable_id
     * @param int $config_id
     */
    private function deleteExistingProvidedCode(string $gradeable_id, int $config_id): void {
        $provided_code_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "provided_code", "files");
        if (is_dir($provided_code_path)) {
            FileUtils::emptyDir($provided_code_path);
        }
    }

    /**
     * @param string $temporary_file_path
     * @param string $filename
     * @param string $gradeable_id
     * @param int $config_id
     * @throws Exception
     */
    private function saveNewProvidedCode(string $temporary_file_path, string $filename, string $gradeable_id, int $config_id): void {
        // NOTE: The user of this function is expected to call deleteExistingProvidedCode()
        //       before this function if they wish to clear whatever is already in the directory first.

        if (!file_exists($temporary_file_path)) {
            throw new Exception("Upload failed: Temporary file not found");
        }

        $target_dir = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "provided_code", "files");
        FileUtils::createDir($target_dir, true, 0770); // creates dir if not yet exists

        if (mime_content_type($temporary_file_path) == "application/zip") {
            $zip = new \ZipArchive();
            $res = $zip->open($temporary_file_path);
            if ($res === true) {
                $zip->extractTo($target_dir);
                $zip->close();
            }
            else {
                FileUtils::recursiveRmdir($target_dir);
                $error_message = ($res == 19) ? "Invalid or uninitialized Zip object" : $zip->getStatusString();
                throw new Exception("Upload failed: {$error_message}");
            }
        }
        else {
            // we have to use copy() here so that the permissions won't get messed up
            if (!@copy($temporary_file_path, FileUtils::joinPaths($target_dir, $filename))) {
                throw new Exception("Upload failed: Could not copy file");
            }
            unlink($temporary_file_path);
        }
    }


    /**
     * @param string $job
     * @param string $gradeable_id
     * @param int $config_id
     */
    private function enqueueLichenJob(string $job, string $gradeable_id, int $config_id): void {
        $em = $this->core->getCourseEntityManager();
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        $config = $em->getRepository(PlagiarismConfig::class)
                     ->findOneBy(["gradeable_id" => $gradeable_id, "config_id" => $config_id]);

        if ($config === null) {
            throw new DatabaseException("Error: Unable to find plagiarism configuration");
        }

        $json_file = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "config.json");
        if (file_exists($json_file)) {
            unlink($json_file);
        }

        $regex_dirs = [];
        if ($config->isRegexDirSubmissionsSelected()) {
            $regex_dirs[] = "submissions";
        }
        if ($config->isRegexDirResultsSelected()) {
            $regex_dirs[] = "results";
        }
        if ($config->isRegexDirCheckoutSelected()) {
            $regex_dirs[] = "checkout";
        }

        if ($job === "RunLichen") {
            $lichen_job_data = [
                "job" => $job,
                "semester" => $semester,
                "course" => $course,
                "gradeable" => $gradeable_id,
                "config_id" => $config_id,
                "config_data" => $this->getJsonForConfig($gradeable_id, $config_id)
            ];
        }
        else {
            $lichen_job_data = [
                "job" => $job,
                "semester" => $semester,
                "course" => $course,
                "gradeable" => $gradeable_id,
                "config_id" => $config_id
            ];
        }

        $lichen_job_file = $this->getQueuePath($gradeable_id, $config_id);

        if (!FileUtils::writeJsonFile($lichen_job_file, $lichen_job_data)) {
            throw new FileWriteException("Error: Failed to write lichen job file. Try again");
        }
    }


    /**
     * Returns a data structure containing the contents of a config.json file
     *
     * @param string $gradeable_id
     * @param int $config_id
     * @return array
     * @throws Exception
     */
    private function getJsonForConfig(string $gradeable_id, int $config_id): array {
        $em = $this->core->getCourseEntityManager();
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        /** @var PlagiarismConfig $config */
        $config = $em->getRepository(PlagiarismConfig::class)
            ->findOneBy(["gradeable_id" => $gradeable_id, "config_id" => $config_id]);

        $regex_dirs = [];
        if ($config->isRegexDirSubmissionsSelected()) {
            $regex_dirs[] = "submissions";
        }
        if ($config->isRegexDirResultsSelected()) {
            $regex_dirs[] = "results";
        }
        if ($config->isRegexDirCheckoutSelected()) {
            $regex_dirs[] = "checkout";
        }

        $json = [
            "term" => $semester,
            "course" => $course,
            "gradeable" => $gradeable_id,
            "config_id" => $config_id,
            "version" => $config->getVersionStatus(),
            "regex" => $config->getRegexArray(),
            "regex_dirs" => $regex_dirs,
            "language" => $config->getLanguage(),
            "threshold" => $config->getThreshold(),
            "hash_size" => $config->getHashSize(),
            "other_gradeables" => $config->getOtherGradeables(),
            "ignore_submissions" => $config->getIgnoredSubmissions()
        ];
        if ($config->hasOtherGradeablePaths()) {
            $json["other_gradeable_paths"] = $config->getOtherGradeablePaths();
        }

        return $json;
    }


    /**
     * @param string $refresh_page
     * @return WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism")]
    public function plagiarismMainPage(string $refresh_page = "NO_REFRESH"): WebResponse {
        $em = $this->core->getCourseEntityManager();
        $all_configurations = [];

        /** @var array<PlagiarismConfig> $configs */
        $configs = $em->getRepository(PlagiarismConfig::class)->findAll();
        $all_gradeables = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        $tmp = [];
        foreach ($all_gradeables as $gradeable) {
            $tmp[$gradeable["g_id"]] = $gradeable["g_title"];
        }
        $all_gradeables = $tmp;

        $user_id = $this->core->getUser()->getId();
        foreach ($configs as $config) {
            $configuration = [
                "g_id" => $config->getGradeableID(),
                "g_title" => $all_gradeables[$config->getGradeableID()],
                "due_date" => $this->core->getQueries()->getDueDateForGradeableById($config->getGradeableID()),
                "g_config_version" => $config->getConfigID(),
                "last_run_timestamp" => $config->getLastRunTimestamp(),
                "has_been_viewed" => $config->userHasAccessed($user_id)
            ];
            $all_configurations[] = $configuration;
        }

        usort($all_configurations, function ($a, $b): int {
            if ($a['due_date'] === null) {
                return -1;
            }

            if ($a["due_date"] === $b["due_date"] && $a["g_title"] === $b["g_title"] && $a["g_config_version"] === $b["g_config_version"]) {
                return 0;
            }

            return $a['due_date'] > $b['due_date']
                   || ($a["due_date"] === $b["due_date"] && $a["g_title"] > $b["g_title"])
                   || ($a["due_date"] === $b["due_date"] && $a["g_title"] === $b["g_title"] && $a["g_config_version"] > $b["g_config_version"]) ? 1 : -1;
        });

        // TODO: return to this and enable later
        // $nightly_rerun_info_file = "/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/nightly_rerun.json";
        // if (!file_exists($nightly_rerun_info_file)) {
        //     $nightly_rerun_info = [];
        //     foreach ($gradeables_with_plagiarism_result as $gradeable_id_title) {
        //         $nightly_rerun_info[$gradeable_id_title['g_id']] = false;
        //     }
        //     if (file_put_contents($nightly_rerun_info_file, json_encode($nightly_rerun_info, JSON_PRETTY_PRINT)) === false) {
        //         die("Failed to create nightly rerun info file");
        //     }
        // }
        // else {
        //     $nightly_rerun_info = json_decode(file_get_contents($nightly_rerun_info_file), true);
        //     foreach ($nightly_rerun_info as $gradeable_id => $nightly_rerun_status) {
        //         $flag = 0;
        //         foreach ($gradeables_with_plagiarism_result as $gradeable_id_title) {
        //             if ($gradeable_id_title['g_id'] == $gradeable_id) {
        //                 $flag = 1;
        //                 break;
        //             }
        //         }
        //         if ($flag == 0) {
        //             #implies plagiarism result for this gradeable are deleted
        //             unset($nightly_rerun_info[$gradeable_id]);
        //         }
        //     }
        //
        //     foreach ($gradeables_with_plagiarism_result as $gradeable_id_title) {
        //         if (!array_key_exists($gradeable_id_title['g_id'], $nightly_rerun_info)) {
        //             #implies plagiarism was run for this gradeable
        //             $nightly_rerun_info[$gradeable_id_title['g_id']] = false;
        //         }
        //     }
        //     if (file_put_contents($nightly_rerun_info_file, json_encode($nightly_rerun_info, JSON_PRETTY_PRINT)) === false) {
        //         die("Failed to create nightly rerun info file");
        //     }
        // }

        // gather and format all the data for every config to display in the main page table
        $plagiarism_result_info = [];
        $gradeable_date_format = $this->core->getConfig()->getDateTimeFormat()->getFormat('gradeable');
        foreach ($all_configurations as $gradeable) {
            $overall_ranking_file = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable['g_id'], $gradeable['g_config_version']), "overall_ranking.txt");
            // if we have an overall ranking file, it means that the Lichen job finished successfully and there are matches
            $has_results = file_exists($overall_ranking_file) && file_get_contents($overall_ranking_file) !== "";

            $timestamp = $gradeable["last_run_timestamp"]->format($gradeable_date_format);
            $submissions = $this->core->getQueries()->getTotalSubmissionsToGradeable($gradeable['g_id']);
            $ranking_available = false;
            $matching_submission_count = 0;
            $top_match_percent = "N/A";
            $gradeable_link = "";
            $rerun_plagiarism_link = "";
            $edit_plagiarism_link = "";
            $delete_form_action = "";
            $nightly_rerun_link = "";
            $night_rerun_status = ""; // TODO: future feature
            $download_config_file_link = $this->core->buildCourseUrl(["plagiarism", "gradeable", $gradeable['g_id'], "download_config_file"]) . "?config_id={$gradeable['g_config_version']}";

            if (file_exists($this->getProcessingQueuePath($gradeable['g_id'], $gradeable['g_config_version']))) {
                // lichen job in processing stage for this gradeable but not completed
                $in_queue = true;
                $processing = true;
                $gradeable['has_been_viewed'] = true; // Needed to remove the "unviewed" background styling while running
            }
            elseif (file_exists($this->getQueuePath($gradeable['g_id'], $gradeable['g_config_version']))) {
                // lichen job in queue for this gradeable but processing not started
                $in_queue = true;
                $processing = false;
                $gradeable['has_been_viewed'] = true; // Needed to remove the "unviewed" background styling while running
            }
            else {
                // no lichen job
                $in_queue = false;
                $processing = false;
                if ($has_results) {
                    try {
                        $rankings = $this->getOverallRankings($gradeable['g_id'], $gradeable['g_config_version']);
                        $top_match_percent = $rankings[0][2];
                        $matching_submission_count = count($rankings);
                        $ranking_available = true;
                    }
                    catch (Exception $e) {
                        $this->core->addErrorMessage($e->getMessage());
                    }

                    $gradeable_link = $this->core->buildCourseUrl(['plagiarism', 'gradeable', $gradeable['g_id']]) . "?config_id={$gradeable['g_config_version']}";
                }
                $rerun_plagiarism_link = $this->core->buildCourseUrl(["plagiarism", "gradeable", $gradeable['g_id'], "rerun"]) . "?config_id={$gradeable['g_config_version']}";
                $edit_plagiarism_link = $this->core->buildCourseUrl(["plagiarism", "configuration", "edit"]) . "?gradeable_id={$gradeable['g_id']}&config_id={$gradeable['g_config_version']}";
                $delete_form_action = $this->core->buildCourseUrl(['plagiarism', 'gradeable', $gradeable['g_id'], 'delete']) . "?config_id={$gradeable['g_config_version']}";
                $nightly_rerun_link = $this->core->buildCourseUrl(["plagiarism", "gradeable", $gradeable['g_id'], "nightly_rerun"]) . "?config_id={$gradeable['g_config_version']}";
            }
            $plagiarism_result_info[] = [
                'title' => $gradeable['g_title'],
                'id' => $gradeable['g_id'],
                'config_id' => $gradeable['g_config_version'],
                'duedate' => $gradeable['due_date'] == null ? null : $gradeable['due_date']->format($gradeable_date_format),
                'timestamp' => $timestamp,
                'submissions' => $submissions,
                'in_queue' => $in_queue,
                'processing' => $processing,
                'ranking_available' => $ranking_available,
                'top_match_percent' => $top_match_percent,
                'matching_submission_count' => $matching_submission_count,
                'gradeable_link' => $gradeable_link,
                'rerun_plagiarism_link' => $rerun_plagiarism_link,
                'edit_plagiarism_link' => $edit_plagiarism_link,
                'delete_form_action' => $delete_form_action,
                'nightly_rerun_link' => $nightly_rerun_link,
                'night_rerun_status' => $night_rerun_status,
                'download_config_file_link' => $download_config_file_link,
                'has_been_viewed' => $gradeable['has_been_viewed']
            ];
        }

        return new WebResponse(
            PlagiarismView::class,
            'plagiarismMainPage',
            $plagiarism_result_info,
            $refresh_page
        );
    }

    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @return ResponseInterface
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}")]
    public function showPlagiarismResult(string $gradeable_id, string $config_id): ResponseInterface {
        $em = $this->core->getCourseEntityManager();
        /** @var PlagiarismConfig $plagiarism_config */
        $plagiarism_config = $em->getRepository(PlagiarismConfig::class)->findOneBy(["gradeable_id" => $gradeable_id, "config_id" => $config_id]);

        $error_return_url = $this->core->buildCourseUrl(['plagiarism']);

        $gradeable_config = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        $gradeable_title = $gradeable_config->getTitle();

        try {
            $config_id = intval($config_id);
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($error_return_url);
        }

        try {
            $rankings_data = $this->getOverallRankings($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage("Plagiarism Detection job is already running for this gradeable.");
            return new RedirectResponse($error_return_url);
        }

        if (count($rankings_data) === 0) {
            $this->core->addSuccessMessage("There are no matches (plagiarism) for the gradeable with current configuration");
        }

        $is_team_assignment = $this->core->getQueries()->getGradeableConfig($gradeable_id)->isTeamAssignment();

        $user_ids_and_names = [];
        if (!$is_team_assignment) {
            $user_ids = [];
            foreach ($rankings_data as $item) {
                $user_ids[$item[0]] = null;
            }
            $user_ids = array_keys($user_ids);

            $user_ids_and_names = $this->core->getQueries()->getUsersByIds($user_ids);
            if ($user_ids_and_names === null) {
                $this->core->addErrorMessage("Error: Unable to load left dropdown list");
                return new RedirectResponse($error_return_url);
            }
        }

        $rankings = [];
        foreach ($rankings_data as $item) {
            $display_name = "";
            if (!$is_team_assignment) {
                $display_name = "{$user_ids_and_names[$item[0]]->getDisplayedGivenName()} {$user_ids_and_names[$item[0]]->getDisplayedFamilyName()}";
            }
            $temp = [
                "percent" => $item[2],
                "match_count" => $item[3],
                "user_id" => $item[0],
                "display_name" => $display_name,
                "version" => $item[1],
            ];
            array_push($rankings, $temp);
        }

        $access_timestamp = new PlagiarismRunAccess($plagiarism_config, $this->core->getUser()->getId());
        $em->persist($access_timestamp);
        $plagiarism_config->addAccess($access_timestamp);
        $em->flush();

        return new WebResponse(
            ['admin', 'Plagiarism'],
            'showPlagiarismResult',
            $gradeable_id,
            $config_id,
            $gradeable_title,
            $rankings
        );
    }

    /**
     * @param string $new_or_edit
     * @param string $gradeable_id
     * @param string $config_id
     * @return RedirectResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/configuration/new", methods: ["POST"])]
    public function savePlagiarismConfiguration(string $new_or_edit, string $gradeable_id, string $config_id): RedirectResponse {
        $em = $this->core->getCourseEntityManager();
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        // Determine whether this is a new config or an existing config
        $return_url = $this->core->buildCourseUrl(['plagiarism', 'configuration', 'new']);
        if ($new_or_edit === "new") {
            $gradeable_id = $_POST["gradeable_id"];
        }
        elseif ($new_or_edit === "edit") {
            $return_url = $this->core->buildCourseUrl(['plagiarism', 'configuration', 'edit']) . "?gradeable_id={$gradeable_id}&config_id={$config_id}";
        }

        if (!is_numeric($config_id) && $new_or_edit !== "new") {
            $this->core->addErrorMessage("Error: Config ID must be a valid integer configuration ID");
            return new RedirectResponse($return_url);
        }
        $config_id = intval($config_id);

        // Check if Lichen job is already running
        if (file_exists($this->getQueuePath($gradeable_id, $config_id)) || file_exists($this->getProcessingQueuePath($gradeable_id, $config_id))) {
            $this->core->addErrorMessage("A job is already running for the gradeable. Try again after a while.");
            return new RedirectResponse($return_url);
        }

        // Generate a unique number for this version of the gradeable //////////
        if ($new_or_edit === "new") {
            try {
                $config_id = $em->getRepository(PlagiarismConfig::class)
                    ->findOneBy(["gradeable_id" => $gradeable_id], ["config_id" => "DESC"]);
                if ($config_id === null) {
                    $config_id = 1;
                }
                else {
                    $config_id = $config_id->getConfigID() + 1;
                }
            }
            catch (Exception $e) {
                $this->core->addErrorMessage($e->getMessage());
                return new RedirectResponse($return_url);
            }
        }

        // Save configuration form data
        if ($new_or_edit === "new" && isset($_POST["config_option"]) && $_POST["config_option"] === "upload_config") { // uploaded config file
            // error checking
            if (empty($_FILES) || !isset($_FILES["upload_config_file"]) || !isset($_FILES["upload_config_file"]["tmp_name"]) || $_FILES["upload_config_file"]["tmp_name"] === "") {
                $this->core->addErrorMessage("Error: File upload failed");
                return new RedirectResponse($return_url);
            }
            // load, parse, and save the config info
            try {
                $data = json_decode(file_get_contents($_FILES["upload_config_file"]["tmp_name"]), true);

                // This is a little ugly/repetitive but it can be frustrating for users to get nondescriptive errors
                // so we try to make potential error cases a little more helpful
                $keys = ["version", "regex", "regex_dirs", "language", "threshold", "hash_size", "other_gradeables", "ignore_submissions"];
                $error_message = "";
                foreach ($keys as $key) {
                    if (!isset($data[$key])) {
                        $error_message .= "Error: Invalid or missing field: {$key}\n";
                    }
                }

                if ($error_message !== "") {
                    throw new ValidationException($error_message, []);
                }

                // Input validation to check for invalid inputs occurs here
                $plagiarism_config = new PlagiarismConfig(
                    $gradeable_id,
                    $config_id,
                    $data["version"],
                    $data["regex"],
                    in_array("submissions", $data["regex_dirs"]),
                    in_array("results", $data["regex_dirs"]),
                    in_array("checkout", $data["regex_dirs"]),
                    $data["language"],
                    $data["threshold"],
                    $data["hash_size"],
                    $data["other_gradeables"],
                    $data["other_gradeable_paths"] ?? [],
                    $this->getCurrentUserGroup(),
                    $data["ignore_submissions"]
                );
                $em->persist($plagiarism_config);
            }
            catch (ValidationException $e) {
                $this->core->addErrorMessage($e->getMessage());
                return new RedirectResponse($return_url);
            }
        }
        elseif ($new_or_edit === "new" && isset($_POST["config_option"]) && $_POST["config_option"] === "import_config") { // imported from another existing config
            try {
                /** @var PlagiarismConfig $source_config */
                $source_config = $em->getRepository(PlagiarismConfig::class)->findOneBy(["gradeable_id" => $_POST["import-config-gradeable"], "config_id" => $_POST["import-config-config-id"]]);
                $plagiarism_config = new PlagiarismConfig(
                    $gradeable_id,
                    $config_id,
                    $source_config->getVersionStatus(),
                    $source_config->getRegexArray(),
                    $source_config->isRegexDirSubmissionsSelected(),
                    $source_config->isRegexDirResultsSelected(),
                    $source_config->isRegexDirCheckoutSelected(),
                    $source_config->getLanguage(),
                    $source_config->getThreshold(),
                    $source_config->getHashSize(),
                    $source_config->getOtherGradeables(),
                    $source_config->getOtherGradeablePaths(),
                    $this->getCurrentUserGroup(),
                    $source_config->getIgnoredSubmissions()
                );
                $em->persist($plagiarism_config);
            }
            catch (ValidationException $e) {
                $this->core->addErrorMessage("Error: Unable to load source configuration");
                return new RedirectResponse($return_url);
            }
        }
        else { // either editing an existing config or saving a new one with manual config data entry
            // Version /////////////////////////////////////////////////////////////
            $version_option = $_POST['version_option'] ?? "";


            // Regex ///////////////////////////////////////////////////////////////
            // TODO: Can we find a way to validate the regex more thoroughly to tell the user their regex was invalid before feeding it to Lichen?
            if (!isset($_POST["regex_dir"]) || !isset($_POST["regex_to_select_files"])) {
                $this->core->addErrorMessage("Error: Unable to read regex fields");
                return new RedirectResponse($return_url);
            }
            $regex_directories = $_POST["regex_dir"];
            $regex_for_selecting_files = explode(',', str_replace(' ', '', $_POST['regex_to_select_files']));


            // Language ////////////////////////////////////////////////////////////
            $language = $_POST["language"] ?? "";


            // Common code threshold ///////////////////////////////////////////////
            $threshold = (int) $_POST['threshold'] ?? 0;


            // Hash Size ///////////////////////////////////////////////////////////
            $hash_size = (int) $_POST['hash_size'] ?? 0;


            // other gradeables ////////////////////////////////////////////////////
            $other_gradeables = [];
            if ($_POST["other_gradeables_option"] === "has_other_gradeables") {
                if (isset($_POST["other_semester_course"]) !== isset($_POST["other_gradeable"])) {
                    $this->core->addErrorMessage("Invalid input provided for other gradeables");
                    $this->core->redirect($return_url);
                }
                foreach ($_POST["other_semester_course"] ?? [] as $index => $sem_course) {
                    if (!isset($_POST["other_gradeable"][$index])) {
                        $this->core->addErrorMessage("Invalid input provided for other term gradeables");
                        $this->core->redirect($return_url);
                    }
                    else {
                        $tokens = explode(" ", $sem_course);
                        if (count($tokens) !== 2) {
                            $this->core->addErrorMessage("Invalid input provided for other semester and course");
                            $this->core->redirect($return_url);
                        }
                        $other_semester = $tokens[0];
                        $other_course = $tokens[1];
                        $other_gradeable = $_POST["other_gradeable"][$index];
                        // Error checking
                        if (str_contains($other_semester, '..') || str_contains($other_course, '..') || str_contains($other_gradeable, '..')) {
                            $this->core->addErrorMessage("Error: other gradeables string contains invalid component '..'");
                            return new RedirectResponse($return_url);
                        }
                        $other_g_submissions_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "courses", $other_semester, $other_course, "submissions", $other_gradeable);
                        if (!is_dir($other_g_submissions_path) || count(scandir($other_g_submissions_path)) === 2) {
                            $this->core->addErrorMessage("Error: submssions to other gradeable provided not found");
                            return new RedirectResponse($return_url);
                        }
                        $to_append = [
                            "other_semester" => $other_semester,
                            "other_course" => $other_course,
                            "other_gradeable" => $other_gradeable
                        ];
                        if ($other_semester === $semester && $other_course === $course && $other_gradeable === $gradeable_id) {
                            $this->core->addErrorMessage("Error: attempt to compare this gradeable '{$gradeable_id}' to itself as other gradeable");
                            return new RedirectResponse($return_url);
                        }
                        if (in_array($to_append, $other_gradeables)) {
                            $this->core->addErrorMessage("Error: duplicate other gradeable found: {$other_semester} {$other_course} {$other_gradeable}");
                            return new RedirectResponse($return_url);
                        }
                        $other_gradeables[] = $to_append;
                    }
                }

                if (isset($_POST["other-gradeable-paths"]) && $_POST["other-gradeable-paths"] !== "") {
                    $paths = explode(",", $_POST["other-gradeable-paths"]);
                    $other_gradeable_paths = [];
                    foreach ($paths as $path) {
                        $other_gradeable_paths[] = trim($path);
                    }
                }
            }


            // Submissions to ignore ///////////////////////////////////////////////
            $ignore_submission_option = [];
            if (isset($_POST['ignore_submission_option'])) {
                // error checking
                $valid_inputs = ["ignore_instructors", "ignore_full_access_graders", "ignore_limited_access_graders", "ignore_others"];
                foreach ($_POST['ignore_submission_option'] as $ignore_type) {
                    if (!in_array($ignore_type, $valid_inputs)) {
                        $this->core->addErrorMessage("Invalid type provided for users to ignore");
                        return new RedirectResponse($return_url);
                    }
                }
                // get user_id in the user categories specified
                $graders = $this->core->getQueries()->getAllGraders();
                foreach ($graders as $grader) {
                    if (
                        $grader->getGroup() == User::GROUP_INSTRUCTOR && in_array("ignore_instructors", $_POST['ignore_submission_option'])
                        || $grader->getGroup() == User::GROUP_FULL_ACCESS_GRADER && in_array("ignore_full_access_graders", $_POST['ignore_submission_option'])
                        || $grader->getGroup() == User::GROUP_LIMITED_ACCESS_GRADER && in_array("ignore_limited_access_graders", $_POST['ignore_submission_option'])
                    ) {
                        array_push($ignore_submission_option, $grader->getId());
                    }
                }
                // parse and append user id's specified in "Others"
                if (in_array("ignore_others", $_POST['ignore_submission_option']) && isset($_POST["ignore_others_list"])) {
                    // parse and push to the array of users
                    $other_users = explode(", ", $_POST["ignore_others_list"]);
                    foreach ($other_users as $other_user) {
                        array_push($ignore_submission_option, $other_user);
                    }
                }
            }

            // Save the config /////////////////////////////////////////////////////
            try {
                if ($new_or_edit === "new") {
                    $plagiarism_config = new PlagiarismConfig(
                        $gradeable_id,
                        $config_id,
                        $version_option,
                        $regex_for_selecting_files,
                        in_array("submissions", $regex_directories),
                        in_array("results", $regex_directories),
                        in_array("checkout", $regex_directories),
                        $language,
                        $threshold,
                        $hash_size,
                        $other_gradeables,
                        $other_gradeable_paths ?? [],
                        $this->getCurrentUserGroup(),
                        $ignore_submission_option
                    );
                }
                else {
                    /** @var PlagiarismConfig $plagiarism_config */
                    $plagiarism_config = $em->getRepository(PlagiarismConfig::class)->findOneBy(["gradeable_id" => $gradeable_id, "config_id" => $config_id]);
                    $plagiarism_config->setVersionStatus($version_option);
                    $plagiarism_config->setRegexArray($regex_for_selecting_files);
                    $plagiarism_config->setRegexDirSubmissions(in_array("submissions", $regex_directories));
                    $plagiarism_config->setRegexDirResults(in_array("results", $regex_directories));
                    $plagiarism_config->setRegexDirCheckout(in_array("checkout", $regex_directories));
                    $plagiarism_config->setLanguage($language);
                    $plagiarism_config->setThreshold($threshold);
                    $plagiarism_config->setHashSize($hash_size);
                    $plagiarism_config->setOtherGradeables($other_gradeables);
                    $plagiarism_config->setOtherGradeablePaths($other_gradeable_paths ?? [], $this->getCurrentUserGroup());
                    $plagiarism_config->setIgnoredSubmissions($ignore_submission_option);
                }
            }
            catch (ValidationException $e) {
                $this->core->addErrorMessage($e->getMessage());
                return new RedirectResponse($return_url);
            }
        }

        // Create directory structure //////////////////////////////////////////
        if (!is_dir($this->getConfigDirectoryPath($gradeable_id, $config_id))) {
            FileUtils::createDir($this->getConfigDirectoryPath($gradeable_id, $config_id), true, 0770);
        }

        // Upload instructor provided code /////////////////////////////////////
        if ($new_or_edit === "edit" && ($_POST['provided_code_option'] !== "code_provided" || $_FILES['provided_code_file']['tmp_name'] !== "")) {
            // delete the old provided code
            $this->deleteExistingProvidedCode($gradeable_id, $config_id);
        }
        if ($_POST['provided_code_option'] === "code_provided" && $_FILES['provided_code_file']['tmp_name'] !== "") {
            $plagiarism_config->setHasProvidedCode(true);

            // error checking
            if (empty($_FILES) || !isset($_FILES['provided_code_file']) || !isset($_FILES['provided_code_file']['tmp_name']) || $_FILES['provided_code_file']['tmp_name'] === "") {
                $this->core->addErrorMessage("Upload failed: Instructor code not provided");
                return new RedirectResponse($return_url);
            }
            // save the code
            try {
                $this->saveNewProvidedCode($_FILES['provided_code_file']['tmp_name'], $_FILES['provided_code_file']['name'], $gradeable_id, $config_id);
            }
            catch (Exception $e) {
                $this->core->addErrorMessage($e->getMessage());
                return new RedirectResponse($return_url);
            }
        }
        else {
            $plagiarism_config->setHasProvidedCode(false);
        }

        $em->persist($plagiarism_config);
        $em->flush();

        // Create the Lichen job ///////////////////////////////////////////////
        try {
            $this->enqueueLichenJob("RunLichen", $gradeable_id, $config_id);
        }
        catch (DatabaseException | FileWriteException $e) {
            $this->core->addErrorMessage("Failed to add configuration to Lichen queue. Create the configuration again.");
            return new RedirectResponse($return_url);
        }

        $this->core->addSuccessMessage("Lichen Plagiarism Detection configuration created for {$gradeable_id} configuration #{$config_id}");
        return new RedirectResponse($this->core->buildCourseUrl(['plagiarism']) . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }


    /**
     * @return WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/configuration/new", methods: ["GET"])]
    public function configurePlagiarismForm(): WebResponse {
        $gradeable_with_submission = array_diff(scandir(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions/")), ['.', '..']);
        $gradeable_ids_titles = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        foreach ($gradeable_ids_titles as $i => $gradeable_id_title) {
            if (!in_array($gradeable_id_title['g_id'], $gradeable_with_submission)) {
                unset($gradeable_ids_titles[$i]);
                continue;
            }
            $duedate = $this->core->getQueries()->getDueDateForGradeableById($gradeable_id_title['g_id']);
            $gradeable_ids_titles[$i]['due_date'] = $duedate == null ? 'no due date' : $duedate->format($this->core->getConfig()->getDateTimeFormat()->getFormat('late_days_allowed'));
        }

        usort($gradeable_ids_titles, function ($a, $b) {
            if ($a['due_date'] === 'no due date') {
                return true;
            }
            if ($b['due_date'] === 'no due date') {
                return false;
            }

            if (new DateTime($a['due_date']) > new DateTime($b['due_date'])) {
                return 1;
            }
            elseif (new DateTime($a['due_date']) < new DateTime($b['due_date'])) {
                return -1;
            }
            else {
                return 0;
            }
        });

        $em = $this->core->getCourseEntityManager();
        $all_configs = $em->getRepository(PlagiarismConfig::class)->findAll();

        $gradeables_with_plag_configs = [];
        foreach ($all_configs as $item) {
            if (!isset($gradeables_with_plag_configs[$item->getGradeableID()])) {
                $gradeables_with_plag_configs[$item->getGradeableID()] = [];
            }
            $gradeables_with_plag_configs[$item->getGradeableID()][] = $item->getConfigID();
        }

        $config = [];

        // Default values for the form
        $config["gradeable_id"] = "";
        $config["config_id"] = "";
        $config["title"] = null;
        $config["gradeable_ids_titles"] = $gradeable_ids_titles;
        $config["provided_code"] = false;
        $config["provided_code_filenames"] = [];
        $config["version"] = "all_versions";
        $config["regex"] = "";
        $config["regex_dirs"] = ["submissions"];
        $config["language"] = array_fill_keys(array_keys(PlagiarismUtils::SUPPORTED_LANGUAGES), "");
        $config["language"]["plaintext"] = "selected";
        $config["threshold"] = PlagiarismUtils::DEFAULT_THRESHOLD;
        $config["hash_size"] = PlagiarismUtils::SUPPORTED_LANGUAGES["plaintext"]["hash_size"];
        $config["has_other_gradeables"] = false;
        $config["other_semester_courses"] = $this->getOtherSemesterCourses();
        $config["other_gradeables"] = [];
        $config["other_gradeable_paths"] = "";
        $config["ignore_submissions"] = [];
        $config["ignore_submissions_list"] = "";

        return new WebResponse(
            ['admin', 'Plagiarism'],
            'configurePlagiarismForm',
            'new',
            $config,
            $gradeables_with_plag_configs
        );
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @return ResponseInterface
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/configuration/edit")]
    public function editPlagiarismSavedConfig(string $gradeable_id, string $config_id): ResponseInterface {
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        // Error checking
        try {
            $config_id = intval($config_id);
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage($e);
            return new RedirectResponse($return_url);
        }

        // get the config
        $em = $this->core->getCourseEntityManager();
        /** @var PlagiarismConfig $plagiarism_config */
        $plagiarism_config = $em->getRepository(PlagiarismConfig::class)->findOneBy(["gradeable_id" => $gradeable_id, "config_id" => $config_id]);

        // check to see if there are any provided code files
        $has_provided_code = $plagiarism_config->hasProvidedCode();
        $provided_code_filenames = [];
        if ($has_provided_code) {
            $provided_code_filename_array = array_diff(scandir(FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "provided_code", "files")), [".", ".."]);
            foreach ($provided_code_filename_array as $filename) {
                $provided_code_filenames[] = $filename;
            }
        }
        $ignore = $this->getIgnoreSubmissionType($plagiarism_config->getIgnoredSubmissions());
        $other_gradeables_array = $plagiarism_config->getOtherGradeables();
        foreach ($other_gradeables_array as &$gradeable) {
            try {
                $gradeable["other_gradeables"] = $this->getOtherOtherGradeables($gradeable["other_semester"], $gradeable["other_course"], $gradeable_id);
            }
            catch (Exception $e) {
                $this->core->addErrorMessage($e->getMessage());
                return new RedirectResponse($return_url);
            }
        }

        $regex_dirs = [];
        if ($plagiarism_config->isRegexDirSubmissionsSelected()) {
            $regex_dirs[] = "submissions";
        }
        if ($plagiarism_config->isRegexDirResultsSelected()) {
            $regex_dirs[] = "results";
        }
        if ($plagiarism_config->isRegexDirCheckoutSelected()) {
            $regex_dirs[] = "checkout";
        }

        $config = [];

        $config["gradeable_id"] = $plagiarism_config->getGradeableID();
        $config["config_id"] = $plagiarism_config->getConfigID();
        $config["title"] = $this->core->getQueries()->getGradeableConfig($plagiarism_config->getGradeableID())->getTitle();
        $config["gradeable_ids_titles"] = [];
        $config["provided_code"] = $has_provided_code;
        $config["provided_code_filenames"] = $provided_code_filenames;
        $config["version"] = $plagiarism_config->getVersionStatus();
        $config["regex"] = implode(", ", $plagiarism_config->getRegexArray());
        $config["regex_dirs"] = $regex_dirs;
        $config["language"] = array_fill_keys(array_keys(PlagiarismUtils::SUPPORTED_LANGUAGES), "");
        $config["language"][$plagiarism_config->getLanguage()] = "selected";
        $config["threshold"] = $plagiarism_config->getThreshold();
        $config["hash_size"] = $plagiarism_config->getHashSize();
        $config["has_other_gradeables"] = count($plagiarism_config->getOtherGradeables()) > 0 || count($plagiarism_config->getOtherGradeablePaths()) > 0;
        $config["other_semester_courses"] = $this->getOtherSemesterCourses();
        $config["other_gradeables"] = $other_gradeables_array;
        $config["other_gradeable_paths"] = implode(",\n", $plagiarism_config->getOtherGradeablePaths());
        $config["ignore_submissions"] = $ignore[0];
        $config["ignore_submissions_list"] = implode(", ", $ignore[1]);

        return new WebResponse(
            ['admin', 'Plagiarism'],
            'configurePlagiarismForm',
            'edit',
            $config,
            [] // placeholder
        );
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @return RedirectResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/rerun")]
    public function reRunPlagiarism(string $gradeable_id, string $config_id): RedirectResponse {
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        try {
            $config_id = intval($config_id);
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($return_url);
        }

        $lichen_job_file = $this->getQueuePath($gradeable_id, $config_id);
        $lichen_job_file_processing = $this->getProcessingQueuePath($gradeable_id, $config_id);

        # Re run only if following checks are passed.
        if (file_exists($lichen_job_file) || file_exists($lichen_job_file_processing)) {
            $this->core->addErrorMessage("A job is already running for the gradeable. Try again after a while.");
            return new RedirectResponse($return_url);
        }

        // Update the last run timestamp
        $em = $this->core->getCourseEntityManager();
        /** @var PlagiarismConfig $plagiarism_config */
        $plagiarism_config = $em->getRepository(PlagiarismConfig::class)->findOneBy(["gradeable_id" => $gradeable_id, "config_id" => $config_id]);
        $plagiarism_config->setLastRunToCurrentTime();
        $em->flush();

        try {
            $this->enqueueLichenJob("RunLichen", $gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($return_url);
        }

        $this->core->addSuccessMessage("Re-Run of Lichen Plagiarism for {$gradeable_id} configuration #{$config_id}");
        return new RedirectResponse($return_url . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @return RedirectResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/delete", methods: ["POST"])]
    public function deletePlagiarismResultAndConfig(string $gradeable_id, string $config_id): RedirectResponse {
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        try {
            $config_id = intval($config_id);
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage($e);
            return new RedirectResponse($return_url);
        }

        if (file_exists($this->getQueuePath($gradeable_id, $config_id)) || file_exists($this->getProcessingQueuePath($gradeable_id, $config_id))) {
            $this->core->addErrorMessage("A job is already running for this configuration. Try again after a while.");
            return new RedirectResponse($return_url);
        }

        try {
            $em = $this->core->getCourseEntityManager();
            $to_be_deleted = $em->getRepository(PlagiarismConfig::class)->findOneBy(["gradeable_id" => $gradeable_id, "config_id" => $config_id]);
            $em->remove($to_be_deleted);
            $this->enqueueLichenJob("DeleteLichenResult", $gradeable_id, $config_id);
            $em->flush();
        }
        catch (FileWriteException | DatabaseException $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($return_url);
        }

        $em->flush();

        $this->core->addSuccessMessage("Lichen results and saved configuration will be deleted.");
        return new RedirectResponse($this->core->buildCourseUrl(['plagiarism']) . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/download_config_file")]
    public function downloadConfigFile(string $gradeable_id, string $config_id) {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        try {
            $result = $this->getJsonForConfig($gradeable_id, intval($config_id));
            echo json_encode($result, JSON_PRETTY_PRINT);
        }
        catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @param string $gradeable_id
     * @param string $config_id
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/nightly_rerun")]
    public function toggleNightlyRerun(string $gradeable_id, string $config_id) {
        // $semester = $this->core->getConfig()->getTerm();
        // $course = $this->core->getConfig()->getCourse();
        // $return_url = $this->core->buildCourseUrl(['plagiarism']);
        //
        // $nightly_rerun_info_file = "/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/nightly_rerun.json";
        //
        // $nightly_rerun_info = json_decode(file_get_contents($nightly_rerun_info_file), true);
        // $nightly_rerun_info[$gradeable_id] = !$nightly_rerun_info[$gradeable_id];
        // if (file_put_contents($nightly_rerun_info_file, json_encode($nightly_rerun_info, JSON_PRETTY_PRINT)) === false) {
        //     $this->core->addErrorMessage("Failed to change nightly rerun for the gradeable");
        //     $this->core->redirect($return_url);
        // }
        // $this->core->addSuccessMessage("Nightly Rerun status changed for the gradeable");
        // $this->core->redirect($return_url);
    }


    #[Route("/courses/{_semester}/{_course}/plagiarism/configuration/getOtherGradeables", methods: ["POST"])]
    public function getOtherGradeables(): JsonResponse {
        if (!isset($_POST['semester_course']) || !isset($_POST['this_gradeable'])) {
            return JsonResponse::getErrorResponse("Error: Unable to get other gradeables");
        }

        $tokens = explode(' ', $_POST['semester_course']);
        $semester = $tokens[0];
        $course = $tokens[1];

        try {
            $return = $this->getOtherOtherGradeables($semester, $course, $_POST['this_gradeable']);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }

        return JsonResponse::getSuccessResponse($return);
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/log")]
    public function getRunLog(string $gradeable_id, string $config_id): JsonResponse {
        try {
            $this->verifyGradeableAndConfigAreValid($gradeable_id, intval($config_id));
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }

        $log_file = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, intval($config_id)), "logs", "lichen_job_output.txt");

        if (!file_exists($log_file)) {
            return JsonResponse::getErrorResponse("Error: Unable to find run log.");
        }

        $log_data = file_get_contents($log_file);

        return JsonResponse::getSuccessResponse($log_data);
    }

    /**
     * returns info for the user versions, to be displayed in the left pane of
     * PlagiarismResult. Grabs the list of versions, the max matching version,
     * and the active version of that user.
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id_1
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/{config_id}/versionlist")]
    public function ajaxGetVersionList(string $gradeable_id, string $config_id, string $user_id_1): JsonResponse {
        // error checking
        try {
            $config_id = intval($config_id);
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }
        // check for backwards crawling
        if (str_contains($user_id_1, '..')) {
            return JsonResponse::getErrorResponse('Error: path contains invalid component ".."');
        }

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return JsonResponse::getErrorResponse('Error: unable to get gradeable "' . $gradeable_id);
        }
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id_1);
        if ($graded_gradeable === false) {
            return JsonResponse::getErrorResponse('Error: unable to get user "' . $user_id_1 . '" submission for gradeable "' . $gradeable_id . '"');
        }

        $active_version_user_1 = (string) $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();

        try {
            $rankings = $this->getOverallRankings($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse("Rankings file not found or no matches found for selected user");
        }

        $max_matching_version = 0;
        foreach ($rankings as $ranking) {
            if ($ranking[0] == $user_id_1) {
                $max_matching_version = $ranking[1];
                break;
            }
        }

        $user_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "users", $user_id_1);
        $files = scandir($user_path);
        $all_versions_user_1 = [];
        foreach ($files as $version) {
            if ($version !== "." && $version !== ".." && file_exists(FileUtils::joinPaths($user_path, $version, "ranking.txt"))) {
                $all_versions_user_1[] = $version;
            }
        }

        $data = [];
        $data["versions"] = array_values($all_versions_user_1);
        $data["max_matching"] = strval($max_matching_version);
        $data["active_version"] = strval($active_version_user_1);
        return JsonResponse::getSuccessResponse($data);
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id
     * @param string $version
     * @param string|null $source_gradeable
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/{config_id}/concat")]
    public function ajaxGetSubmissionConcatenated(string $gradeable_id, string $config_id, string $user_id, string $version, string $source_gradeable = null): JsonResponse {
        // error checking
        try {
            $version = intval($version);
            $config_id = intval($config_id);
            $this->verifyGradeableAndConfigAreValid($gradeable_id, intval($config_id));
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }
        // check for backwards crawling
        if (str_contains($user_id, '..') || ($source_gradeable !== null && str_contains($source_gradeable, '..'))) {
            return JsonResponse::getErrorResponse('Error: path contains invalid component ".."');
        }

        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        if (isset($source_gradeable) && $source_gradeable !== "{$semester}__{$course}__{$gradeable_id}") {
            $file_name = FileUtils::joinPaths($this->getOtherGradeablePath($gradeable_id, $config_id, $source_gradeable, $user_id, $version), "submission.concatenated");
        }
        else {
            $file_name = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id, $version), "submission.concatenated");
        }

        if (!file_exists($file_name)) {
            return JsonResponse::getErrorResponse("Unable to open submission.concatenated for user {$user_id}, version {$version}");
        }

        return JsonResponse::getSuccessResponse(file_get_contents($file_name));
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id_1
     * @param string $version_user_1
     * @param string|null $user_id_2
     * @param string|null $version_user_2
     * @param string|null $source_gradeable_user_2
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/{config_id}/colorinfo")]
    public function ajaxGetColorInfo(string $gradeable_id, string $config_id, string $user_id_1, string $version_user_1, string $user_id_2 = null, string $version_user_2 = null, string $source_gradeable_user_2 = null): JsonResponse {
        // error checking
        try {
            $version_user_1 = intval($version_user_1);
            $version_user_2 = intval($version_user_2);
            $config_id = intval($config_id);
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }


        // get the contents of matches.json as an array of Intervals
        $matches_file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $version_user_1), "matches.json");
        if (!file_exists($matches_file_path)) {
            return JsonResponse::getErrorResponse("Error: Unable to find matches.json for user {$user_id_1}, version {$version_user_1}");
        }
        $intervals = PlagiarismUtils::constructIntervalsForUserPair($matches_file_path, $user_id_2, intval($version_user_2), $source_gradeable_user_2);

        // get the list of tokens for user 1
        $user_1_tokens_file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $version_user_1), "tokens.json");
        if (!file_exists($user_1_tokens_file_path)) {
            return JsonResponse::getErrorResponse("Error: Unable to find tokens.json for user {$user_id_1}, version {$version_user_1}");
        }
        $tokens_user_1 = json_decode(file_get_contents($user_1_tokens_file_path), true);


        // get the list of tokens for user 2
        $tokens_user_2 = [];
        if (isset($user_id_2)) {
            $semester = $this->core->getConfig()->getTerm();
            $course = $this->core->getConfig()->getCourse();
            if (isset($source_gradeable_user_2) && $source_gradeable_user_2 !== "{$semester}__{$course}__{$gradeable_id}") {
                $user_2_tokens_file_path = FileUtils::joinPaths($this->getOtherGradeablePath($gradeable_id, $config_id, $source_gradeable_user_2, $user_id_2, $version_user_2), "tokens.json");
            }
            else {
                $user_2_tokens_file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_2, $version_user_2), "tokens.json");
            }

            if (!file_exists($user_2_tokens_file_path)) {
                return JsonResponse::getErrorResponse("Error: Unable to find tokens.json for user {$user_id_2}, version {$version_user_2}");
            }
            $tokens_user_2 = json_decode(file_get_contents($user_2_tokens_file_path), true);
        }


        // Used to prevent an out of bounds error on the tokens arrays
        $dummyToken = [
            "char" => 99999999999,
            "line" => 99999999999,
            "value" => ""
        ];
        array_push($tokens_user_1, $dummyToken);
        if (!empty($tokens_user_2)) {
            array_push($tokens_user_2, $dummyToken);
        }


        $return = [];
        foreach ($intervals as $interval) {
            $others = [];
            foreach (array_keys($interval->getOthers()) as $id_string) {
                $temp = [];
                $tokens = explode("__", $id_string, 3);
                $temp["user_id"] = $tokens[0];
                $temp["version"] = $tokens[1];
                $temp["source_gradeable"] = $tokens[2];
                $others[] = $temp;
            }

            $matching_positions = [];
            if (!empty($tokens_user_2)) {
                foreach ($interval->getMatchingPositions($user_id_2, $version_user_2, $source_gradeable_user_2) as $mp) {
                    $matching_positions[] = [
                        "start_char" => $tokens_user_2[$mp["start"] - 1]["char"],
                        "start_line" => $tokens_user_2[$mp["start"] - 1]["line"],
                        "end_char" => $tokens_user_2[$mp["end"]]["char"],
                        "end_line" => $tokens_user_2[$mp["end"]]["line"],
                    ];
                }
            }

            $return[] = [
                "start_char" => $tokens_user_1[$interval->getStart() - 1]["char"],
                "start_line" => $tokens_user_1[$interval->getStart() - 1]["line"],
                "end_char" => $tokens_user_1[$interval->getEnd()]["char"],
                "end_line" => $tokens_user_1[$interval->getEnd()]["line"],
                "type" => $interval->getType(),
                "matching_positions" => $matching_positions,
                "others" => $others
            ];
        }

        return JsonResponse::getSuccessResponse($return);
    }

    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id_1
     * @param string $version_user_1
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/{config_id}/match")]
    public function ajaxGetUser2DropdownList(string $gradeable_id, string $config_id, string $user_id_1, string $version_user_1): JsonResponse {
        // error checking
        try {
            $config_id = intval($config_id);
            $version_user_1 = intval($version_user_1);
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }
        if (str_contains($user_id_1, '..')) {
            return JsonResponse::getErrorResponse('Error: path contains invalid component ".."');
        }

        try {
            $ranking = $this->getRankingsForUser($gradeable_id, $config_id, $user_id_1, $version_user_1);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }

        // If there were no matches for this version, show nothing in the right dropdown
        if (count($ranking) === 0) {
            return JsonResponse::getSuccessResponse([]);
        }

        $is_team_assignment = $this->core->getQueries()->getGradeableConfig($gradeable_id)->isTeamAssignment();

        $user_ids_and_names = [];
        if (!$is_team_assignment) {
            $user_ids = [];
            foreach ($ranking as $item) {
                $user_ids[$item[0]] = null;
            }
            $user_ids = array_keys($user_ids);

            $user_ids_and_names = $this->core->getQueries()->getUsersByIds($user_ids);
            if ($user_ids_and_names === null) {
                return JsonResponse::getErrorResponse("Error: Unable to load right dropdown list");
            }
        }

        $return = [];
        foreach ($ranking as $item) {
            $display_name = "";
            if (!$is_team_assignment && array_key_exists($item[0], $user_ids_and_names)) {
                $display_name = "{$user_ids_and_names[$item[0]]->getDisplayedGivenName()} {$user_ids_and_names[$item[0]]->getDisplayedFamilyName()}";
            }
            $temp = [
                "percent" => $item[3],
                "user_id" => $item[0],
                "display_name" => $display_name,
                "version" => $item[1],
                "source_gradeable" => $item[2]
            ];
            array_push($return, $temp);
        }

        return JsonResponse::getSuccessResponse($return);
    }


    /**
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/plagiarism/check_refresh")]
    public function checkRefreshLichenMainPage(): JsonResponse {
        $em = $this->core->getCourseEntityManager();
        $configs = $em->getRepository(PlagiarismConfig::class)->findAll();

        $gradeables_in_progress = 0;
        foreach ($configs as $config) {
            if (file_exists($this->getQueuePath($config->getGradeableID(), $config->getConfigID())) || file_exists($this->getProcessingQueuePath($config->getGradeableID(), $config->getConfigID()))) {
                $gradeables_in_progress++;
            }
        }

        return JsonResponse::getSuccessResponse($gradeables_in_progress);
    }
}
