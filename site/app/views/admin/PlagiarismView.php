<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\FileUtils;

class PlagiarismView extends AbstractView {

    public function plagiarismMainPage($semester, $course, $gradeables_with_plagiarism_result, $refresh_page, $nightly_rerun_info) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection');

        $plagiarism_result_info = [];

        $course_path = $this->core->getConfig()->getCoursePath();
        foreach ($gradeables_with_plagiarism_result as $gradeable) {
            $plagiarism_row = [];
            $plagiarism_row['title'] = $gradeable['g_title'];
            $plagiarism_row['id'] = $gradeable['g_id'];
            $plagiarism_row['delete_form_action'] = $this->core->buildCourseUrl([
                'plagiarism',
                'gradeable',
                $plagiarism_row['id'],
                'delete'
            ]);
            if (file_exists($course_path . "/lichen/ranking/" . $plagiarism_row['id'] . ".txt")) {
                $timestamp = date("F d Y H:i:s.", filemtime($course_path . "/lichen/ranking/" . $plagiarism_row['id'] . ".txt"));
                $students = array_diff(scandir($course_path . "/lichen/concatenated/" . $plagiarism_row['id']), ['.', '..']);
                $submissions = 0;
                foreach ($students as $student) {
                    $submissions += count(array_diff(scandir($course_path . "/lichen/concatenated/" . $plagiarism_row['id'] . "/" . $student), ['.', '..']));
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
            if (file_exists("/var/local/submitty/daemon_job_queue/lichen__" . $semester . "__" . $course . "__" . $plagiarism_row['id'] . ".json")) {
                $plagiarism_row['in_queue'] = true;
                $plagiarism_row['processing'] = false;
            }
            elseif (file_exists("/var/local/submitty/daemon_job_queue/PROCESSING_lichen__" . $semester . "__" . $course . "__" . $plagiarism_row['id'] . ".json")) {
                // lichen job in processing stage for this gradeable but not completed
                $plagiarism_row['in_queue'] = true;
                $plagiarism_row['processing'] = true;
            }
            else {
                // no lichen job
                $ranking_file_path = "/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/ranking/" . $plagiarism_row['id'] . ".txt";
                if (file_get_contents($ranking_file_path) == "") {
                    $plagiarism_row['matches_and_topmatch'] = "0 students matched, N/A top match";
                }
                else {
                    $content = trim(str_replace(["\r", "\n"], '', file_get_contents($ranking_file_path)));
                    $rankings = array_chunk(preg_split('/ +/', $content), 3);
                    $plagiarism_row['ranking_available'] = true;
                    $plagiarism_row['matches_and_topmatch'] = count($rankings) . " students matched, " . $rankings[0][0] . " top match";
                    $plagiarism_row['gradeable_link'] = $this->core->buildCourseUrl(['plagiarism', 'gradeable', $plagiarism_row['id']]);
                }
                $plagiarism_row['rerun_plagiarism_link'] = $this->core->buildCourseUrl(['plagiarism', 'gradeable', "{$plagiarism_row['id']}", 'rerun']);
                $plagiarism_row['edit_plagiarism_link'] = $this->core->buildCourseUrl(['plagiarism', 'configuration', 'edit']) . "?gradeable_id={$plagiarism_row['id']}";
                $plagiarism_row['nightly_rerun_link'] = $this->core->buildCourseUrl(["plagiarism", "gradeable", "{$plagiarism_row['id']}", "nightly_rerun"]);
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

    public function showPlagiarismResult($semester, $course, $gradeable_id, $gradeable_title, $rankings) {
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
            "gradeable_title" => $gradeable_title,
            "rankings" => $rankings,
        ]);
    }

    public function configureGradeableForPlagiarismForm($new_or_edit, $gradeable_ids_titles, $prior_term_gradeables, $saved_config, $title) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection', $this->core->buildCourseUrl(['plagiarism']));
        $this->core->getOutput()->addBreadcrumb('Configure New Gradeable');
        $prior_term_gradeables_json = json_encode($prior_term_gradeables);

        #default values for the form
        $gradeable_id = "";
        $all_version = "checked";
        $active_version = "";
        $all_files = "checked";
        $regex_matching_files = "";
        $regex = "";
        $language = ["python" => "selected", "java" => "", "plaintext" => "", "cpp" => "", "mips" => ""];
        $provided_code = "";
        $no_provided_code = "checked";
        $provided_code_filename = "";
        $threshold = "5";
        $sequence_length = "10";
        $prior_term_gradeables_number = $saved_config['prev_term_gradeables'] ? count($saved_config['prev_term_gradeables']) + 1 : 1;
        $ignore_submission_number = $saved_config['ignore_submissions'] ? count($saved_config['ignore_submissions']) + 1 : 1;
        $ignore = "";
        $no_ignore = "checked";


        #values which are in saved configuration
        if ($new_or_edit == "edit") {
            $gradeable_id = $saved_config['gradeable'];
            $all_version = ($saved_config['version'] == "all_version") ? "checked" : "";
            $active_version = ($saved_config['version'] == "active_version") ? "checked" : "";
            if ($saved_config['file_option'] == "matching_regex") {
                $all_files = "";
                $regex_matching_files = "checked";
                $regex = $saved_config['regex'];
            }
            $language[$saved_config['language']] = "selected";

            if ($saved_config["instructor_provided_code"] == true) {
                $provided_code_filename_array = (array_diff(scandir($saved_config["instructor_provided_code_path"]), [".", ".."]));
                foreach ($provided_code_filename_array as $filename) {
                    $provided_code_filename = $filename;
                }
                $provided_code = "checked";
                $no_provided_code = "";
            }

            $threshold = $saved_config['threshold'];
            $sequence_length = $saved_config['sequence_length'];

            if (count($saved_config['ignore_submissions']) > 0) {
                $ignore = "checked";
                $no_ignore = "";
            }
        }
        $this->core->getOutput()->addInternalCss("plagiarism.css");
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate('plagiarism/PlagiarismConfigurationForm.twig', [
            "new_or_edit" => $new_or_edit,
            "form_action_link" => $this->core->buildCourseUrl(['plagiarism', 'configuration', 'new']) . "?new_or_edit={$new_or_edit}&gradeable_id={$gradeable_id}",
            "csrf_token" => $this->core->getCsrfToken(),
            "prior_term_gradeables_number" => $prior_term_gradeables_number,
            "ignore_submission_number" => $ignore_submission_number,
            "gradeable_ids_titles" => $gradeable_ids_titles,
            "title" => $title,
            "saved_config" => $saved_config,
            'no_provided_code' => $no_provided_code,
            'provided_code' => $provided_code,
            "all_version" => $all_version,
            "active_version" => $active_version,
            "all_files" => $all_files,
            "regex_matching_files" => $regex_matching_files,
            "regex" => $regex,
            "language" => $language,
            "threshold" => $threshold,
            "sequence_length" => $sequence_length,
            "no_ignore" => $no_ignore,
            "ignore" => $ignore,
            "provided_code_filename" => $provided_code_filename,
            "plagiarism_link" => $this->core->buildCourseUrl(['plagiarism']),
            "prior_term_gradeables" => $prior_term_gradeables,
            "prior_term_gradeables_json" => $prior_term_gradeables_json,
        ]);
    }
}
