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
                        "title" => "Plagiarism Detection"
                    ];
                }
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("GlobalHeader.twig", [
            "messages" => $messages,
            "css" => $css,
            "js" => $js,
            "pageTitle" => $pageTitle,
            "navURLs" => $navURLs,
            "breadcrumbs" => $breadcrumbs
        ]);
     }

    public function footer($runtime) {
        $return = <<<HTML
    <div id="push"></div>
</div>
<div id="footer">
    <span id="copyright">&copy; 2017 RPI | An <a href="https://rcos.io" target="_blank">RCOS project</a></span>|
    <span id="github">
        <a href="https://github.com/Submitty/Submitty" target="blank" title="Fork us on Github">
            <i class="fa fa-github fa-lg"></i>
        </a>
    </span>
HTML;
    if ($this->core->getConfig()->isDebug()) {
            $return .= <<<HTML
    <a href="#" onClick="togglePageDetails();">Show Page Details</a>
HTML;
    }
    $return .= <<<HTML
</div>
HTML;
        if ($this->core->getConfig()->isDebug()) {
            $return .= <<<HTML
<div id='page-info'>
    Runtime: {$runtime}<br /><br />
    <h3>Site Details</h3>
    Total Submitty Details: {$this->core->getSubmittyDB()->getQueryCount()}<br /><br />
    Submitty Queries:<br /> {$this->core->getSubmittyDB()->getPrintQueries()}
HTML;
            if ($this->core->getConfig()->isCourseLoaded()) {
                $return .= <<<HTML
    <h3>Course Details</h3>
    Total Course Queries: {$this->core->getCourseDB()->getQueryCount()}<br /><br />
    Course Queries: <br /> {$this->core->getCourseDB()->getPrintQueries()}
HTML;
            }
        $return .= <<<HTML
</div>
HTML;
        }
        $return .= <<<HTML
</body>
</html>

HTML;

        return $return;
    }

    public function invalidPage($page) {
        return <<<HTML
<div class="box">
The page {$page} does not exist. Please try again.
</div>
HTML;

    }
}
