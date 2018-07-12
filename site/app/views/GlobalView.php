<?php

namespace app\views;

class GlobalView extends AbstractView {
    public function header($breadcrumbs, $css=array(), $js=array()) {
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

        //Allow courses to override css
        if ($this->core->getConfig()->isCourseLoaded() && file_exists($this->core->getConfig()->getCoursePath()."/config/override.css")) {
            $css[] = $this->core->getConfig()->getCoursePath()."/config/override.css";
        }

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
                }
            }
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
            "site_url" => $this->core->getConfig()->getSiteUrl()
        ]);
     }

    public function footer($runtime) {
        return $this->core->getOutput()->renderTwigTemplate("GlobalFooter.twig", [
            "runtime" => $runtime,
            "is_debug" => $this->core->getConfig()->isDebug(),
            "submitty_queries" => $this->core->getSubmittyDB() ? $this->core->getSubmittyDB()->getPrintQueries() : [],
            "course_queries" => $this->core->getCourseDB() ? $this->core->getCourseDB()->getPrintQueries() : [],
        ]);
    }

    public function invalidPage($page) {
        return $this->core->getOutput()->renderTwigTemplate("error/InvalidPage.twig", [
            "page" => $page
        ]);
    }
}
