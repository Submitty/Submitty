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

    public function header() {
        $errors = "";
        if (count($_SESSION['messages']['errors']) > 0) {
            $errors = <<<HTML
<div class='message' id="error-messages">
    <div class="inner-message alert alert-error">
            <a class="fa fa-times message-close" onClick="removeBox('error-messages');"></a>

HTML;
            foreach ($_SESSION['messages']['errors'] as $key => $error) {
                $errors .= "{$error}<br />\n";
                unset($_SESSION['messages']['errors'][$key]);
            }
            $errors .= <<<HTML
    </div>
</div>

HTML;
        }

        $alerts = "";
        if (count($_SESSION['messages']['alerts']) > 0) {
            $alerts = <<<HTML
<div class='message' id="alert-messages">
    <div class="inner-message alert alert-notice">
            <a class="fa fa-times" style="float: right; margin-right: -20px; cursor: pointer;" href="#"></a>

HTML;
            foreach ($_SESSION['messages']['alerts'] as $key => $alert) {
                $alerts .= "{$alert}<br />\n";
                unset($_SESSION['messages']['alerts'][$key]);
            }
            $alerts .= <<<HTML
    </div>
</div>

HTML;
        }

        $return = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>{$this->core->getConfig()->getCourseName()} Submissions</title>
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}public/css/server.css" />
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.1/css/font-awesome.min.css" />
    <script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}public/js/jquery.min.js"></script>
    <script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}public/js/server.js"></script>
</head>
<body>
{$errors}
{$alerts}
<div id="container">

HTML;
        if ($this->core->getUser()->accessGrading()) {
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
        <li class="dropdown"><a href="#">Manage Gradeables</a>
            <div class="dropdown-content">
                <a href="{$this->core->buildUrl(array('component' => 'admin',
                                                      'page' => 'assignments',
                                                      'action' => 'list'))}">Manage Assignments</a>
                <a href="#">Manage Labs</a>
                <a href="#">Manage Tests</a>
            </div>
        </li>
        <li><a href="#">View Students</a></li>
        <li><a href="#">View Users</a></li>
        <li><a href="#">Class Configuration</a></li>
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
    <span style="float: right; margin-top: 5px; margin-right: 10px;">
        Hello {$this->core->getUser()->getDetail('user_id')}
    </span>
</div>
<div id="nav-clear"></div>

HTML;
        }

        $return .= <<<HTML
<div id="header">
    <h1 id="header-text">Homework Submissions for {$this->core->getConfig()->getCourseName()}</h1>
</div>


HTML;
        return $return;
    }

    public function footer($runtime) {
        return <<<HTML
    <div id="push"></div>
</div>
<div id="footer">
    <span id="copyright">&copy; 2016 RPI</span>
    <a href="https://github.com/RCOS-Grading-Server/HWserver"><div class="fa fa-github fa-lg"></div></a>
</div>

<div id='page-info'>
    Total Queries: {$this->core->getDatabase()->totalQueries()}<br />
    Runtime: {$runtime}<br />
    Queries: <br /> {$this->core->getDatabase()->getQueries()}
</div>

</body>
</html>

HTML;
    }

    public function invalidPage($page) {
        return <<<HTML
<div class="box">
The page {$page} does not exist. Please try again.
</div>
HTML;

    }
}