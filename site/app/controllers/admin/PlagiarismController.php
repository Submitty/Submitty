<?php

namespace app\controllers\admin;

use app\libraries\response\ResponseInterface;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\plagiarism\PlagiarismUtils;
use app\libraries\routers\AccessControl;
use app\libraries\routers\FeatureFlag;
use Exception;
use Symfony\Component\Routing\Annotation\Route;
use app\models\User;

/**
 * Class PlagiarismController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 * @FeatureFlag("plagiarism")
 */
class PlagiarismController extends AbstractController {
    /**
     * This function validates a given gradeable and config ID to make sure they are valid.
     * @param string $gradeable_id
     * @param string $config_id
     * @throws Exception
     */
    private function verifyGradeableAndConfigAreValid(string $gradeable_id, string $config_id): void {
        if ($gradeable_id !== "" && !$this->core->getQueries()->existsGradeable($gradeable_id)) {
            throw new Exception("Error: Invalid gradeable ID provided: {$gradeable_id}");
        }
        // check for backwards crawling
        if (str_contains($gradeable_id, '..') || str_contains($config_id, '..')) {
            throw new Exception('Error: path contains invalid component ".."');
        }
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @return string
     */
    private function getConfigDirectoryPath(string $gradeable_id, string $config_id): string {
        return FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen", $gradeable_id, $config_id);
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id
     * @param string $version
     * @return string
     */
    private function getSubmissionPath(string $gradeable_id, string $config_id, string $user_id, string $version): string {
        return FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen", $gradeable_id, $config_id, "users", $user_id, $version);
    }

    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $source_gradeable
     * @param string $user_id
     * @param string $version
     * @return string
     */
    private function getOtherGradeablePath(string $gradeable_id, string $config_id, string $source_gradeable, string $user_id, string $version): string {
        return FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen", $gradeable_id, $config_id, "other_gradeables", $source_gradeable, $user_id, $version);
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @return string
     */
    private function getQueuePath(string $gradeable_id, string $config_id): string {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $daemon_job_queue_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue");
        return FileUtils::joinPaths($daemon_job_queue_path, "lichen__{$semester}__{$course}__{$gradeable_id}__{$config_id}.json");
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @return string
     */
    private function getProcessingQueuePath(string $gradeable_id, string $config_id): string {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $daemon_job_queue_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue");
        return FileUtils::joinPaths($daemon_job_queue_path, "PROCESSING_lichen__{$semester}__{$course}__{$gradeable_id}__{$config_id}.json");
    }

    /**
     * Gets a list of courses which have the same group as the current course and are thus eligible prior terms
     * @return array
     * @throws Exception
     */
    private function getPriorSemesterCourses(): array {
        $this_semester = $this->core->getConfig()->getSemester();
        $this_course = $this->core->getConfig()->getCourse();
        $valid_courses = $this->core->getQueries()->getOtherCoursesWithSameGroup($this_semester, $this_course);
        $ret = [];
        foreach ($valid_courses as $item) {
            $ret[] = "{$item['semester']} {$item['course']}";
        }
        return $ret;
    }

    /**
     * Get a list of gradeables for the given term+course
     * @param string $term
     * @param string $course
     * @return array
     * @throws Exception
     */
    private function getOtherPriorGradeables(string $term, string $course): array {
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
            if ($gradeable !== '.' && $gradeable !== '..') {
                $gradeables[] = $gradeable;
            }
        }

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
     * @param string $config_id
     * @return array
     * @throws Exception
     */
    private function getOverallRankings(string $gradeable_id, string $config_id): array {
        $file_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "overall_ranking.txt");
        if (!file_exists($file_path)) {
            throw new Exception("Unable to read overall ranking file for gradeable {$gradeable_id} config {$config_id}");
        }

        $content = file_get_contents($file_path);
        $content = trim(str_replace(["\r", "\n"], ' ', $content));
        $rankings = preg_split('/ +/', $content);
        $rankings = array_chunk($rankings, 3);
        return $rankings;
    }

    /**
     * Returns a ranking of users by percent match with user 1 (used for determining the rightmost dropdown list)
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id_1
     * @param string $user_1_version
     * @return array
     * @throws Exception
     */
    private function getRankingsForUser(string $gradeable_id, string $config_id, string $user_id_1, string $user_1_version): array {
        $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $user_1_version), "ranking.txt");
        if (!file_exists($file_path)) {
            throw new Exception("Unable to read ranking file for {$user_id_1} version {$user_1_version} in gradeable {$gradeable_id} config {$config_id}");
        }

        $content = file_get_contents($file_path);
        $content = trim(str_replace(["\r", "\n"], ' ', $content));
        $rankings = preg_split('/ +/', $content);
        $rankings = array_chunk($rankings, 4);
        return $rankings;
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     */
    private function deleteExistingProvidedCode(string $gradeable_id, string $config_id): void {
        $provided_code_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "provided_code", "files");
        if (is_dir($provided_code_path)) {
            FileUtils::emptyDir($provided_code_path);
        }
    }

    /**
     * @param string $temporary_file_path
     * @param string $filename
     * @param string $gradeable_id
     * @param string $config_id
     * @throws Exception
     */
    private function saveNewProvidedCode(string $temporary_file_path, string $filename, string $gradeable_id, string $config_id): void {
        // NOTE: The user of this function is expected to call deleteExistingProvidedCode()
        //       before this function if they wish to clear whatever is already in the directory first.

        if (!file_exists($temporary_file_path)) {
            throw new Exception("Upload failed: Temporary file not found");
        }

        $target_dir = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "provided_code", "files");
        FileUtils::createDir($target_dir, "true", 0770); // creates dir if not yet exists

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
     * @Route("/courses/{_semester}/{_course}/plagiarism")
     * @param string $refresh_page
     * @return WebResponse
     */
    public function plagiarismMainPage(string $refresh_page = "NO_REFRESH"): WebResponse {
        $course_path = $this->core->getConfig()->getCoursePath();
        $all_configurations = [];

        $gradeables_with_plagiarism_result = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        foreach ($gradeables_with_plagiarism_result as $i => $gradeable_id_title) {
            if (is_dir(FileUtils::joinPaths($course_path, "lichen", $gradeable_id_title['g_id']))) {
                foreach (scandir(FileUtils::joinPaths($course_path, "lichen", $gradeable_id_title['g_id'])) as $config_id) {
                    if ($config_id !== '.' && $config_id !== '..' && file_exists(FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id_title['g_id'], $config_id), "config.json"))) {
                        $configuration = [];
                        $configuration["g_id"] = $gradeable_id_title["g_id"];
                        $configuration["g_title"] = $gradeable_id_title["g_title"];
                        $configuration["g_grade_due_date"] = $this->core->getQueries()->getDateForGradeableById($gradeable_id_title["g_id"]);
                        $configuration["g_config_version"] = $config_id;

                        $all_configurations[] = $configuration;
                    }
                }
            }
        }

