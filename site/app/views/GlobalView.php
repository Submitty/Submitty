<?php

namespace app\views;

class GlobalView extends AbstractView {
    public function header($breadcrumbs, $wrapper_urls, $sidebar_buttons, $notifications_info, $css=array(), $js=array()) {
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

        $pageTitle = $this->core->getConfig()->isCourseLoaded() ? $this->core->getFullCourseName() : "Submitty";

        return $this->core->getOutput()->renderTwigTemplate("GlobalHeader.twig", [
            "messages" => $messages,
            "css" => $css,
            "js" => $js,
            "page_title" => $pageTitle,
            "sidebar_buttons" => $sidebar_buttons,
            "breadcrumbs" => $breadcrumbs,
            "user_first_name" => $this->core->getUser() ? $this->core->getUser()->getDisplayedFirstName() : "",
            "base_url" => $this->core->getConfig()->getBaseUrl(),
            "site_url" => $this->core->getConfig()->getSiteUrl(),
            "course_url" => $this->core->buildNewCourseUrl(),
            "notifications_info" => $notifications_info,
            "wrapper_enabled" => $this->core->getConfig()->wrapperEnabled(),
            "wrapper_urls" => $wrapper_urls,
            "system_message" => $this->core->getConfig()->getSystemMessage()
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
            "footer_links" => $footer_links
        ]);
    }

    public function invalidPage($page) {
        return $this->core->getOutput()->renderTwigTemplate("error/InvalidPage.twig", [
            "page" => $page
        ]);
    }
}
