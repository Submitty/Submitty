<?php

namespace app\views;

class GlobalView extends AbstractView {
    public function header($breadcrumbs, $wrapper_urls, $css=array(), $js=array()) {
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

        $navURLs = [];
        if ($this->core->getConfig()->isCourseLoaded() && $this->core->userLoaded()) {
            if ($this->core->getUser()->accessGrading()) {
                if ($this->core->getUser()->accessAdmin()) {
                    $navURLs[] = [
                        "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'configuration', 'action' => 'view')),
                        "title" => "Course Settings"
                    ];
                    $navURLs[] = [
                        "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'users')),
                        "title" => "Students"
                    ];
                    $navURLs[] = [
                        "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'graders')),
                        "title" => "Graders"
                    ];
                    $navURLs[] = [
                        "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'rotating_sections')),
                        "title" => "Setup Sections"
                    ];
                    $navURLs[] = [
                        "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => 'view_late')),
                        "title" => "Late Days Allowed"
                    ];
                    $navURLs[] = [
                        "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => 'view_extension')),
                        "title" => "Excused Absence Extensions"
                    ];
                    $navURLs[] = [
                        "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'reports', 'action' => 'reportpage')),
                        "title" => "Grade Summaries / CSV Report"
                    ];
                    $navURLs[] = [
                        "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism')),
                        "title" => "Lichen Plagiarism Detection [WIP]"
                    ];
                    $navURLs[] = [
                        "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper')),
                        "title" => "Site Theme"
                    ];
                }
            }
        }

        $notifications_info = null;
        if ($this->core->getUser() && $this->core->getConfig()->isCourseLoaded()) {
            $notifications_info = $this->core->getQueries()->getUnreadNotificationsCount($this->core->getUser()->getId(), null);
        }

        return $this->core->getOutput()->renderTwigTemplate("GlobalHeader.twig", [
            "messages" => $messages,
            "css" => $css,
            "js" => $js,
            "page_title" => $pageTitle,
            "nav_urls" => $navURLs,
            "breadcrumbs" => $breadcrumbs,
            "user_first_name" => $this->core->getUser() ? $this->core->getUser()->getDisplayedFirstName() : "",
            "base_url" => $this->core->getConfig()->getBaseUrl(),
            "site_url" => $this->core->getConfig()->getSiteUrl(),
            "notifications_info" => $notifications_info,
            "wrapper_enabled" => $this->core->getConfig()->wrapperEnabled(),
            "wrapper_urls" => $wrapper_urls
        ]);
     }

    public function footer($runtime, $wrapper_urls) {
        return $this->core->getOutput()->renderTwigTemplate("GlobalFooter.twig", [
            "runtime" => $runtime,
            "wrapper_enabled" => $this->core->getConfig()->wrapperEnabled(),
            "is_debug" => $this->core->getConfig()->isDebug(),
            "submitty_queries" => $this->core->getSubmittyDB() ? $this->core->getSubmittyDB()->getPrintQueries() : [],
            "course_queries" => $this->core->getCourseDB() ? $this->core->getCourseDB()->getPrintQueries() : [],
            "wrapper_urls" => $wrapper_urls
        ]);
    }

    public function invalidPage($page) {
        return $this->core->getOutput()->renderTwigTemplate("error/InvalidPage.twig", [
            "page" => $page
        ]);
    }
}
