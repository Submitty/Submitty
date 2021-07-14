<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\plagiarism\PlagiarismUtils;

class PlagiarismView extends AbstractView {

    public function plagiarismMainPage($all_configurations, $refresh_page, $nightly_rerun_info) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection');

        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $plagiarism_result_info = [];

        $course_path = $this->core->getConfig()->getCoursePath();
        foreach ($all_configurations as $gradeable) {
            $plagiarism_row = [];
            $plagiarism_row['title'] = $gradeable['g_title'];
            $plagiarism_row['id'] = $gradeable['g_id'];
            $plagiarism_row['config_id'] = $gradeable['g_config_version'];
            $plagiarism_row['duedate'] = $gradeable['g_grade_due_date']->format('F d Y H:i:s'); // TODO: think about the format of this date.  Using the format of the last run date for now.
            $plagiarism_row['delete_form_action'] = $this->core->buildCourseUrl(['plagiarism', 'gradeable', $plagiarism_row['id'], 'delete']) . "?config_id={$plagiarism_row["config_id"]}";
            if (file_exists(FileUtils::joinPaths($course_path, "lichen", $plagiarism_row['id'], $plagiarism_row['config_id'], "overall_ranking.txt"))) {
                $timestamp = date("F d Y H:i:s", filemtime(FileUtils::joinPaths($course_path, "lichen", $plagiarism_row['id'], $plagiarism_row['config_id'], "overall_ranking.txt")));
                $students = array_diff(scandir(FileUtils::joinPaths($course_path, "lichen", $plagiarism_row['id'], $plagiarism_row['config_id'], "users")), ['.', '..']);
                $submissions = 0;
                foreach ($students as $student) {
                    $submissions += count(array_diff(scandir(FileUtils::joinPaths($course_path, "lichen", $plagiarism_row['id'], $plagiarism_row['config_id'], "users", $student)), ['.', '..']));
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

            $plagiarism_row['night_rerun_status'] = "";//$nightly_rerun_info[$plagiarism_row['id']] ? "" : "checked";

            // lichen job in queue for this gradeable but processing not started
            if (file_exists(FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue", "lichen__{$semester}__{$course}__{$plagiarism_row['id']}_{$plagiarism_row['config_id']}.json"))) {
                $plagiarism_row['in_queue'] = true;
                $plagiarism_row['processing'] = false;
            }
            elseif (file_exists(FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue", "PROCESSING_lichen__{$semester}__{$course}__{$plagiarism_row['id']}__{$plagiarism_row['config_id']}.json"))) {
                // lichen job in processing stage for this gradeable but not completed
                $plagiarism_row['in_queue'] = true;
                $plagiarism_row['processing'] = true;
            }
            else {
                // no lichen job
                $ranking_file_path = FileUtils::joinPaths($course_path, "lichen", $plagiarism_row["id"], $plagiarism_row["config_id"], "overall_ranking.txt");
                if (!file_exists($ranking_file_path) || file_get_contents($ranking_file_path) == "") {
                    $plagiarism_row['matches_and_topmatch'] = "0 students matched, N/A top match";
                }
                else {
                    $content = trim(str_replace(["\r", "\n"], '', file_get_contents($ranking_file_path)));
                    $rankings = array_chunk(preg_split('/ +/', $content), 3);
                    $plagiarism_row['ranking_available'] = true;
                    $plagiarism_row['matches_and_topmatch'] = count($rankings) . " students matched, {$rankings[0][0]} top match";
                    $plagiarism_row['gradeable_link'] = $this->core->buildCourseUrl(['plagiarism', 'gradeable', $plagiarism_row['id']]) . "?config_id={$plagiarism_row["config_id"]}";
                }
                $plagiarism_row['rerun_plagiarism_link'] = $this->core->buildCourseUrl(["plagiarism", "gradeable", $plagiarism_row["id"], "rerun"]) . "?config_id={$plagiarism_row["config_id"]}";
                $plagiarism_row['edit_plagiarism_link'] = $this->core->buildCourseUrl(["plagiarism", "configuration", "edit"]) . "?gradeable_id={$plagiarism_row["id"]}&config_id={$plagiarism_row["config_id"]}";
                $plagiarism_row['nightly_rerun_link'] = $this->core->buildCourseUrl(["plagiarism", "gradeable", $plagiarism_row["id"], "nightly_rerun"]) . "?config_id={$plagiarism_row["config_id"]}";
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

    public function configurePlagiarismForm($new_or_edit, $config) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection', $this->core->buildCourseUrl(['plagiarism']));
        if ($new_or_edit === "edit") {
            $this->core->getOutput()->addBreadcrumb('Edit Gradeable Configuration');
        }
        else {
            $this->core->getOutput()->addBreadcrumb('Configure New Gradeable');
        }
        $this->core->getOutput()->addInternalCss("plagiarism.css");
        $this->core->getOutput()->enableMobileViewport();

        return $this->core->getOutput()->renderTwigTemplate('plagiarism/PlagiarismConfigurationForm.twig', [
            "new_or_edit" => $new_or_edit,
            "base_url" => $this->core->buildCourseUrl(['plagiarism', 'configuration']),
            "form_action_link" => $this->core->buildCourseUrl(['plagiarism', 'configuration', 'new']) . "?new_or_edit={$new_or_edit}&gradeable_id={$config["gradeable_id"]}&config_id={$config["config_id"]}",
            "csrf_token" => $this->core->getCsrfToken(),
            "plagiarism_link" => $this->core->buildCourseUrl(['plagiarism']),
            "config" => $config
        ]);
    }
}
