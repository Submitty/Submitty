<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\plagiarism\PlagiarismUtils;

class PlagiarismView extends AbstractView {

    public function plagiarismMainPage($gradeables_with_plagiarism_result, $refresh_page, $nightly_rerun_info) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection');

        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $plagiarism_result_info = [];

        $course_path = $this->core->getConfig()->getCoursePath();
        foreach ($gradeables_with_plagiarism_result as $gradeable) {
            $plagiarism_row = [];
            $plagiarism_row['title'] = $gradeable['g_title'];
            $plagiarism_row['id'] = $gradeable['g_id'];
            $plagiarism_row['duedate'] = $gradeable['g_grade_due_date']->format('F d Y H:i:s'); // TODO: think about the format of this date.  Using the format of the last run date for now.
            $plagiarism_row['delete_form_action'] = $this->core->buildCourseUrl(['plagiarism', 'gradeable', $plagiarism_row['id'], 'delete']);
            if (file_exists(FileUtils::joinPaths($course_path, "lichen", "ranking", $plagiarism_row['id'], "overall_ranking.txt"))) {
                $timestamp = date("F d Y H:i:s", filemtime(FileUtils::joinPaths($course_path, "lichen", "ranking", $plagiarism_row['id'], "overall_ranking.txt")));
                $students = array_diff(scandir(FileUtils::joinPaths($course_path, "lichen", "concatenated", $plagiarism_row['id'])), ['.', '..']);
                $submissions = 0;
                foreach ($students as $student) {
                    $submissions += count(array_diff(scandir(FileUtils::joinPaths($course_path, "lichen", "concatenated", $plagiarism_row['id'], $student)), ['.', '..']));
                }
                $students = count($students);
            }
            else {
                $timestamp = "N/A";
                $students = "N/A";
                $submissions = "N/A";
            }
            $plagiarism_row['timestamp'] = $timestamp;
            $plagiarism_row['students'] = $students;
            $plagiarism_row['submissions'] = $submissions;

            $plagiarism_row['night_rerun_status'] = $nightly_rerun_info[$plagiarism_row['id']] ? "" : "checked";

            // lichen job in queue for this gradeable but processing not started
            if (file_exists(FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue", "lichen__{$semester}__{$course}__{$plagiarism_row['id']}.json"))) {
                $plagiarism_row['in_queue'] = true;
                $plagiarism_row['processing'] = false;
            }
            elseif (file_exists(FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue", "PROCESSING_lichen__{$semester}__{$course}__{$plagiarism_row['id']}.json"))) {
                // lichen job in processing stage for this gradeable but not completed
                $plagiarism_row['in_queue'] = true;
                $plagiarism_row['processing'] = true;
            }
            else {
                // no lichen job
                $ranking_file_path = FileUtils::joinPaths($course_path, "lichen", "ranking", $plagiarism_row["id"], "overall_ranking.txt");
                if (!file_exists($ranking_file_path) || file_get_contents($ranking_file_path) == "") {
                    $plagiarism_row['matches_and_topmatch'] = "0 students matched, N/A top match";
                }
                else {
                    $content = trim(str_replace(["\r", "\n"], '', file_get_contents($ranking_file_path)));
                    $rankings = array_chunk(preg_split('/ +/', $content), 3);
                    $plagiarism_row['ranking_available'] = true;
                    $plagiarism_row['matches_and_topmatch'] = count($rankings) . " students matched, {$rankings[0][0]} top match";
                    $plagiarism_row['gradeable_link'] = $this->core->buildCourseUrl(['plagiarism', 'gradeable', $plagiarism_row['id']]);
                }
                $plagiarism_row['rerun_plagiarism_link'] = $this->core->buildCourseUrl(["plagiarism", "gradeable", $plagiarism_row["id"], "rerun"]);
                $plagiarism_row['edit_plagiarism_link'] = $this->core->buildCourseUrl(["plagiarism", "configuration", "edit"]) . "?gradeable_id={$plagiarism_row["id"]}";
                $plagiarism_row['nightly_rerun_link'] = $this->core->buildCourseUrl(["plagiarism", "gradeable", $plagiarism_row["id"], "nightly_rerun"]);
            }
            $plagiarism_result_info[] = $plagiarism_row;
        }

        $this->core->getOutput()->addInternalCss("plagiarism.css");
        $this->core->getOutput()->enableMobileViewport();

        return $this->core->getOutput()->renderTwigTemplate('plagiarism/Plagiarism.twig', [
            "refresh_page" => $refresh_page,
            "plagiarism_results_info" => $plagiarism_result_info,
            "csrf_token" => $this->core->getCsrfToken(),
            "new_plagiarism_config_link" => $this->core->buildCourseUrl(['plagiarism', 'configuration', 'new']),
            "refreshLichenMainPageLink" => $this->core->buildCourseUrl(['plagiarism', 'check_refresh']),
            "semester" => $semester,
            "course" => $course
        ]);
    }

    public function showPlagiarismResult($gradeable_id, $config_id, $gradeable_title, $rankings) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism  Detection', $this->core->buildCourseUrl(['plagiarism']));
        $this->core->getOutput()->addBreadcrumb($gradeable_title);
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('codemirror', 'codemirror.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('codemirror', 'codemirror.js'));
        $this->core->getOutput()->addInternalJs('plagiarism.js');
        $this->core->getOutput()->addInternalJs('resizable-panels.js');
        $this->core->getOutput()->addInternalCss('plagiarism.css');
        $this->core->getOutput()->addInternalCss('scrollable-sidebar.css');
        $this->core->getOutput()->enableMobileViewport();

        return $this->core->getOutput()->renderTwigTemplate('plagiarism/PlagiarismResult.twig', [
            "gradeable_id" => $gradeable_id,
            "config_id" => $config_id,
            "gradeable_title" => $gradeable_title,
            "rankings" => $rankings,
        ]);
    }

    public function configurePlagiarismForm($new_or_edit, $gradeable_ids_titles, $prior_term_gradeables, $ignore_submissions, $ignore_submissions_others, $saved_config, $title) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection', $this->core->buildCourseUrl(['plagiarism']));
        $this->core->getOutput()->addBreadcrumb('Configure New Gradeable');
        $this->core->getOutput()->addInternalCss("plagiarism.css");
        $this->core->getOutput()->enableMobileViewport();

        $prior_term_gradeables_json = json_encode($prior_term_gradeables);

        // Default values for the form
        $gradeable_id = "";
        $provided_code = false;
        $provided_code_filenames = [];
        $version = "all_versions";
        $regex = "";
        $regex_dirs = ["submissions"];
        $language = array_fill_keys(PlagiarismUtils::getSupportedLanguages(), "");
        $language["plaintext"] = "selected";
        $threshold = 5;
        $sequence_length = 4;
        //$prior_term_gradeables_number = $saved_config['prev_term_gradeables'] ? count($saved_config['prev_term_gradeables']) + 1 : 1;
        $prior_terms = false;
        $ignore_submissions_list = null;

        // Values which are in saved configuration
        if ($new_or_edit == "edit") {
            $gradeable_id = $saved_config['gradeable'];

            if (is_dir(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen", "provided_code", $gradeable_id))) {
                $provided_code_filename_array = array_diff(scandir(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "lichen", "provided_code", $gradeable_id)), [".", ".."]);
                $provided_code = count($provided_code_filename_array) > 0;
                foreach ($provided_code_filename_array as $filename) {
                    $provided_code_filenames[] = $filename;
                }
            }

            $version = $saved_config['version'];
            $regex = $saved_config['regex'];
            $regex_dirs = $saved_config['regex_dirs'];
            $language["plaintext"] = ""; // Reset value after we set it initially so be selected
            $language[$saved_config['language']] = "selected";
            $threshold = (int) $saved_config['threshold'];
            $sequence_length = (int) $saved_config['sequence_length'];
            $prior_terms = false; // $prior_term_gradeables_number > 1;
            $ignore_submissions_list = implode(", ", $ignore_submissions_others);
        }

        return $this->core->getOutput()->renderTwigTemplate('plagiarism/PlagiarismConfigurationForm.twig', [
            "new_or_edit" => $new_or_edit,
            "form_action_link" => $this->core->buildCourseUrl(['plagiarism', 'configuration', 'new']) . "?new_or_edit={$new_or_edit}&gradeable_id={$gradeable_id}",
            "csrf_token" => $this->core->getCsrfToken(),
            //"prior_term_gradeables_number" => $prior_term_gradeables_number,
            "provided_code" => $provided_code,
            "gradeable_ids_titles" => $gradeable_ids_titles,
            "title" => $title,
            "provided_code_filenames" => $provided_code_filenames,
            "version" => $version,
            "regex" => $regex,
            "regex_dirs" => $regex_dirs,
            "language" => $language,
            "threshold" => $threshold,
            "sequence_length" => $sequence_length,
            "prior_terms" => $prior_terms,
            "ignore_submissions" => $ignore_submissions,
            "ignore_submissions_list" => $ignore_submissions_list,
            "plagiarism_link" => $this->core->buildCourseUrl(['plagiarism']),
            "prior_term_gradeables" => $prior_term_gradeables,
            "prior_term_gradeables_json" => $prior_term_gradeables_json
        ]);
    }
}
