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

    public function header($breadcrumbs, $css=array()) {
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
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css" />
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/server.css" />
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/diff-viewer.css" />
HTML;
    foreach($css as $css_ref){
        $return .= <<<HTML
        <link rel="stylesheet" type="text/css" href="{$css_ref}" />   
HTML;
    }
    
    $return .= <<<HTML
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
                $ta_base_url = $this->core->getConfig()->getTABaseUrl();
                $semester = $this->core->getConfig()->getSemester();
                $course = $this->core->getConfig()->getCourse();
                $return .= <<<HTML
<div id="nav">
    <ul>
HTML;
                if($this->core->getUser()->accessAdmin()) {
                    $return .= <<<HTML
            
                    <li>
                        <a href="{$ta_base_url}/account/admin-hw-report.php?course={$course}&semester={$semester}&this=Generate%20Homework%20Report">Generate Homework Report</a>
                    </li>
                    <li>
                        <a href="{$ta_base_url}/account/admin-grade-summaries.php?course={$course}&semester={$semester}&this=Generate%20Grade%20Summaries">Generate Grade Summaries</a>
                    </li>
                    <li>
                        <a href="{$ta_base_url}/account/admin-csv-report.php?course={$course}&semester={$semester}&this=Generate%20CSV%20Report">Generate CSV Report</a>
                    </li>

                    <!--<li><a href="{$this->core->buildUrl(array('component' => 'admin',
                                                              'page' => 'gradeables',
                                                              'action' => 'list'))}">Manage Gradeables</a></li>-->
                    <li>
                        <a href="{$ta_base_url}/account/admin-gradeables.php?course={$course}&semester={$semester}&this=Manage%20Gradeables">Manage Gradeables</a>
                    </li>
                    
                    <!--<li><a href="{$this->core->buildUrl(array('component' => 'admin',
                                                              'page' => 'users',
                                                              'action' => 'listStudents'))}">View Students</a></li>-->
                    <!-- TODO Add these to a drop down -->
                        <li>
                            <a href="{$ta_base_url}/account/admin-students.php?course={$course}&semester={$semester}&this=View%20Students">View Students</a>
                        </li>
                        
                        <li>
                            <a href="{$ta_base_url}/account/admin-users.php?course={$course}&semester={$semester}&this=View%20Users">View Users</a>
                        </li>
                        
                       <li>
                            <a href="{$ta_base_url}/account/admin-classlist.php?course={$course}&semester={$semester}&this=Upload%20ClassList">Upload ClassList</a>
                        </li>
                        
                        <li>
                            <a href="{$ta_base_url}/account/admin-rotating-sections.php?course={$course}&semester={$semester}&this=Setup%20Rotating%20Sections">Setup Rotating Sections</a>
                        </li>
                        
                        <li>
                            <a href="{$ta_base_url}/account/admin-latedays.php?course={$course}&semester={$semester}&this=Late%200Days%20Course">Late Days Course</a>
                        </li>
                        
                        <li>
                            <a href="{$ta_base_url}/account/admin-latedays-exceptions.php?course={$course}&semester={$semester}&this=Late%200Days%20Student">Late Days Student</a>
                        </li>
                        
                    <!-- -->
                    
                    <!--<li><a href="{$this->core->buildUrl(array('component' => 'admin', 
                                                              'page' => 'configuration', 
                                                              'action' => 'view'))}">Class Configuration</a></li>-->

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
          <img height=80 width=230 src="http://submitty.org/images/submitty_logo.png">
<!--
        <h1>Submitty</h1>
        <h2>Rensselaer Center for Open Source</h2>
-->
    </div>
    </a>
    <div id="header-text">
        <h2>
HTML;
        if ($this->core->userLoaded()) {
            $logout_link = $this->core->buildUrl(array('component' => 'authentication', 'page' => 'logout'));
            $first_name = $this->core->getUser()->getFirstName();
            $id = $this->core->getUser()->getId();
            $return .= <<<HTML
            <span id="login">Hello {$first_name} (<span id="login-id">{$id}</span>)</span> (<a id='logout' href='{$logout_link}'>Logout</a>)
HTML;
        }
        else {
            $return .= <<<HTML
            <span id="login-guest">Hello Guest</span>
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