<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\plagiarism\PlagiarismUtils;

class PlagiarismView extends AbstractView {

    public function plagiarismMainPage($plagiarism_result_info, $refresh_page) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection');
        $this->core->getOutput()->addInternalCss("plagiarism.css");
        $this->core->getOutput()->enableMobileViewport();

        return $this->core->getOutput()->renderTwigTemplate('plagiarism/Plagiarism.twig', [
            "refresh_page" => $refresh_page,
            "plagiarism_results_info" => $plagiarism_result_info,
            "csrf_token" => $this->core->getCsrfToken(),
            "new_plagiarism_config_link" => $this->core->buildCourseUrl(['plagiarism', 'configuration', 'new']),
            "refreshLichenMainPageLink" => $this->core->buildCourseUrl(['plagiarism', 'check_refresh']),
            "semester" => $this->core->getConfig()->getSemester(),
            "course" => $this->core->getConfig()->getCourse()
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
            "term_course_gradeable" => "{$this->core->getConfig()->getSemester()}__{$this->core->getConfig()->getCourse()}__{$gradeable_id}",
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