        usort($all_configurations, function ($a, $b) {
            return $a['g_grade_due_date'] > $b['g_grade_due_date'];
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
        $nightly_rerun_info = []; // placeholder

        return new WebResponse(
            ['admin', 'Plagiarism'],
            'plagiarismMainPage',
            $all_configurations,
            $refresh_page,
            $nightly_rerun_info
        );
    }

    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @return ResponseInterface
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}")
     */
    public function showPlagiarismResult(string $gradeable_id, string $config_id): ResponseInterface {
        $error_return_url = $this->core->buildCourseUrl(['plagiarism']);

        $gradeable_config = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        $gradeable_title = $gradeable_config->getTitle();

        try {
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage($e);
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
        $rankings = [];
        foreach ($rankings_data as $item) {
            $display_name = "";
            if (!$is_team_assignment) {
                $display_name = "{$this->core->getQueries()->getUserById($item[1])->getDisplayedFirstName()} {$this->core->getQueries()->getUserById($item[1])->getDisplayedLastName()}";
            }
            $temp = [
                "percent" => $item[0],
                "user_id" => $item[1],
                "display_name" => $display_name,
                "version" => $item[2],
            ];
            array_push($rankings, $temp);
        }


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
     * @Route("/courses/{_semester}/{_course}/plagiarism/configuration/new", methods={"POST"})
     */
    public function saveNewPlagiarismConfiguration(string $new_or_edit, string $gradeable_id, string $config_id): RedirectResponse {
        $course_path = $this->core->getConfig()->getCoursePath();
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        // Determine whether this is a new config or an existing config
        $return_url = $this->core->buildCourseUrl(['plagiarism', 'configuration', 'new']);
        if ($new_or_edit === "new") {
            $gradeable_id = $_POST["gradeable_id"];
        }
        elseif ($new_or_edit === "edit") {
            $return_url = $this->core->buildCourseUrl(['plagiarism', 'configuration', 'edit']) . "?gradeable_id={$gradeable_id}&config_id={$config_id}";
        }

        // Check for invalid gradeable/config IDs
        try {
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($return_url);
        }


        if ($config_id !== null) {
            // Check if Lichen job is already running
            if (file_exists($this->getQueuePath($gradeable_id, $config_id)) || file_exists($this->getProcessingQueuePath($gradeable_id, $config_id))) {
                $this->core->addErrorMessage("A job is already running for the gradeable. Try again after a while.");
                return new RedirectResponse($return_url);
            }
        }


        // Version /////////////////////////////////////////////////////////////
        $version_option = $_POST['version_option'];
        assert($version_option == "active_version" || $version_option == "all_versions");


        // Regex ///////////////////////////////////////////////////////////////
        // TODO: Can we find a way to validate the regex more thoroughly to tell the user their regex was invalid before feeding it to Lichen?
        if (!isset($_POST["regex_dir"]) || !isset($_POST["regex_to_select_files"]) || str_contains($_POST["regex_to_select_files"], "..")) {
            $this->core->addErrorMessage("Invalid regex form data.");
            return new RedirectResponse($return_url);
        }
        $regex_directories = $_POST["regex_dir"];
        $regex_for_selecting_files = $_POST['regex_to_select_files'];


        // Language ////////////////////////////////////////////////////////////
        $language = $_POST['language'] ?? '';
        if (!in_array($language, PlagiarismUtils::getSupportedLanguages())) {
            $this->core->addErrorMessage("Invalid selected language");
            return new RedirectResponse($return_url);
        }


        // Common code threshold ///////////////////////////////////////////////
        $threshold = (int) $_POST['threshold'] ?? 0;
        if ($threshold < 2) {
            $this->core->addErrorMessage("Invalid input provided for threshold");
            return new RedirectResponse($return_url);
        }


        // Sequence length /////////////////////////////////////////////////////
        $sequence_length = (int) $_POST['sequence_length'] ?? 0;
        if ($sequence_length < 1) {
            $this->core->addErrorMessage("Invalid input provided for sequence length");
            return new RedirectResponse($return_url);
        }


        // Prior terms /////////////////////////////////////////////////////////
        $prev_term_gradeables = [];
        if ($_POST["past_terms_option"] === "past_terms") {
            if (isset($_POST["prior_semester_course"]) !== isset($_POST["prior_gradeable"])) {
                $this->core->addErrorMessage("Invalid input provided for prior term gradeables");
                $this->core->redirect($return_url);
            }
            foreach ($_POST["prior_semester_course"] as $index => $sem_course) {
                if (!isset($_POST["prior_gradeable"][$index])) {
                    $this->core->addErrorMessage("Invalid input provided for prior term gradeables");
                    $this->core->redirect($return_url);
                }
                else {
                    $tokens = explode(" ", $sem_course);
                    if (count($tokens) !== 2) {
                        $this->core->addErrorMessage("Invalid input provided for prior semester and course");
                        $this->core->redirect($return_url);
                    }
                    $prior_semester = $tokens[0];
                    $prior_course = $tokens[1];
                    $prior_gradeable = $_POST["prior_gradeable"][$index];
                    // Error checking
                    if (str_contains($prior_semester, '..') || str_contains($prior_course, '..') || str_contains($prior_gradeable, '..')) {
                        $this->core->addErrorMessage("Error: prior term gradeables string contains invalid component '..'");
                        return new RedirectResponse($return_url);
                    }
                    $prior_g_submissions_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "courses", $prior_semester, $prior_course, "submissions", $prior_gradeable);
                    if (!is_dir($prior_g_submissions_path) || count(scandir($prior_g_submissions_path)) === 2) {
                        $this->core->addErrorMessage("Error: submssions to prior term gradeable provided not found");
                        return new RedirectResponse($return_url);
                    }
                    $prev_term_gradeables[] = [
                        "prior_semester" => $prior_semester,
                        "prior_course" => $prior_course,
                        "prior_gradeable" => $prior_gradeable
                    ];
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


        // Generate a unique number for this version of the gradeable //////////
        if ($new_or_edit === "new") {
            $config_id = 1;
            if (is_dir(FileUtils::joinPaths($course_path, "lichen", $gradeable_id))) {
                foreach (scandir(FileUtils::joinPaths($course_path, "lichen", $gradeable_id)) as $file) {
                    if ($file !== '.' && $file !== '..' && is_numeric($file) && intval($file) >= $config_id) {
                        $config_id = intval($file) + 1;
                    }
                }
            }
            $config_id = strval($config_id);
        }


        // Create directory structure //////////////////////////////////////////
        if (!is_dir($this->getConfigDirectoryPath($gradeable_id, $config_id))) {
            FileUtils::createDir($this->getConfigDirectoryPath($gradeable_id, $config_id), "true", 0770);
        }

        // Upload instructor provided code /////////////////////////////////////
        if ($new_or_edit === "edit" && ($_POST['provided_code_option'] !== "code_provided" || $_FILES['provided_code_file']['tmp_name'] !== "")) {
            // delete the old provided code
            $this->deleteExistingProvidedCode($gradeable_id, $config_id);
        }
        if ($_POST['provided_code_option'] === "code_provided" && $_FILES['provided_code_file']['tmp_name'] !== "") {
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


        // Save the config.json ////////////////////////////////////////////////
        $json_file = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "config.json");
        $json_data = [
            "semester" => $semester,
            "course" => $course,
            "gradeable" => $gradeable_id,
            "config_id" => $config_id,
            "version" => $version_option,
            "regex" => $regex_for_selecting_files,
            "regex_dirs" => $regex_directories,
            "language" => $language,
            "threshold" => $threshold,
            "sequence_length" => $sequence_length,
            "prior_term_gradeables" => $prev_term_gradeables,
            "ignore_submissions" => $ignore_submission_option
        ];

        if (!@file_put_contents($json_file, json_encode($json_data, JSON_PRETTY_PRINT))) {
            $this->core->addErrorMessage("Failed to create configuration. Create the configuration again.");
            return new RedirectResponse($return_url);
        }

        // Create the Lichen job ///////////////////////////////////////////////
        try {
            $this->enqueueLichenJob("RunLichen", $gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage("Failed to add configuration to Lichen queue. Create the configuration again.");
            return new RedirectResponse($return_url);
        }

        $this->core->addSuccessMessage("Lichen Plagiarism Detection configuration created for {$gradeable_id} configuration #{$config_id}");
        return new RedirectResponse($this->core->buildCourseUrl(['plagiarism']) . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }


    /**
     * @param string $job
     * @param string $gradeable_id
     * @param string $config_id
     * @throws Exception
     */
    private function enqueueLichenJob(string $job, string $gradeable_id, string $config_id): void {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $lichen_job_data = [
            "job" => $job,
            "semester" => $semester,
            "course" => $course,
            "gradeable" => $gradeable_id,
            "config_id" => $config_id
        ];

        // This will throw an exception if the gradeable/config is not valid and the exception should trickle up to the function which called this function.
        $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);

        $lichen_job_file = $this->getQueuePath($gradeable_id, $config_id);

        if (file_exists($lichen_job_file) && !is_writable($lichen_job_file)) {
            throw new Exception("Error: Failed to create lichen job. Try again");
        }

        if (file_put_contents($lichen_job_file, json_encode($lichen_job_data, JSON_PRETTY_PRINT)) === false) {
            throw new Exception("Error: Failed to write lichen job file. Try again");
        }
    }


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/rerun")
     * @param string $gradeable_id
     * @param string $config_id
     * @return RedirectResponse
     */
    public function reRunPlagiarism(string $gradeable_id, string $config_id): RedirectResponse {
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        try {
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

        $json_config_file = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "config.json");

        if (!file_exists($json_config_file)) {
            $this->core->addErrorMessage("Plagiarism results have been deleted. Add new configuration for the gradeable.");
            return new RedirectResponse($return_url);
        }

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
     * @Route("/courses/{_semester}/{_course}/plagiarism/configuration/new", methods={"GET"})
     * @return WebResponse
     */
    public function configurePlagiarismForm(): WebResponse {
        $gradeable_with_submission = array_diff(scandir(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions/")), ['.', '..']);
        $gradeable_ids_titles = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        foreach ($gradeable_ids_titles as $i => $gradeable_id_title) {
            if (!in_array($gradeable_id_title['g_id'], $gradeable_with_submission)) {
                unset($gradeable_ids_titles[$i]);
                continue;
            }
            $duedate = $this->core->getQueries()->getDateForGradeableById($gradeable_id_title['g_id']);
            $gradeable_ids_titles[$i]['g_grade_due_date'] = $duedate->format('F d Y H:i:s');
        }
        usort($gradeable_ids_titles, function ($a, $b) {
            return $a['g_grade_due_date'] > $b['g_grade_due_date'];
        });

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
        $config["language"] = array_fill_keys(PlagiarismUtils::getSupportedLanguages(), "");
        $config["language"]["plaintext"] = "selected";
        $config["threshold"] = 5;
        $config["sequence_length"] = 4;
        $config["prior_terms"] = false;
        $config["prior_semester_courses"] = $this->getPriorSemesterCourses();
        $config["prior_term_gradeables"] = [];
        $config["ignore_submissions"] = [];
        $config["ignore_submissions_list"] = "";

        return new WebResponse(
            ['admin', 'Plagiarism'],
            'configurePlagiarismForm',
            'new',
            $config
        );
    }


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/configuration/edit")
     * @param string $gradeable_id
     * @param string $config_id
     * @return ResponseInterface
     */
    public function editPlagiarismSavedConfig(string $gradeable_id, string $config_id): ResponseInterface {
        $config_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "config.json");
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        // Error checking
        try {
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage($e);
            return new RedirectResponse($return_url);
        }
        if (!file_exists($config_path)) {
            $this->core->addErrorMessage("Saved configuration not found.");
            return new RedirectResponse($return_url);
        }

        // get the config from the config file
        $saved_config = json_decode(file_get_contents($config_path), true);
        $title = "";
        if (isset($saved_config['gradeable']) && $saved_config['gradeable'] !== null) {
            $title = $this->core->getQueries()->getGradeableConfig($saved_config['gradeable'])->getTitle();
        }

        // check to see if there are any provided code files
        $has_provided_code = false;
        $provided_code_filenames = [];
        if (is_dir(FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "provided_code", "files"))) {
            $provided_code_filename_array = array_diff(scandir(FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "provided_code", "files")), [".", ".."]);
            $has_provided_code = count($provided_code_filename_array) > 0;
            foreach ($provided_code_filename_array as $filename) {
                $provided_code_filenames[] = $filename;
            }
        }
        $ignore = $this->getIgnoreSubmissionType($saved_config['ignore_submissions']);
        $prior_term_gradeables_array = $saved_config['prior_term_gradeables'];
        foreach ($prior_term_gradeables_array as &$gradeable) {
            try {
                $gradeable["other_gradeables"] = $this->getOtherPriorGradeables($gradeable["prior_semester"], $gradeable["prior_course"]);
            }
            catch (Exception $e) {
                $this->core->addErrorMessage($e->getMessage());
                return new RedirectResponse($return_url);
            }
        }
        $config = [];

        $config["gradeable_id"] = $saved_config['gradeable'];
        $config["config_id"] = $saved_config['config_id'];
        $config["title"] = $title;
        $config["gradeable_ids_titles"] = [];
        $config["provided_code"] = $has_provided_code;
        $config["provided_code_filenames"] = $provided_code_filenames;
        $config["version"] = $saved_config['version'];
        $config["regex"] = $saved_config['regex'];
        $config["regex_dirs"] = $saved_config['regex_dirs'];
        $config["language"] = array_fill_keys(PlagiarismUtils::getSupportedLanguages(), "");
        $config["language"][$saved_config['language']] = "selected";
        $config["threshold"] = (int) $saved_config['threshold'];
        $config["sequence_length"] = (int) $saved_config['sequence_length'];
        $config["prior_terms"] = count($saved_config['prior_term_gradeables']) > 0;
        $config["prior_semester_courses"] = $this->getPriorSemesterCourses();
        $config["prior_term_gradeables"] = $prior_term_gradeables_array;
        $config["ignore_submissions"] = $ignore[0];
        $config["ignore_submissions_list"] = implode(", ", $ignore[1]);

        return new WebResponse(
            ['admin', 'Plagiarism'],
            'configurePlagiarismForm',
            'edit',
            $config
        );
    }


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/delete", methods={"POST"})
     * @param string $gradeable_id
     * @param string $config_id
     * @return RedirectResponse
     */
    public function deletePlagiarismResultAndConfig(string $gradeable_id, string $config_id): RedirectResponse {
        $return_url = $this->core->buildCourseUrl(['plagiarism']);
        $config_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "config.json");

        try {
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

        if (!file_exists($config_path)) {
            $this->core->addErrorMessage("Plagiarism results for the configuration are already deleted. Refresh the page.");
            return new RedirectResponse($return_url);
        }

        try {
            $this->enqueueLichenJob("DeleteLichenResult", $gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($return_url);
        }

        $this->core->addSuccessMessage("Lichen results and saved configuration will be deleted.");
        return new RedirectResponse($this->core->buildCourseUrl(['plagiarism']) . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/nightly_rerun")
     * @param string $gradeable_id
     * @param string $config_id
     */
    public function toggleNightlyRerun(string $gradeable_id, string $config_id) {
        // $semester = $this->core->getConfig()->getSemester();
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


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/configuration/getPriorGradeables", methods={"POST"})
     */
    public function getPriorGradeables(): JsonResponse {
        $tokens = explode(' ', $_POST["semester_course"]);
        $semester = $tokens[0];
        $course = $tokens[1];

        try {
            $return = $this->getOtherPriorGradeables($semester, $course);
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
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/log")
     */
    public function getRunLog(string $gradeable_id, string $config_id): JsonResponse {
        try {
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }

        $log_file = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "logs", "lichen_job_output.txt");

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
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/{config_id}/versionlist")
     */
    public function ajaxGetVersionList(string $gradeable_id, string $config_id, string $user_id_1): JsonResponse {
        // error checking
        try {
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
            if ($ranking[1] == $user_id_1) {
                $max_matching_version = $ranking[2];
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
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/{config_id}/concat")
     */
    public function ajaxGetSubmissionConcatenated(string $gradeable_id, string $config_id, string $user_id, string $version, string $source_gradeable = null): JsonResponse {
        // error checking
        try {
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }
        // check for backwards crawling
        if (str_contains($user_id, '..') || str_contains($version, '..') || ($source_gradeable !== null && str_contains($source_gradeable, '..'))) {
            return JsonResponse::getErrorResponse('Error: path contains invalid component ".."');
        }

        $semester = $this->core->getConfig()->getSemester();
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
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/{config_id}/colorinfo")
     */
    public function ajaxGetColorInfo(string $gradeable_id, string $config_id, string $user_id_1, string $version_user_1, string $user_id_2 = null, string $version_user_2 = null, string $source_gradeable_user_2 = null): JsonResponse {
        // error checking
        try {
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
            $semester = $this->core->getConfig()->getSemester();
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
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/{config_id}/match")
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id_1
     * @param string $version_user_1
     * @return JsonResponse
     */
    public function ajaxGetUser2DropdownList(string $gradeable_id, string $config_id, string $user_id_1, string $version_user_1): JsonResponse {
        // error checking
        try {
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }
        if (str_contains($user_id_1, '..') || str_contains($version_user_1, '..')) {
            return JsonResponse::getErrorResponse('Error: path contains invalid component ".."');
        }

        try {
            $ranking = $this->getRankingsForUser($gradeable_id, $config_id, $user_id_1, $version_user_1);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }

        $return = [];
        $is_team_assignment = $this->core->getQueries()->getGradeableConfig($gradeable_id)->isTeamAssignment();
        foreach ($ranking as $item) {
            $display_name = "";
            if (!$is_team_assignment) {
                $display_name = "{$this->core->getQueries()->getUserById($item[1])->getDisplayedFirstName()} {$this->core->getQueries()->getUserById($item[1])->getDisplayedLastName()}";
            }
            $temp = [
                "percent" => $item[0],
                "user_id" => $item[1],
                "display_name" => $display_name,
                "version" => $item[2],
                "source_gradeable" => $item[3]
            ];
            array_push($return, $temp);
        }

        return JsonResponse::getSuccessResponse($return);
    }


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/check_refresh")
     * @return JsonResponse
     */
    public function checkRefreshLichenMainPage(): JsonResponse {
        $gradeables_in_progress = 0;
        $gradeables_with_plagiarism_result = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        foreach ($gradeables_with_plagiarism_result as $i => $gradeable_id_title) {
            if (is_dir(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen", $gradeable_id_title['g_id']))) {
                foreach (scandir(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen", $gradeable_id_title['g_id'])) as $config_id) {
                    if ($config_id !== '.' && $config_id !== '..' && file_exists(FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id_title['g_id'], $config_id), "config.json"))) {
                        if (
                            file_exists($this->getQueuePath($gradeable_id_title['g_id'], $config_id))
                            || file_exists($this->getProcessingQueuePath($gradeable_id_title['g_id'], $config_id))
                        ) {
                            $gradeables_in_progress++;
                        }
                    }
                }
            }
        }

        return JsonResponse::getSuccessResponse($gradeables_in_progress);
    }
}
