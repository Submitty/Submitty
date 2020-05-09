<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\plagiarism\PlagiarismUtils;
use app\libraries\routers\AccessControl;
use app\libraries\routers\FeatureFlag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PlagiarismController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 * @FeatureFlag("plagiarism")
 */
class PlagiarismController extends AbstractController {
    private function getGradeablesFromPriorTerm() {
        $return = array();

        $filename = FileUtils::joinPaths(
            $this->core->getConfig()->getSubmittyPath(),
            "courses",
            "gradeables_from_prior_terms.txt"
        );

        if (file_exists($filename)) {
            $file = fopen($filename, "r");
            if (!$file) {
                exit("Unable to open file!");
            }

            while (!feof($file)) {
                $line = fgets($file);
                $line = trim($line, " ");
                $line = explode("/", $line);
                $sem = $line[5];
                $course = $line[6];
                $gradeables = array();
                while (!feof($file)) {
                    $line = fgets($file);
                    if (trim(trim($line, " "), "\n") === "") {
                        break;
                    }
                    array_push($gradeables, trim(trim($line, " "), "\n"));
                }
                $return[$sem][$course] = $gradeables;
            }

            fclose($file);
            uksort($return, function ($semester_a, $semester_b) {
                $year_a = (int) substr($semester_a, 1);
                $year_b = (int) substr($semester_b, 1);
                if ($year_a > $year_b) {
                    return 0;
                }
                elseif ($year_a < $year_b) {
                    return 1;
                }
                else {
                    return ($semester_a[0] === 'f') ? 0 : 1;
                }
            });
        }
        return $return;
    }
    /**
     * @Route("/{_semester}/{_course}/plagiarism")
     */
    public function plagiarismMainPage($refresh_page = "NO_REFRESH") {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $gradeables_with_plagiarism_result = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        foreach ($gradeables_with_plagiarism_result as $i => $gradeable_id_title) {
            if (!file_exists("/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/ranking/" . $gradeable_id_title['g_id'] . ".txt") && !file_exists("/var/local/submitty/daemon_job_queue/lichen__" . $semester . "__" . $course . "__" . $gradeable_id_title['g_id'] . ".json") && !file_exists("/var/local/submitty/daemon_job_queue/PROCESSING_lichen__" . $semester . "__" . $course . "__" . $gradeable_id_title['g_id'] . ".json")) {
                unset($gradeables_with_plagiarism_result[$i]);
            }
        }

        $nightly_rerun_info_file = "/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/nightly_rerun.json";
        if (!file_exists($nightly_rerun_info_file)) {
            $nightly_rerun_info = array();
            foreach ($gradeables_with_plagiarism_result as $gradeable_id_title) {
                $nightly_rerun_info[$gradeable_id_title['g_id']] = false;
            }
            if (file_put_contents($nightly_rerun_info_file, json_encode($nightly_rerun_info, JSON_PRETTY_PRINT)) === false) {
                die("Failed to create nightly rerun info file");
            }
        }
        else {
            $nightly_rerun_info = json_decode(file_get_contents($nightly_rerun_info_file), true);
            foreach ($nightly_rerun_info as $gradeable_id => $nightly_rerun_status) {
                $flag = 0;
                foreach ($gradeables_with_plagiarism_result as $gradeable_id_title) {
                    if ($gradeable_id_title['g_id'] == $gradeable_id) {
                        $flag = 1;
                        break;
                    }
                }
                if ($flag == 0) {
                    #implies plagiarism result for this gradeable are deleted
                    unset($nightly_rerun_info[$gradeable_id]);
                }
            }

            foreach ($gradeables_with_plagiarism_result as $gradeable_id_title) {
                if (!array_key_exists($gradeable_id_title['g_id'], $nightly_rerun_info)) {
                    #implies plagiarism was run for this gradeable
                    $nightly_rerun_info[$gradeable_id_title['g_id']] = false;
                }
            }
            if (file_put_contents($nightly_rerun_info_file, json_encode($nightly_rerun_info, JSON_PRETTY_PRINT)) === false) {
                die("Failed to create nightly rerun info file");
            }
        }



        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismMainPage', $semester, $course, $gradeables_with_plagiarism_result, $refresh_page, $nightly_rerun_info);
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'deletePlagiarismResultAndConfigForm');
    }

    /**
     * @Route("/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}")
     */
    public function showPlagiarismResult($gradeable_id) {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $gradeable_title = ($this->core->getQueries()->getGradeableConfig($gradeable_id))->getTitle();
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        $file_path = "/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/ranking/" . $gradeable_id . ".txt";
        if (!file_exists($file_path)) {
            $this->core->addErrorMessage("Plagiarism Detection job is running for this gradeable.");
            $this->core->redirect($return_url);
        }
        if (file_get_contents($file_path) == "") {
            $this->core->addSuccessMessage("There are no matches(plagiarism) for the gradeable with current configuration");
            $this->core->redirect($return_url);
        }
        $content = file_get_contents($file_path);
        $content = trim(str_replace(array("\r", "\n"), '', $content));
        $rankings = preg_split('/ +/', $content);
        $rankings = array_chunk($rankings, 3);
        foreach ($rankings as $i => $ranking) {
            array_push($rankings[$i], $this->core->getQueries()->getUserById($ranking[1])->getDisplayedFirstName());
            array_push($rankings[$i], $this->core->getQueries()->getUserById($ranking[1])->getDisplayedLastName());
        }

        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'showPlagiarismResult', $semester, $course, $gradeable_id, $gradeable_title, $rankings);
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismPopUpToShowMatches');
    }

    /**
     * @Route("/{_semester}/{_course}/plagiarism/configuration/new", methods={"GET"})
     */
    public function configureNewGradeableForPlagiarismForm() {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $gradeable_with_submission = array_diff(scandir("/var/local/submitty/courses/$semester/$course/submissions/"), array('.', '..'));
        $gradeable_ids_titles = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        foreach ($gradeable_ids_titles as $i => $gradeable_id_title) {
            if (!in_array($gradeable_id_title['g_id'], $gradeable_with_submission) || file_exists("/var/local/submitty/daemon_job_queue/lichen__" . $semester . "__" . $course . "__" . $gradeable_id_title['g_id'] . ".json") || file_exists("/var/local/submitty/daemon_job_queue/PROCESSING_lichen__" . $semester . "__" . $course . "__" . $gradeable_id_title['g_id'] . ".json") || file_exists("/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/config/lichen_" . $semester . "_" . $course . "_" . $gradeable_id_title['g_id'] . ".json")) {
                unset($gradeable_ids_titles[$i]);
            }
        }

        $prior_term_gradeables = $this->getGradeablesFromPriorTerm();
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'configureGradeableForPlagiarismForm', 'new', $gradeable_ids_titles, $prior_term_gradeables, null, null);
    }

    /**
     * @Route("/{_semester}/{_course}/plagiarism/configuration/new", methods={"POST"})
     */
    public function saveNewPlagiarismConfiguration($new_or_edit, $gradeable_id = null) {

        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $return_url = $this->core->buildCourseUrl(['plagiarism', 'configuration', 'new']);
        if ($new_or_edit == "new") {
            $gradeable_id = $_POST['gradeable_id'];
        }

        if ($new_or_edit == "edit") {
            $return_url = $this->core->buildCourseUrl(['plagiarism', 'configuration', 'edit']) . '?' . http_build_query(['gradeable_id' => $gradeable_id]);
        }

        if (file_exists("/var/local/submitty/daemon_job_queue/lichen__" . $semester . "__" . $course . "__" . $gradeable_id . ".json") || file_exists("/var/local/submitty/daemon_job_queue/PROCESSING_lichen__" . $semester . "__" . $course . "__" . $gradeable_id . ".json")) {
                $this->core->addErrorMessage("A job is already running for the gradeable. Try again after a while.");
                $this->core->redirect($return_url);
        }

        $prev_gradeable_number = $_POST['prior_term_gradeables_number'];
        $ignore_submission_number = $_POST['ignore_submission_number'];
        $version_option = $_POST['version_option'];
        if ($version_option == "active_version") {
            $version_option = "active_version";
        }
        else {
            $version_option = "all_version";
        }

        $file_option = $_POST['file_option'];
        if ($file_option == "regex_matching_files") {
            $file_option = "matching_regex";
        }
        else {
            $file_option = "all";
        }
        if ($file_option == "matching_regex") {
            if (isset($_POST['regex_to_select_files']) && $_POST['regex_to_select_files'] !== '') {
                $regex_for_selecting_files = $_POST['regex_to_select_files'];
            }
            else {
                $this->core->addErrorMessage("No regex provided for selecting files");
                $this->core->redirect($return_url);
            }
        }

        $language = $_POST['language'];
        if (isset($_POST['threshold']) && $_POST['threshold'] !== '') {
            $threshold = $_POST['threshold'];
        }
        else {
            $this->core->addErrorMessage("No input provided for threshold");
            $this->core->redirect($return_url);
        }
        if (isset($_POST['sequence_length']) && $_POST['sequence_length'] !== '') {
            $sequence_length = $_POST['sequence_length'];
        }
        else {
            $this->core->addErrorMessage("No input provided for sequence length");
            $this->core->redirect($return_url);
        }

        $prev_term_gradeables = array();
        for ($i = 0; $i < $prev_gradeable_number; $i++) {
            if ($_POST['prev_sem_' . $i] != "" && $_POST['prev_course_' . $i] != "" && $_POST['prev_gradeable_' . $i] != "") {
                array_push($prev_term_gradeables, "/var/local/submitty/course/" . $_POST['prev_sem_' . $i] . "/" . $_POST['prev_course_' . $i] . "/submissions/" . $_POST['prev_gradeable_' . $i]);
            }
        }

        $ignore_submissions = array();
        $ignore_submission_option = $_POST['ignore_submission_option'];
        if ($ignore_submission_option == "ignore") {
            for ($i = 0; $i < $ignore_submission_number; $i++) {
                if (isset($_POST['ignore_submission_' . $i]) && $_POST['ignore_submission_' . $i] !== '') {
                    array_push($ignore_submissions, $_POST['ignore_submission_' . $i]);
                }
            }
        }

        $gradeable_path =  FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id);
        $provided_code_option = $_POST['provided_code_option'];
        if ($provided_code_option == "code_provided") {
            $instructor_provided_code = true;
        }
        else {
            if (is_dir(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen/provided_code", $gradeable_id))) {
                FileUtils::emptyDir(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen/provided_code", $gradeable_id));
            }
            $instructor_provided_code = false;
        }

        if ($instructor_provided_code == true) {
            if (empty($_FILES) || !isset($_FILES['provided_code_file'])) {
                $this->core->addErrorMessage("Upload failed: Instructor code not provided");
                $this->core->redirect($return_url);
            }

            if (!isset($_FILES['provided_code_file']['tmp_name']) || $_FILES['provided_code_file']['tmp_name'] == "") {
                $this->core->addErrorMessage("Upload failed: Instructor code not provided");
                $this->core->redirect($return_url);
            }
            else {
                $upload = $_FILES['provided_code_file'];
                $target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen/provided_code", $gradeable_id);
                if (!is_dir($target_dir)) {
                    FileUtils::createDir($target_dir);
                }
                FileUtils::emptyDir($target_dir);

                $instructor_provided_code_path = $target_dir;

                if (mime_content_type($upload["tmp_name"]) == "application/zip") {
                    $zip = new \ZipArchive();
                    $res = $zip->open($upload['tmp_name']);
                    if ($res === true) {
                        $zip->extractTo($target_dir);
                        $zip->close();
                    }
                    else {
                        FileUtils::recursiveRmdir($target_dir);
                        $error_message = ($res == 19) ? "Invalid or uninitialized Zip object" : $zip->getStatusString();
                        $this->core->addErrorMessage("Upload failed: {$error_message}");
                        $this->core->redirect($return_url);
                    }
                }
                else {
                    if (!@copy($upload['tmp_name'], FileUtils::joinPaths($target_dir, $upload['name']))) {
                        FileUtils::recursiveRmdir($target_dir);
                        $this->core->addErrorMessage("Upload failed: Could not copy file");
                        $this->core->redirect($return_url);
                    }
                }
            }
        }

        $config_dir = "/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/config/";
        $json_file = "/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/config/lichen_" . $semester . "_" . $course . "_" . $gradeable_id . ".json";
        $json_data = array("semester" =>    $semester,
                            "course" =>     $course,
                            "gradeable" =>  $gradeable_id,
                            "version" =>    $version_option,
                            "file_option" => $file_option,
                            "language" =>   $language,
                            "threshold" =>  $threshold,
                            "hash" => bin2hex(random_bytes(8)),
                            "sequence_length" => $sequence_length,
                            "prev_term_gradeables" => $prev_term_gradeables,
                            "ignore_submissions" =>   $ignore_submissions,
                            "instructor_provided_code" =>   $instructor_provided_code,
                                        );
        if ($file_option == "matching_regex") {
            $json_data["regex"] = $regex_for_selecting_files;
        }
        $old_config = file_get_contents($json_file);
        if ($old_config !== false) {
            $old_array = json_decode($old_config, true);
            $regex_in_old = in_array("regex", $old_array);
            $regex_in_new = in_array("regex", $json_data);
            $json_data["regex_updated"] = false;
            if (
                ($regex_in_old
                && !$regex_in_new)
                 || (!$regex_in_old
                 && $regex_in_new)
                 || ($old_array['regex'] != $json_data['regex'])
            ) {
                    $json_data["regex_updated"] = true;
            }
        }
        if ($instructor_provided_code == true) {
            $json_data["instructor_provided_code_path"] = $instructor_provided_code_path;
        }

        if (file_put_contents($json_file, json_encode($json_data, JSON_PRETTY_PRINT)) === false) {
            $this->core->addErrorMessage("Failed to create configuration. Create the configuration again.");
            $this->core->redirect($return_url);
        }


        // if fails at following step, still provided code and cnfiguration get saved

        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO");
        $current_time_string_tz = $current_time . " " . $this->core->getConfig()->getTimezone()->getName();
        $course_path = $this->core->getConfig()->getCoursePath();
        if (!@file_put_contents(FileUtils::joinPaths($course_path, "lichen", "config", "." . $gradeable_id . ".lichenrun.timestamp"), $current_time_string_tz . "\n")) {
            $this->core->addErrorMessage("Failed to save timestamp file for this Lichen Run. Create the configuration again.");
            $this->core->redirect($return_url);
        }

        // if fails at following step, still provided code, cnfiguration, timestamp file get saved

        $ret = $this->enqueueLichenJob("RunLichen", $gradeable_id);
        if ($ret !== null) {
            $this->core->addErrorMessage("Failed to create configuration. Create the configuration again.");
            $this->core->redirect($return_url);
        }

        $this->core->addSuccessMessage("Lichen Plagiarism Detection configuration created for " . $gradeable_id);
        $this->core->redirect($this->core->buildCourseUrl(['plagiarism']) . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }

    private function enqueueLichenJob($job, $gradeable_id) {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $lichen_job_data = [
            "job" => $job,
            "semester" => $semester,
            "course" => $course,
            "gradeable" => $gradeable_id
        ];
        $lichen_job_file = "/var/local/submitty/daemon_job_queue/lichen__" . $semester . "__" . $course . "__" . $gradeable_id . ".json";

        if (file_exists($lichen_job_file) && !is_writable($lichen_job_file)) {
            return "Failed to create lichen job. Try again";
        }

        if (file_put_contents($lichen_job_file, json_encode($lichen_job_data, JSON_PRETTY_PRINT)) === false) {
            return "Failed to write lichen job file. Try again";
        }
        return null;
    }

    /**
     * @Route("/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/rerun")
     */
    public function reRunPlagiarism($gradeable_id) {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        # Re run only if following checks are passed.
        if (file_exists("/var/local/submitty/daemon_job_queue/lichen__" . $semester . "__" . $course . "__" . $gradeable_id . ".json") || file_exists("/var/local/submitty/daemon_job_queue/PROCESSING_lichen__" . $semester . "__" . $course . "__" . $gradeable_id . ".json")) {
                $this->core->addErrorMessage("A job is already running for the gradeable. Try again after a while.");
                $this->core->redirect($return_url);
        }

        if (!file_exists("/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/config/lichen_" . $semester . "_" . $course . "_" . $gradeable_id . ".json")) {
            $this->core->addErrorMessage("Plagiarism results have been deleted. Add new configuration for the gradeable.");
            $this->core->redirect($return_url);
        }

        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO");
        $current_time_string_tz = $current_time . " " . $this->core->getConfig()->getTimezone()->getName();
        $course_path = $this->core->getConfig()->getCoursePath();
        if (!@file_put_contents(FileUtils::joinPaths($course_path, "lichen", "config", "." . $gradeable_id . ".lichenrun.timestamp"), $current_time_string_tz . "\n")) {
            $this->core->addErrorMessage("Failed to save timestamp file for this Lichen Run. Re-run the detector.");
            $this->core->redirect($return_url);
        }

        $ret = $this->enqueueLichenJob("RunLichen", $gradeable_id);
        if ($ret !== null) {
            $this->core->addErrorMessage($ret);
            $this->core->redirect($return_url);
        }

        $this->core->addSuccessMessage("Re-Run of Lichen Plagiarism for " . $gradeable_id);
        $this->core->redirect($this->core->buildCourseUrl(['plagiarism']) . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }

    /**
     * @Route("/{_semester}/{_course}/plagiarism/configuration/edit")
     */
    public function editPlagiarismSavedConfig($gradeable_id) {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        $prior_term_gradeables = $this->getGradeablesFromPriorTerm();

        if (!file_exists("/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/config/lichen_" . $semester . "_" . $course . "_" . $gradeable_id . ".json")) {
            $this->core->addErrorMessage("Saved configuration not found.");
            $this->core->redirect($return_url);
        }

        $saved_config = json_decode(file_get_contents("/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/config/lichen_" . $semester . "_" . $course . "_" . $gradeable_id . ".json"), true);
        $title = "";
        if (isset($saved_config['gradeable']) && $saved_config['gradeable'] !== null) {
            $title = $this->core->getQueries()->getGradeableConfig($saved_config['gradeable'])->getTitle();
        }

        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'configureGradeableForPlagiarismForm', 'edit', null, $prior_term_gradeables, $saved_config, $title);
    }

    /**
     * @Route("/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/delete", methods={"POST"})
     */
    public function deletePlagiarismResultAndConfig($gradeable_id) {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        if (file_exists("/var/local/submitty/daemon_job_queue/lichen__" . $semester . "__" . $course . "__" . $gradeable_id . ".json") || file_exists("/var/local/submitty/daemon_job_queue/PROCESSING_lichen__" . $semester . "__" . $course . "__" . $gradeable_id . ".json")) {
                $this->core->addErrorMessage("A job is already running for the gradeable. Try again after a while.");
                $this->core->redirect($return_url);
        }

        if (!file_exists("/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/config/lichen_" . $semester . "_" . $course . "_" . $gradeable_id . ".json")) {
            $this->core->addErrorMessage("Plagiarism results for the gradeable are already deleted. Refresh the page.");
            $this->core->redirect($return_url);
        }

        $ret = $this->enqueueLichenJob("DeleteLichenResult", $gradeable_id);
        if ($ret !== null) {
            $this->core->addErrorMessage($ret);
            $this->core->redirect($return_url);
        }

        $this->core->addSuccessMessage("Lichen results and saved configuration for the gradeable will be deleted.");
        $this->core->redirect($this->core->buildCourseUrl(['plagiarism']) . '?' . http_build_query(['refresh_page' => 'REFRESH_ME']));
    }

    /**
     * @Route("/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/nightly_rerun")
     */
    public function toggleNightlyRerun($gradeable_id) {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $return_url = $this->core->buildCourseUrl(['plagiarism']);

        $nightly_rerun_info_file = "/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/nightly_rerun.json";

        $nightly_rerun_info = json_decode(file_get_contents($nightly_rerun_info_file), true);
        $nightly_rerun_info[$gradeable_id] = !$nightly_rerun_info[$gradeable_id];
        if (file_put_contents($nightly_rerun_info_file, json_encode($nightly_rerun_info, JSON_PRETTY_PRINT)) === false) {
            $this->core->addErrorMessage("Failed to change nightly rerun for the gradeable");
            $this->core->redirect($return_url);
        }
        $this->core->addSuccessMessage("Nightly Rerun status changed for the gradeable");
        $this->core->redirect($return_url);
    }

    /**
     * @Route("/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/concat")
     */
    public function ajaxGetSubmissionConcatenated($gradeable_id, $user_id_1, $version_user_1, $user_id_2 = null, $version_user_2 = null) {
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
        $file_path = $course_path . "/lichen/ranking/" . $gradeable_id . ".txt";
        if (!file_exists($file_path)) {
            $return = array('error' => 'Ranking file not exists.');
            $return = json_encode($return);
            echo($return);
            return;
        }
        $content = file_get_contents($file_path);
        $content = trim(str_replace(array("\r", "\n"), '', $content));
        $rankings = preg_split('/ +/', $content);
        $rankings = array_chunk($rankings, 3);
        foreach ($rankings as $ranking) {
            if ($ranking[1] == $user_id_1) {
                $max_matching_version = $ranking[2];
            }
        }
        if ($version_user_1 == "max_matching") {
            $version_user_1 = $max_matching_version;
        }
        $all_versions_user_1 = array_diff(scandir($course_path . "/lichen/concatenated/" . $gradeable_id . "/" . $user_id_1), array(".", ".."));

        $file_name = $course_path . "/lichen/concatenated/" . $gradeable_id . "/" . $user_id_1 . "/" . $version_user_1 . "/submission.concatenated";
        $data = "";
        if (($this->core->getUser()->accessAdmin()) && (file_exists($file_name))) {
            if (isset($user_id_2) && !empty($user_id_2) && isset($version_user_2) && !empty($version_user_2)) {
                $color_info = $this->getColorInfo($course_path, $gradeable_id, $user_id_1, $version_user_1, $user_id_2, $version_user_2, '1');
            }
            else {
                $color_info = $this->getColorInfo($course_path, $gradeable_id, $user_id_1, $version_user_1, '', '', '1');
            }
            $data = array('display_code1' => $this->getDisplayForCode($file_name, $color_info), 'code_version_user_1' => $version_user_1, 'max_matching_version' => $max_matching_version, 'active_version_user_1' => $active_version_user_1, 'all_versions_user_1' => $all_versions_user_1, 'ci' => $color_info);
        }
        else {
            $return = array('error' => 'User 1 submission.concatenated for specified version not found.');
            $return = json_encode($return);
            echo($return);
            return;
        }
        if (isset($user_id_2) && !empty($user_id_2) && isset($version_user_2) && !empty($version_user_2)) {
            $file_name = $course_path . "/lichen/concatenated/" . $gradeable_id . "/" . $user_id_2 . "/" . $version_user_2 . "/submission.concatenated";

            if (($this->core->getUser()->accessAdmin()) && (file_exists($file_name))) {
                $color_info = $this->getColorInfo($course_path, $gradeable_id, $user_id_1, $version_user_1, $user_id_2, $version_user_2, '2');
                $data['display_code2'] = $this->getDisplayForCode($file_name, $color_info);
            }
            else {
                $return = array('error' => 'User 2 submission.concatenated for matching version not found.');
                $return = json_encode($return);
                echo($return);
                return;
            }
        }
        $data['ci'] = $color_info[0];
        $data['si'] = $color_info[1];
        $return = json_encode($data);
        echo($return);
    }

    public function getColorInfo($course_path, $gradeable_id, $user_id_1, $version_user_1, $user_id_2, $version_user_2, $codebox) {
        $color_info = array();

        //Represents left and right display users
        $color_info[1] = array();
        $color_info[2] = array();
        $segment_info = array();

        $file_path = $course_path . "/lichen/matches/" . $gradeable_id . "/" . $user_id_1 . "/" . $version_user_1 . "/matches.json";
        if (!file_exists($file_path)) {
            return $color_info;
        }
        else {
            $matches = PlagiarismUtils::mergeIntervals(PlagiarismUtils::constructIntervals($file_path));
            //$matches = json_decode(file_get_contents($file_path), true);
            $file_path = $course_path . "/lichen/tokenized/" . $gradeable_id . "/" . $user_id_1 . "/" . $version_user_1 . "/tokens.json";
            $tokens_user_1 = json_decode(file_get_contents($file_path), true);
            if ($user_id_2 != "") {
                $file_path = $course_path . "/lichen/tokenized/" . $gradeable_id . "/" . $user_id_2 . "/" . $version_user_2 . "/tokens.json";
                $tokens_user_2 = json_decode(file_get_contents($file_path), true);
            }
            while (!$matches->isEmpty()) {
                $match = $matches->top();
                $s_pos = $match->getStart();
                $e_pos = $match->getEnd();
                $start_pos = $tokens_user_1[$s_pos - 1]["char"] - 1;
                $start_line = $tokens_user_1[$s_pos - 1]["line"] - 1;
                $end_pos = $tokens_user_1[$e_pos - 1]["char"] - 1;
                $end_line = $tokens_user_1[$e_pos - 1]["line"] - 1;
                $start_value = $tokens_user_1[$s_pos - 1]["value"];
                $end_value = $tokens_user_1[$e_pos - 1]["value"];
                $userMatchesStarts = array();
                $userMatchesEnds = array();
                // if (match['type'] == "match") {
                    $segment_info["{$start_line}_{$start_pos}"] = array();
                    $orange_color = false;
                foreach ($match->getUsers() as $i => $other) {
                    $segment_info["{$start_line}_{$start_pos}"][] = $other->getUid() . "_" . $other->getVid();
                    if ($other->getUid() == $user_id_2) {
                        $orange_color = true;
                        if ($codebox == "2" && $user_id_2 != "") {
                            foreach ($other->getMatchingPositions() as $pos) {
                                $matchPosStart = $pos['start'];
                                $matchPosEnd =  $pos['end'];
                                $start_pos_2 = $tokens_user_2[$matchPosStart - 1]["char"] - 1;
                                $start_line_2 = $tokens_user_2[$matchPosStart - 1]["line"] - 1;
                                $end_pos_2 = $tokens_user_2[$matchPosEnd - 1]["char"] - 1;
                                $end_line_2 = $tokens_user_2[$matchPosEnd - 1]["line"] - 1;
                                $start_value_2 = $tokens_user_2[$matchPosStart - 1]["value"];
                                $end_value_2 = $tokens_user_2[$matchPosEnd - 1]["value"];
                                    
                                $color_info[2][] = [$start_pos_2, $start_line_2, $end_pos_2, $end_line_2, '#ffa500', $start_value_2, $end_value_2, $matchPosStart, $matchPosEnd];
                                $userMatchesStarts[] = $matchPosStart;
                                $userMatchesEnds[] = $matchPosEnd;
                            }
                        }
                    }
                }

                if ($orange_color) {
                    //Color is orange -- general match from selected match
                    $color = '#ffa500';
                }
                elseif (!$orange_color) {
                    //Color is yellow -- matches other students...
                    $color = '#ffff00';
                }
                // }
                // elseif ($match["type"] == "common") {
                //     //Color is grey -- common matches among all students
                //     $color = '#cccccc';
                // }
                // elseif ($match["type"] == "provided") {
                //     //Color is green -- instructor provided code #b5e3b5
                //     $color = '#b5e3b5';
                // }

                array_push($color_info[1], [$start_pos, $start_line, $end_pos, $end_line, $color, $start_value, $end_value, count($userMatchesStarts) > 0 ? $userMatchesStarts : [], count($userMatchesEnds) > 0 ? $userMatchesEnds : [] ]);
                $matches->pop();
            }
        }
        return [$color_info, $segment_info];
    }

    public function getDisplayForCode(string $file_name, $color_info) {
        return file_get_contents($file_name);
    }

    /**
     * @Route("/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/match")
     */
    public function ajaxGetMatchingUsers($gradeable_id, $user_id_1, $version_user_1) {
        $course_path = $this->core->getConfig()->getCoursePath();
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        $return = array();
        $error = "";
        $file_path = $course_path . "/lichen/ranking/" . $gradeable_id . ".txt";
        if (!file_exists($file_path)) {
            $return = array('error' => 'Ranking file not exists.');
            $return = json_encode($return);
            echo($return);
            return;
        }
        $content = file_get_contents($file_path);
        $content = trim(str_replace(array("\r", "\n"), '', $content));
        $rankings = preg_split('/ +/', $content);
        $rankings = array_chunk($rankings, 3);
        foreach ($rankings as $ranking) {
            if ($ranking[1] == $user_id_1) {
                $max_matching_version = $ranking[2];
            }
        }
        $version = $version_user_1;
        if ($version_user_1 == "max_matching") {
            $version = $max_matching_version;
        }
        $file_path = $course_path . "/lichen/matches/" . $gradeable_id . "/" . $user_id_1 . "/" . $version . "/matches.json";
        if (!file_exists($file_path)) {
            echo("no_match_for_this_version");
        }
        else {
            $content = json_decode(file_get_contents($file_path), true);
            foreach ($content as $match) {
                if ($match["type"] == "match") {
                    foreach ($match["others"] as $match_info) {
                        if (!in_array(array($match_info["username"],$match_info["version"]), $return)) {
                            array_push($return, array($match_info["username"],$match_info["version"]));
                        }
                    }
                }
            }
            foreach ($return as $i => $match_user) {
                array_push($return[$i], $this->core->getQueries()->getUserById($match_user[0])->getDisplayedFirstName());
                array_push($return[$i], $this->core->getQueries()->getUserById($match_user[0])->getDisplayedLastName());
            }
            $return = json_encode($return);
            echo($return);
        }
    }

    /**
     * @Route("/{_semester}/{_course}/plagiarism/gradeable/{gradeable_id}/clicked_match")
     */
    public function ajaxGetMatchesForClickedMatch($gradeable_id, $user_id_1, $version_user_1, $start, $end) {
        $course_path = $this->core->getConfig()->getCoursePath();

        $token_path = $course_path . "/lichen/tokenized/" . $gradeable_id . "/" . $user_id_1 . "/" . $version_user_1 . "/tokens.json";
        $tokens_user_1 = json_decode(file_get_contents($token_path), true);

        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        $return = array();

        $file_path = $course_path . "/lichen/matches/" . $gradeable_id . "/" . $user_id_1 . "/" . $version_user_1 . "/matches.json";
        if (!file_exists($file_path)) {
            echo(json_encode(array("error" => "user 1 matches.json does not exists")));
        }
        else {
            $content = json_decode(file_get_contents($file_path), true);
            foreach ($content as $match) {
                if ($tokens_user_1[$match["start"] - 1]["line"] - 1 == $start && $tokens_user_1[$match["end"] - 1]["line"] - 1 == $end) { //also do char place
                    foreach ($match["others"] as $match_info) {
                        $matchingpositions = array();
                        $token_path_2 = $course_path . "/lichen/tokenized/" . $gradeable_id . "/" . $match_info['username'] . "/" . $match_info['version'] . "/tokens.json";
                        $tokens_user_2 = json_decode(file_get_contents($token_path_2), true);
                        foreach ($match_info['matchingpositions'] as $matchingpos) {
                            array_push($matchingpositions, array("start_line" => $tokens_user_2[$matchingpos["start"] - 1]["line"] - 1 , "start_ch" => $tokens_user_2[$matchingpos["start"] - 1]["char"] - 1,
                                 "end_line" => $tokens_user_2[$matchingpos["end"] - 1]["line"] - 1, "end_ch" => $tokens_user_2[$matchingpos["end"] - 1]["char"] - 1 ));
                        }
                        $first_name = $this->core->getQueries()->getUserById($match_info["username"])->getDisplayedFirstName();
                        $last_name = $this->core->getQueries()->getUserById($match_info["username"])->getDisplayedLastName();
                        array_push($return, array($match_info["username"],$match_info["version"], $matchingpositions, $first_name, $last_name));
                    }
                }
            }
            $return = json_encode($return);
            echo($return);
        }
    }

    /**
     * Check if the results folder exists for a given gradeable and version results.json
     * in the results/ directory. If the file exists, we output a string that the calling
     * JS checks for to initiate a page refresh (so as to go from "in-grading" to done
     *
     * @Route("/{_semester}/{_course}/plagiarism/check_refresh")
     */
    public function checkRefreshLichenMainPage() {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $gradeable_ids_titles = $this->core->getQueries()->getAllGradeablesIdsAndTitles();

        foreach ($gradeable_ids_titles as $gradeable_id_title) {
            if (file_exists("/var/local/submitty/daemon_job_queue/lichen__" . $semester . "__" . $course . "__" . $gradeable_id_title['g_id'] . ".json") || file_exists("/var/local/submitty/daemon_job_queue/PROCESSING_lichen__" . $semester . "__" . $course . "__" . $gradeable_id_title['g_id'] . ".json")) {
                $this->core->getOutput()->renderString("REFRESH_ME");
                return;
            }
        }

        $this->core->getOutput()->renderString("NO_REFRESH");
    }
}
