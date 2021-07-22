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


    private function getGradeablesFromPriorTerm() {
        // TODO: Implement.
        return [];
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
        // TODO: temporary fix, should make a proper PR overhauling all of plagiarism.js to fix this.
        if ($user_1_version === "max_matching") {
            $max_percent = 0;
            foreach (scandir(FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "users", $user_id_1)) as $version) {
                $file_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "users", $user_id_1, $version, "ranking.txt");
                if ($version !== "." && $version !== ".." && file_exists($file_path)) {
                    $content = file_get_contents($file_path);
                    $content = trim(str_replace(["\r", "\n"], ' ', $content));
                    $rankings = preg_split('/ +/', $content);
                    $rankings = array_chunk($rankings, 3);

                    if ($rankings[0][0] > $max_percent) {
                        $user_1_version = $version;
                        $max_percent = $rankings[0][0];
                    }
                }
            }
        }

        $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $user_1_version), "ranking.txt");
        if (!file_exists($file_path)) {
            throw new Exception("Unable to read ranking file for {$user_id_1} version {$user_1_version} in gradeable {$gradeable_id} config {$config_id}");
        }

        $content = file_get_contents($file_path);
        $content = trim(str_replace(["\r", "\n"], ' ', $content));
        $rankings = preg_split('/ +/', $content);
        $rankings = array_chunk($rankings, 3);
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
     * @return WebResponse|RedirectResponse
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
            $rankings = $this->getOverallRankings($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            $this->core->addErrorMessage("Plagiarism Detection job is already running for this gradeable.");
            return new RedirectResponse($error_return_url);
        }

        if (count($rankings) === 0) {
            $this->core->addSuccessMessage("There are no matches (plagiarism) for the gradeable with current configuration");
        }

        foreach ($rankings as $i => $ranking) {
            if (!$gradeable_config->isTeamAssignment()) {
                array_push($rankings[$i], $this->core->getQueries()->getUserById($ranking[1])->getDisplayedFirstName());
                array_push($rankings[$i], $this->core->getQueries()->getUserById($ranking[1])->getDisplayedLastName());
            }
            else {
                array_push($rankings[$i], "");
                array_push($rankings[$i], "");
            }
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
        /*
        TODO: fix? prior_term_gradeables_number is commented out on the frontend so $_POST['prior_term_gradeables_number'] is always unset / 0
        See #6650 for where this variable was commented out

        $prev_gradeable_number = $_POST['prior_term_gradeables_number'];
        for ($i = 0; $i < $prev_gradeable_number; $i++) {
            if ($_POST['prev_sem_' . $i] != "" && $_POST['prev_course_' . $i] != "" && $_POST['prev_gradeable_' . $i] != "") {
                array_push($prev_term_gradeables, FileUtils::joinPaths($course_path, $_POST['prev_sem_' . $i], $_POST['prev_course_' . $i], "submissions", $_POST['prev_gradeable_' . $i]));
            }
        }
        */


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
            "prev_term_gradeables" => $prev_term_gradeables,
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

        $prior_term_gradeables = $this->getGradeablesFromPriorTerm();

        return new WebResponse(
            ['admin', 'Plagiarism'],
            'configurePlagiarismForm',
            'new',
            $gradeable_ids_titles,
            $prior_term_gradeables,
            null,
            null,
            null,
            null
        );
    }


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/configuration/edit")
     * @param string $gradeable_id
     * @param string $config_id
     * @return WebResponse|RedirectResponse
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

        $saved_config = json_decode(file_get_contents($config_path), true);
        $title = "";
        if (isset($saved_config['gradeable']) && $saved_config['gradeable'] !== null) {
            $title = $this->core->getQueries()->getGradeableConfig($saved_config['gradeable'])->getTitle();
        }

        $prior_term_gradeables = $this->getGradeablesFromPriorTerm();
        $ignore_submissions = $this->getIgnoreSubmissionType($saved_config['ignore_submissions']);

        return new WebResponse(
            ['admin', 'Plagiarism'],
            'configurePlagiarismForm',
            'edit',
            null,
            $prior_term_gradeables,
            $ignore_submissions[0],
            $ignore_submissions[1],
            $saved_config,
            $title
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
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id_1
     * @param string $version_user_1
     * @param string|null $user_id_2
     * @param string|null $version_user_2
     * @return JsonResponse
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/concat")
     */
    public function ajaxGetSubmissionConcatenated(string $gradeable_id, string $config_id, string $user_id_1, string $version_user_1, string $user_id_2 = null, string $version_user_2 = null): JsonResponse {
        // error checking
        try {
            $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse($e->getMessage());
        }
        // check for backwards crawling
        if (str_contains($user_id_1, '..') || str_contains($version_user_1, '..') || ($user_id_2 !== null && str_contains($user_id_2, '..')) || ($version_user_2 !== null && str_contains($version_user_2, '..'))) {
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

        $active_version_user_1 =  (string) $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();

        try {
            $rankings = $this->getOverallRankings($gradeable_id, $config_id);
        }
        catch (Exception $e) {
            return JsonResponse::getErrorResponse("Rankings file not found or no matches found for selected user");
        }

        $max_matching_version = 1;
        foreach ($rankings as $ranking) {
            if ($ranking[1] == $user_id_1) {
                $max_matching_version = $ranking[2];
            }
        }
        if ($version_user_1 == "max_matching" || $version_user_1 == "") {
            $version_user_1 = $max_matching_version;
        }
        $all_versions_user_1 = array_diff(scandir(FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "users", $user_id_1)), [".", ".."]);

        $file_name = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $version_user_1), "submission.concatenated");
        if (file_exists($file_name)) {
            if (isset($user_id_2) && !empty($user_id_2) && isset($version_user_2) && !empty($version_user_2)) {
                try {
                    $color_info = $this->getColorInfo($gradeable_id, $config_id, $user_id_1, $version_user_1, $user_id_2, $version_user_2, '1');
                }
                catch (Exception $e) {
                    return JsonResponse::getErrorResponse($e->getMessage());
                }
            }
            else {
                try {
                    $color_info = $this->getColorInfo($gradeable_id, $config_id, $user_id_1, $version_user_1, '', '', '1');
                }
                catch (Exception $e) {
                    return JsonResponse::getErrorResponse($e->getMessage());
                }
            }
            $data = [
                'display_code1' => file_get_contents($file_name),
                'code_version_user_1' => $version_user_1,
                'max_matching_version' => $max_matching_version,
                'active_version_user_1' => $active_version_user_1,
                'all_versions_user_1' => $all_versions_user_1,
                'ci' => $color_info
            ];
        }
        else {
            return JsonResponse::getErrorResponse("User 1 submission.concatenated for specified version not found.");
        }

        if (isset($user_id_2) && !empty($user_id_2) && isset($version_user_2) && !empty($version_user_2)) {
            $file_name = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_2, $version_user_2), "submission.concatenated");

            if (($this->core->getUser()->accessAdmin()) && (file_exists($file_name))) {
                try {
                    $color_info = $this->getColorInfo($gradeable_id, $config_id, $user_id_1, $version_user_1, $user_id_2, $version_user_2, '2');
                }
                catch (Exception $e) {
                    return JsonResponse::getErrorResponse($e->getMessage());
                }
                $data['display_code2'] = file_get_contents($file_name);
            }
            else {
                return JsonResponse::getErrorResponse("User 2 submission.concatenated for specified version not found.");
            }
        }

        $data['ci'] = $color_info[0];
        $data['si'] = $color_info[1];
        return JsonResponse::getSuccessResponse($data);
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id_1
     * @param string $version_user_1
     * @param string $user_id_2
     * @param string $version_user_2
     * @param string $codebox
     * @return array
     * @throws Exception
     */
    private function getColorInfo(string $gradeable_id, string $config_id, string $user_id_1, string $version_user_1, string $user_id_2, string $version_user_2, string $codebox): array {
        // error checking
        $this->verifyGradeableAndConfigAreValid($gradeable_id, $config_id);

        $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $version_user_1), "matches.json");
        if (!file_exists($file_path)) {
            throw new Exception("Error: Unable to find matches.json");
        }

        // check for backwards crawling
        if (str_contains($user_id_1, '..') || str_contains($version_user_1, '..') || str_contains($user_id_2, '..') || str_contains($version_user_2, '..')) {
            throw new Exception('Error: path contains invalid component ".."');
        }


        $color_info = [];
        //Represents left and right display users
        $color_info[1] = [];
        $segment_info = [];


        // Used to prevent an out of bounds error on the tokens arrays
        $dummyToken = [];
        $dummyToken["char"] = 99999999999; // set it to a big number of negligible significance

        $matches = PlagiarismUtils::constructIntervalsForUserPair($file_path, $user_id_2, intval($version_user_2));

        $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $version_user_1), "tokens.json");
        if (!file_exists($file_path)) {
            throw new Exception("Error: Unable to find tokens.json for user 1");
        }
        $tokens_user_1 = json_decode(file_get_contents($file_path), true);

        $tokens_user_2 = [];
        if ($user_id_2 != "") {
            $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_2, $version_user_2), "tokens.json");
            if (!file_exists($file_path)) {
                throw new Exception("Error: Unable to find tokens.json for user 2");
            }
            $tokens_user_2 = json_decode(file_get_contents($file_path), true);
            array_push($tokens_user_2, $dummyToken);
        }

        array_push($tokens_user_1, $dummyToken);

        $i = 0;
        foreach ($matches as $match) {
            // count the number of tokens iterated through
            $i++;

            $s_pos = $match->getStart();
            $e_pos = $match->getEnd();

            $next_start = 99999999999;
            if ($i < count($matches)) {
                next($matches);
                $next_start = current($matches)->getStart();
            }

            $start_pos = $tokens_user_1[$s_pos - 1]["char"] - 1;
            $start_line = $tokens_user_1[$s_pos - 1]["line"] - 1;

            if ($e_pos > $next_start) {
                $e_pos = $next_start - 1;
            }
            $end_pos = $tokens_user_1[$e_pos]["char"] - 1;
            $end_line = $tokens_user_1[$e_pos - 1]["line"] - 1;

            $userMatchesStarts = [];
            $userMatchesEnds = [];

            $color = ""; // placeholder

            if ($match->getType() === "match") {
                //Color is yellow -- matches other students but not general match between students...
                $color = '#ffff00';

                $others = array_keys($match->getOthers());
                $segment_info["{$start_line}_{$start_pos}"] = $others;
            }
            elseif ($match->getType() === "specific-match") {
                //Color is orange -- general match from selected match
                $color = '#ffa500';

                if ($codebox == "2" && $user_id_2 != "") {
                    foreach ($match->getMatchingPositions($user_id_2, $version_user_2) as $pos) {
                        $matchPosStart = $pos['start'];
                        $matchPosEnd =  $pos['end'];
                        $start_pos_2 = $tokens_user_2[$matchPosStart - 1]["char"] - 1;
                        $start_line_2 = $tokens_user_2[$matchPosStart - 1]["line"] - 1;
                        $end_pos_2 = $tokens_user_2[$matchPosEnd]["char"] - 1;
                        $end_line_2 = $tokens_user_2[$matchPosEnd - 1]["line"] - 1;

                        $color_info[2][] = [$start_pos_2, $start_line_2, $end_pos_2, $end_line_2, $color, $matchPosStart, $matchPosEnd];
                        $userMatchesStarts[] = $matchPosStart;
                        $userMatchesEnds[] = $matchPosEnd;
                    }
                }

                $others = array_keys($match->getOthers());
                $segment_info["{$start_line}_{$start_pos}"] = $others;
            }
            elseif ($match->getType() === "common") { // common code does not show up on user 2
                //Color is grey -- common matches among all students
                $color = '#cccccc';
            }
            elseif ($match->getType() === "provided") { // provided code does not show up on user 2
                //Color is green -- instructor provided code #b5e3b5
                $color = '#b5e3b5';
            }

            array_push($color_info[1], [$start_pos, $start_line, $end_pos, $end_line, $color, $userMatchesStarts, $userMatchesEnds]);
        }
        return [$color_info, $segment_info];
    }

    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/match")
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id_1
     * @param string $version_user_1
     * @return JsonResponse
     */
    public function ajaxGetMatchingUsers(string $gradeable_id, string $config_id, string $user_id_1, string $version_user_1): JsonResponse {
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
        foreach ($ranking as $item) {
            $temp = [];
            array_push($temp, $item[1]);
            array_push($temp, $item[2]);
            if (!$this->core->getQueries()->getGradeableConfig($gradeable_id)->isTeamAssignment()) {
                array_push($temp, $this->core->getQueries()->getUserById($item[1])->getDisplayedFirstName());
                array_push($temp, $this->core->getQueries()->getUserById($item[1])->getDisplayedLastName());
            }
            else {
                array_push($temp, "");
                array_push($temp, "");
            }
            array_push($temp, $item[0]);
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
