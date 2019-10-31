<?php

namespace app\views;
use app\models\Breadcrumb;

class GlobalView extends AbstractView {
    public function header($breadcrumbs, $wrapper_urls, $sidebar_buttons, $notifications_info, $css, $js, $duck_img) {
        $messages = [];
        foreach (array('error', 'notice', 'success') as $type) {
            foreach ($_SESSION['messages'][$type] as $key => $error) {
                $messages[] = [
                    "type" => $type,
                    "key" => $key,
                    "error" => $error
                ];

                unset($_SESSION['messages'][$type][$key]);
            }
        }

        $course_name = ucwords(strtolower($this->core->getFullCourseName()));
        $page_name = end($breadcrumbs)->getTitle();
        // We assume that if there is no page breadcrumb (only course), we are on gradeables
        if ($course_name == ucwords(strtolower($page_name))) {
            $page_name = "Gradeables";
        }

        $page_title = "Submitty";
        if ($this->core->getUser() === null) {
            $page_title = "Submitty Login";
        } else if ($this->core->getConfig()->isCourseLoaded()) {
            $page_title = "Submitty " . $course_name . " " . $page_name;
        }

        $config_data = json_decode(file_get_contents("/usr/local/submitty/config/submitty.json"), true);

        return $this->core->getOutput()->renderTwigTemplate("GlobalHeader.twig", [
            "messages" => $messages,
            "css" => $css,
            "js" => $js,
            "page_title" => $page_title,
            "sidebar_buttons" => $sidebar_buttons,
            "breadcrumbs" => $breadcrumbs,
            "user_first_name" => $this->core->getUser() ? $this->core->getUser()->getDisplayedFirstName() : "",
            "base_url" => $this->core->getConfig()->getBaseUrl(),
            "course_url" => $this->core->buildCourseUrl(),
            "notifications_info" => $notifications_info,
            "wrapper_enabled" => $this->core->getConfig()->wrapperEnabled(),
            "wrapper_urls" => $wrapper_urls,
            "system_message" => $this->core->getConfig()->getSystemMessage(),
            "csrf_token" => $this->core->getCsrfToken(),
            "enable_banner" => $config_data['duck_special_effects'],
            "duck_img" => $duck_img
        ]);
    }

    public function footer($runtime, $wrapper_urls, $footer_links) {
        return $this->core->getOutput()->renderTwigTemplate("GlobalFooter.twig", [
            "runtime" => $runtime,
            "wrapper_enabled" => $this->core->getConfig()->wrapperEnabled(),
            "is_debug" => $this->core->getConfig()->isDebug(),
            "submitty_queries" => $this->core->getConfig()->isDebug() && $this->core->getSubmittyDB() ? $this->core->getSubmittyDB()->getPrintQueries() : [],
            "course_queries" => $this->core->getConfig()->isDebug() && $this->core->getCourseDB() ? $this->core->getCourseDB()->getPrintQueries() : [],
            "wrapper_urls" => $wrapper_urls,
            "latest_tag" => $this->core->getConfig()->getLatestTag(),
            "latest_commit" => $this->core->getConfig()->getLatestCommit(),
            "footer_links" => $footer_links
        ]);
    }
}
