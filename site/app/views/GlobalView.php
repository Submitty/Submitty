<?php

namespace app\views;

use app\libraries\Core;
use app\models\User;

class GlobalView {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function header($breadcrumbs) {
        $messages = <<<HTML
<div id='messages'>

HTML;
        foreach (array('error', 'notice', 'success') as $type) {
            foreach ($_SESSION['messages'][$type] as $key => $error) {
                $messages .= <<<HTML
    <div id='{$type}-{$key}' class="inner-message alert alert-{$type}">
        <a class="fa fa-times message-close" onClick="removeMessagePopup('{$type}-{$key}');"></a>
        <i class="fa fa-times-circle"></i> {$error}
    </div>

HTML;
                unset($_SESSION['messages'][$type][$key]);
            }
        }
        $messages .= <<<HTML
</div>

HTML;

        if (file_exists($this->core->getConfig()->getCoursePath()."/override.css")) {
            $override_css = "<link rel='stylesheet' type='text/css' href='{$this->core->getConfig()->getCoursePath()}/override.css' />";
        }
        else {
            $override_css = '';
        }

        $is_dev = ($this->core->userLoaded() && $this->core->getUser()->isDeveloper()) ? "true" : "false";
        $return = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>{$this->core->getFullCourseName()}</title>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.1/css/font-awesome.min.css" />
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/server.css" />
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/diff-viewer.css" />
    {$override_css}
    <script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/jquery.min.js"></script>
    <script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/diff-viewer.js"></script>
    <script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/server.js"></script>
    <script type="text/javascript">
        var is_developer = {$is_dev};
    </script>
</head>
<body>
{$messages}
<div id="container">

HTML;
        if ($this->core->getUser() != null) {
            if($this->core->getUser()->accessGrading()) {
                $return .= <<<HTML
<div id="nav">
    <ul>
        <li><a href="{$this->core->buildUrl(array('component' => 'submission', 
                                                  'page' => 'homework'))}">Submit</a></li>
        <li><a href="#">Grade Assignments</a></li>
        <li><a href="#">Grade Labs</a></li>
        <li><a href="#">Grade Tests</a></li>

HTML;
                if($this->core->getUser()->accessAdmin()) {
                    $return .= <<<HTML
        <li><a href="#">Report Tools</a></li>
        <li><a href="{$this->core->buildUrl(array('component' => 'admin',
                                                  'page' => 'gradeables',
                                                  'action' => 'list'))}">Manage Gradeables</a>
        </li>
        <li><a href="{$this->core->buildUrl(array('component' => 'admin',
                                                  'page' => 'users',
                                                  'action' => 'listStudents'))}">View Students</a></li>
        <li><a href="#">View Users</a></li>
        <li><a href="{$this->core->buildUrl(array('component' => 'admin', 
                                                  'page' => 'configuration', 
                                                  'action' => 'view'))}">Class Configuration</a></li>
        <li><a href="#">View Orphans</a></li>

HTML;
                    if($this->core->getUser()->isDeveloper()) {
                        $return .= <<<HTML
        <li><a href="#" onClick="togglePageDetails();">Show Page Details</a></li>

HTML;
                    }
                }
                $return .= <<<HTML
    </ul>
</div>
<div id="nav-clear"></div>

HTML;
            }
        }

        $return .= <<<HTML
<div id="header">
    <a href="http://submitty.org">
    <div id="logo-text">
        <h1>Submitty</h1>
        <h2>Rensselaer Center for Open Source</h2>
    </div>
    </a>
    <div id="header-text">
        <h2>
HTML;
        if ($this->core->userLoaded()) {
            $logout_link = $this->core->buildUrl(array('component' => 'login', 'page' => 'logout'));
            $first_name = $this->core->getUser()->getFirstName();
            $id = $this->core->getUser()->getId();
            $return .= <<<HTML
            Hello {$first_name} ({$id}) (<a id='logout' href='{$logout_link}'>Logout</a>)
HTML;
        }
        else {
            $return .= <<<HTML
            Hello Guest
HTML;
        }
        $return .= <<<HTML
        </h2>
        <h2>
        {$breadcrumbs}
        </h2>
    </div>
</div>


HTML;
        return $return;
    }

    public function footer($runtime) {
        $return = <<<HTML
    <div id="push"></div>
</div>
<div id="footer">
    <span id="copyright">&copy; 2016 RPI</span>
    <a href="https://github.com/RCOS-Grading-Server/HWserver" target="blank" title="Fork us on Github">
        <i class="fa fa-github fa-lg"></i>
    </a>
</div>
HTML;
        if ($this->core->userLoaded() && $this->core->getUser()->isDeveloper()) {
            $return .= <<<HTML
<div id='page-info'>
    Total Queries: {$this->core->getDatabase()->totalQueries()}<br />
    Runtime: {$runtime}<br />
    Queries: <br /> {$this->core->getDatabase()->getQueries()}
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