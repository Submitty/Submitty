<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\DateUtils;
use app\libraries\plagiarism\Interval;
use app\libraries\plagiarism\PlagiarismUtils;
use app\libraries\routers\AccessControl;
use app\libraries\routers\FeatureFlag;
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
     * @return array|null
     */
    private function getOverallRankings(string $gradeable_id, string $config_id): ?array {
        $file_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "overall_ranking.txt");
        if (!file_exists($file_path)) {
            return null;
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
     * @return array|null
     */
    private function getRankingsForUser(string $gradeable_id, string $config_id, string $user_id_1, string $user_1_version): ?array {
        $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $user_1_version), "ranking.txt");
        if (!file_exists($file_path)) {
            return null;
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
     * @throws \Exception
     */
    private function saveNewProvidedCode(string $temporary_file_path, string $filename, string $gradeable_id, string $config_id): void {
        // NOTE: The user of this function is expected to call deleteExistingProvidedCode()
        //       before this function if they wish to clear whatever is already in the directory first.

        if (!file_exists($temporary_file_path)) {
            throw new \Exception("Upload failed: Temporary file not found");
        }

        $target_dir = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "provided_code", "files");
        FileUtils::createDir($target_dir); // creates dir if not yet exists

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
                throw new \Exception("Upload failed: {$error_message}");
            }
        }
        else {
            if (!@move_uploaded_file($temporary_file_path, FileUtils::joinPaths($target_dir, $filename))) {
                FileUtils::recursiveRmdir($target_dir);
                throw new \Exception("Upload failed: Could not copy file");
            }
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism")
     */
    public function plagiarismMainPage($refresh_page="NO_REFRESH") {
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

        $this->core->getOutput()->renderOutput(['admin', 'Plagiarism'], 'plagiarismMainPage', $all_configurations, $refresh_page, $nightly_rerun_info);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}?configuration={config_id}")
     */
    public function showPlagiarismResult(string $gradeable_id, string $config_id) {
        $gradeable_config = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        $gradeable_title = $gradeable_config->getTitle();

        $rankings = $this->getOverallRankings($gradeable_id, $config_id);
        if ($rankings === null) {
            // This should theoretically never happen from the UI but we check it anyway.
            $this->core->addErrorMessage("Plagiarism Detection job is running for this gradeable.");
            $this->core->redirect($this->core->buildCourseUrl(['plagiarism']));
        }
        elseif (count($rankings) === 0) {
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

        $this->core->getOutput()->renderOutput(['admin', 'Plagiarism'], 'showPlagiarismResult', $gradeable_id, $config_id, $gradeable_title, $rankings);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/configuration/new", methods={"POST"})
     */
    public function saveNewPlagiarismConfiguration(string $new_or_edit, string $gradeable_id = null, string $config_id = null) {
        $course_path = $this->core->getConfig()->getCoursePath();
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        // Determine whether this is a new config or an existing config
        $return_url = $this->core->buildCourseUrl(['plagiarism', 'configuration', 'new']);
        if ($new_or_edit == "new") {
            $gradeable_id = $_POST['gradeable_id'];
        }
        elseif ($new_or_edit == "edit") {
            $return_url = $this->core->buildCourseUrl(['plagiarism', 'configuration', 'edit']) . '?' . http_build_query(['gradeable_id' => $gradeable_id]);
        }


        if ($config_id !== null) {
            // Check if Lichen job is already running
            if (file_exists($this->getQueuePath($gradeable_id, $config_id)) || file_exists($this->getProcessingQueuePath($gradeable_id, $config_id))) {
                $this->core->addErrorMessage("A job is already running for the gradeable. Try again after a while.");
                $this->core->redirect($return_url);
            }
        }


        // Version /////////////////////////////////////////////////////////////
        $version_option = $_POST['version_option'];
        assert($version_option == "active_version" || $version_option == "all_versions");


        // Regex ///////////////////////////////////////////////////////////////
        // TODO: Can we find a way to validate the regex more thoroughly to tell the user their regex was invalid before feeding it to Lichen?
        if (!isset($_POST["regex_dir"]) || !isset($_POST["regex_to_select_files"]) || str_contains($_POST["regex_to_select_files"], "..")) {
            $this->core->addErrorMessage("Invalid regex form data.");
            $this->core->redirect($return_url);
        }
        $regex_directories = $_POST["regex_dir"];
        $regex_for_selecting_files = $_POST['regex_to_select_files'];


        // Language ////////////////////////////////////////////////////////////
        $language = $_POST['language'] ?? '';
        if (!in_array($language, PlagiarismUtils::getSupportedLanguages())) {
            $this->core->addErrorMessage("Invalid selected language");
            $this->core->redirect($return_url);
        }


        // Common code threshold ///////////////////////////////////////////////
        $threshold = (int) $_POST['threshold'] ?? 0;
        if ($threshold < 2) {
            $this->core->addErrorMessage("Invalid input provided for threshold");
            $this->core->redirect($return_url);
        }


        // Sequence length /////////////////////////////////////////////////////
        $sequence_length = (int) $_POST['sequence_length'] ?? 0;
        if ($sequence_length < 1) {
            $this->core->addErrorMessage("Invalid input provided for sequence length");
            $this->core->redirect($return_url);
        }


        // Prior terms /////////////////////////////////////////////////////////
        $prev_gradeable_number = $_POST['prior_term_gradeables_number'];
        $prev_term_gradeables = [];
        for ($i = 0; $i < $prev_gradeable_number; $i++) {
            if ($_POST['prev_sem_' . $i] != "" && $_POST['prev_course_' . $i] != "" && $_POST['prev_gradeable_' . $i] != "") {
                array_push($prev_term_gradeables, FileUtils::joinPaths($course_path, $_POST['prev_sem_' . $i], $_POST['prev_course_' . $i], "submissions", $_POST['prev_gradeable_' . $i]));
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
                    $this->core->redirect($return_url);
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
        $config_id = 1;

        if (isdir(FileUtils::joinPaths($course_path, "lichen", $gradeable_id))) {
            foreach (scandir(FileUtils::joinPaths($course_path, "lichen", $gradeable_id)) as $file) {
                if ($file !== '.' && $file !== '..' && is_numeric($file) && intval($file) > $config_id) {
                    $config_id = intval($file) + 1;
                }
            }
        }


        // Create directory structure //////////////////////////////////////////
        $config_path = FileUtils::joinPaths($course_path, "lichen", $gradeable_id, $config_id);
        if (!is_dir($config_path)) {
            FileUtils::createDir($config_path);
        }


        // Upload instructor provided code /////////////////////////////////////
        if ($new_or_edit === "edit" && ($_POST['provided_code_option'] !== "code_provided" || $_FILES['provided_code_file']['tmp_name'] !== "")) {
            // delete the old provided code
            $this->deleteExistingProvidedCode($gradeable_id);
        }
        if ($_POST['provided_code_option'] === "code_provided" && $_FILES['provided_code_file']['tmp_name'] !== "") {
            // error checking
            if (empty($_FILES) || !isset($_FILES['provided_code_file']) || !isset($_FILES['provided_code_file']['tmp_name']) || $_FILES['provided_code_file']['tmp_name'] === "") {
                $this->core->addErrorMessage("Upload failed: Instructor code not provided");
                $this->core->redirect($return_url);
            }
            // save the code
            try {
                $this->saveNewProvidedCode($_FILES['provided_code_file']['tmp_name'], $_FILES['provided_code_file']['name'], $gradeable_id);
            }
            catch (\Exception $e) {
                $this->core->addErrorMessage($e);
                $this->core->redirect($return_url);
            }
        }


        // Save the config.json ////////////////////////////////////////////////
        $json_file = FileUtils::joinPaths($course_path, "lichen", $gradeable_id, $config_id, "config.json");
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
            $this->core->redirect($return_url);
        }


        // Create the Lichen job ///////////////////////////////////////////////
        $ret = $this->enqueueLichenJob("RunLichen", $gradeable_id, $config_id);
        if ($ret !== null) {
            $this->core->addErrorMessage("Failed to add configuration to Lichen queue. Create the configuration again.");
            $this->core->redirect($return_url);
        }

        $this->core->addSuccessMessage("Lichen Plagiarism Detection configuration created for {$gradeable_id} configuration #{$config_id}");
        $this->core->redirect($this->core->buildCourseUrl(['plagiarism']) . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }


    /**
     * @param string $job
     * @param string $gradeable_id
     * @param string $config_id
     * @return string|null
     */
    private function enqueueLichenJob(string $job, string $gradeable_id, string $config_id) {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $lichen_job_data = [
            "job" => $job,
            "semester" => $semester,
            "course" => $course,
            "gradeable" => $gradeable_id,
            "config_id" => $config_id
        ];

        $lichen_job_file = $this->getQueuePath($gradeable_id, $config_id);

        if (file_exists($lichen_job_file) && !is_writable($lichen_job_file)) {
            return "Failed to create lichen job. Try again";
        }

        if (file_put_contents($lichen_job_file, json_encode($lichen_job_data, JSON_PRETTY_PRINT)) === false) {
            return "Failed to write lichen job file. Try again";
        }
        return null;
    }


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/rerun")
     */
    public function reRunPlagiarism(string $gradeable_id, string $config_id) {
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        $lichen_job_file = $this->getQueuePath($gradeable_id, $config_id);
        $lichen_job_file_processing = $this->getProcessingQueuePath($gradeable_id, $config_id));

        # Re run only if following checks are passed.
        if (file_exists($lichen_job_file) || file_exists($lichen_job_file_processing)) {
            $this->core->addErrorMessage("A job is already running for the gradeable. Try again after a while.");
            $this->core->redirect($return_url);
        }

        $json_config_file = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "config.json");

        if (!file_exists($json_config_file)) {
            $this->core->addErrorMessage("Plagiarism results have been deleted. Add new configuration for the gradeable.");
            $this->core->redirect($return_url);
        }

        $ret = $this->enqueueLichenJob("RunLichen", $gradeable_id);
        if ($ret !== null) {
            $this->core->addErrorMessage($ret);
            $this->core->redirect($return_url);
        }

        $this->core->addSuccessMessage("Re-Run of Lichen Plagiarism for {$gradeable_id} configuration #{$config_id}");
        $this->core->redirect($this->core->buildCourseUrl(['plagiarism']) . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/configuration/new", methods={"GET"})
     */
    public function configurePlagiarismForm() {
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
        $this->core->getOutput()->renderOutput(['admin', 'Plagiarism'], 'configurePlagiarismForm', 'new', $gradeable_ids_titles, $prior_term_gradeables, null, null, null, null);
    }


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/configuration/edit")
     */
    public function editPlagiarismSavedConfig(string $gradeable_id, string $config_id) {
        $config_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "config.json");
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        if (!file_exists($config_path)) {
            $this->core->addErrorMessage("Saved configuration not found.");
            $this->core->redirect($return_url);
        }

        $saved_config = json_decode(file_get_contents($config_path), true);
        $title = "";
        if (isset($saved_config['gradeable']) && $saved_config['gradeable'] !== null) {
            $title = $this->core->getQueries()->getGradeableConfig($saved_config['gradeable'])->getTitle();
        }

        $prior_term_gradeables = $this->getGradeablesFromPriorTerm();
        $ignore_submissions = $this->getIgnoreSubmissionType($saved_config['ignore_submissions']);

        $this->core->getOutput()->renderOutput(['admin', 'Plagiarism'], 'configureGradeableForPlagiarismForm', 'edit', null, $prior_term_gradeables, $ignore_submissions[0], $ignore_submissions[1], $saved_config, $title);
    }


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/delete", methods={"POST"})
     */
    public function deletePlagiarismResultAndConfig(string $gradeable_id, string $config_id) {
        $return_url = $this->core->buildCourseUrl(['plagiarism']);
        $config_path = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "config.json");

        if (file_exists($this->getQueuePath($gradeable_id, $config_id)) || file_exists($this->getProcessingQueuePath($gradeable_id, $config_id))) {
            $this->core->addErrorMessage("A job is already running for this configuration. Try again after a while.");
            $this->core->redirect($return_url);
        }

        if (!file_exists($config_path)) {
            $this->core->addErrorMessage("Plagiarism results for the configuration are already deleted. Refresh the page.");
            $this->core->redirect($return_url);
        }

        $ret = $this->enqueueLichenJob("DeleteLichenResult", $gradeable_id);
        if ($ret !== null) {
            $this->core->addErrorMessage($ret);
            $this->core->redirect($return_url);
        }

        $this->core->addSuccessMessage("Lichen results and saved configuration will be deleted.");
        $this->core->redirect($this->core->buildCourseUrl(['plagiarism']) . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/nightly_rerun?configuration={config_id}")
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
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/log?configuration={config_id}")
     */
    public function getRunLog(string $gradeable_id, string $config_id) {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        $course_path = $this->core->getConfig()->getCoursePath();
        $log_file = FileUtils::joinPaths($this->getConfigDirectoryPath($gradeable_id, $config_id), "logs", "lichen_job_output.txt");

        if (!file_exists($log_file)) {
            echo("Error: Unable to find run log.");
        }

        $log_data = file_get_contents($log_file);

        echo $log_data;
    }


    /**
     * @param string $gradeable_id
     * @param string $config_id
     * @param string $user_id_1
     * @param string $version_user_1
     * @param string|null $user_id_2
     * @param string|null $version_user_2
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/concat?configuration={config_id}")
     */
    public function ajaxGetSubmissionConcatenated(string $gradeable_id, string $config_id, string $user_id_1,
                                                  string $version_user_1, string $user_id_2 = null, string $version_user_2 = null) {
        $course_path = $this->core->getConfig()->getCoursePath();
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id_1);
        if ($graded_gradeable === false) {
            return;
        }

        $return = "";
        $active_version_user_1 =  (string) $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();

        $rankings = $this->getOverallRankings($gradeable_id, $config_id);

        if ($rankings === null || count($rankings) === 0) {
            $return = ['error' => 'Rankings file not found or no matches found for selected user'];
            $return = json_encode($return);
            echo($return);
            return;
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
        $data = "";
        if (file_exists($file_name)) {
            if (isset($user_id_2) && !empty($user_id_2) && isset($version_user_2) && !empty($version_user_2)) {
                $color_info = $this->getColorInfo($gradeable_id, $config_id, $user_id_1, $version_user_1, $user_id_2, $version_user_2, '1');
            }
            else {
                $color_info = $this->getColorInfo($gradeable_id, $config_id, $user_id_1, $version_user_1, '', '', '1');
            }
            $data = [
                'display_code1' => $this->getDisplayForCode($file_name),
                'code_version_user_1' => $version_user_1,
                'max_matching_version' => $max_matching_version,
                'active_version_user_1' => $active_version_user_1,
                'all_versions_user_1' => $all_versions_user_1,
                'ci' => $color_info
            ];
        }
        else {
            $return = ['error' => 'User 1 submission.concatenated for specified version not found.'];
            $return = json_encode($return);
            echo($return);
            return;
        }

        if (isset($user_id_2) && !empty($user_id_2) && isset($version_user_2) && !empty($version_user_2)) {
            $file_name = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_2, $version_user_2), "submission.concatenated");

            if (($this->core->getUser()->accessAdmin()) && (file_exists($file_name))) {
                $color_info = $this->getColorInfo($gradeable_id, $config_id,  $user_id_1, $version_user_1, $user_id_2, $version_user_2, '2');
                $data['display_code2'] = $this->getDisplayForCode($file_name);
            }
            else {
                $return = ['error' => 'User 2 submission.concatenated for matching version not found.'];
                $return = json_encode($return);
                echo($return);
                return;
            }
        }

        $data['ci'] = $color_info[0];
        $data['si'] = $color_info[1];
        echo(json_encode($data));
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
     */
    public function getColorInfo(string $gradeable_id, string $config_id, string $user_id_1,
                                 string $version_user_1, string $user_id_2, string $version_user_2, string $codebox): array {
        $course_path = $this->core->getConfig()->getCoursePath();
        $color_info = [];

        //Represents left and right display users
        $color_info[1] = [];
        $segment_info = [];

        $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $version_user_1), "matches.json");
        if (!file_exists($file_path)) {
            return $color_info;
        }
        else {
            // Used to prevent an out of bounds error on the tokens arrays
            $dummyToken = [];
            $dummyToken["char"] = 99999999999; // set it to a big number of negligible significance

            $matches = PlagiarismUtils::constructIntervalsForUserPair($file_path, $user_id_2, intval($version_user_2));

            $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $version_user_1), "tokens.json");
            $tokens_user_1 = json_decode(file_get_contents($file_path), true);

            $tokens_user_2 = [];
            if ($user_id_2 != "") {
                $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_2, $version_user_2), "tokens.json");
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
        }
        return [$color_info, $segment_info];
    }

    public function getDisplayForCode(string $file_name) {
        return file_get_contents($file_name);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/match?configuration={config_id}")
     */
    public function ajaxGetMatchingUsers(string $gradeable_id, string $config_id, string $user_id_1, string $version_user_1) {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        $file_path = FileUtils::joinPaths($this->getSubmissionPath($gradeable_id, $config_id, $user_id_1, $version_user_1), "ranking.txt");

        $i = 1;
        $max_matching_version = 1;
        $max_matching_percent = 0;
        while (is_dir($file_path . $i)) {
            $ranking = $this->getRankingsForUser($gradeable_id, $config_id, $user_id_1, strval($i));

            if ($ranking === null || count($ranking) === 0) {
                echo "";
                return;
            }

            if ($ranking[0][0] > $max_matching_percent) {
                $max_matching_percent = $ranking[0][0];
                $max_matching_version = $i;
            }
            $i++;
        }

        // we shouldn't need any error checking here because we just loaded the file above
        $ranking = $this->getRankingsForUser($gradeable_id, $config_id, $user_id_1, strval($max_matching_version));

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

        $return = json_encode($return);
        echo json_encode($return);
    }


    /**
     * @Route("/courses/{_semester}/{_course}/plagiarism/check_refresh")
     */
    public function checkRefreshLichenMainPage() {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        $gradeables_in_progress = 0;
        $gradeables_with_plagiarism_result = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        foreach ($gradeables_with_plagiarism_result as $i => $gradeable_id_title) {
            if (is_dir(FileUtils::joinPaths($course_path, "lichen", $gradeable_id_title['g_id']))) {
                foreach (scandir(FileUtils::joinPaths($course_path, "lichen", $gradeable_id_title['g_id'])) as $config_id) {
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

        echo $gradeables_in_progress;
    }
}
