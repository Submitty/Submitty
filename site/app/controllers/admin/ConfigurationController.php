<?php

namespace app\controllers\admin;

use app\controllers\IController;
use app\libraries\Core;
use app\libraries\IniParser;
use app\libraries\Output;

class ConfigurationController implements IController {
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        switch ($_REQUEST['action']) {
            case 'view':
                $this->viewConfiguration();
                break;
            case 'update':
                $this->updateConfiguration();
                break;
            default:
                Output::showError("Invalid page request for controller");
                break;
        }
    }

    public function viewConfiguration() {
        $fields = array(
            'course_name'               => $this->core->getConfig()->getCourseName(),
            'default_hw_late_days'      => $this->core->getConfig()->getDefaultHwLateDays(),
            'default_student_late_days' => $this->core->getConfig()->getDefaultStudentLateDays(),
            'use_autograder'            => $this->core->getConfig()->getUseAutograder(),
            'generate_diff'             => $this->core->getConfig()->getGenerateDiff(),
            'zero_rubric_grades'        => $this->core->getConfig()->getZeroRubricGrades()
        );

        if (isset($_SESSION['request']['course_name'])) {
            $fields['course_name'] = htmlentities($_SESSION['request']['course_name']);
        }

        foreach (array('default_hw_late_days', 'default_student_late_days') as $key) {
            if (isset($_SESSION['request'][$key])) {
                $fields[$key] = intval($_SESSION['request'][$key]);
            }
        }

        foreach (array('use_autograder', 'generate_diff', 'zero_rubric_grades') as $key) {
            if (isset($_SESSION['request'][$key])) {
                $fields[$key] = ($_SESSION['request'][$key] == true) ? true : false;
            }
        }

        if (isset($_SESSION['request'])) {
            unset($_SESSION['request']);
        }

        Output::render_output(array('admin', 'Configuration'), 'viewConfig', $fields);
    }

    public function updateConfiguration() {
        if ($this->core->checkCsrfToken($_POST['csrf_token'])) {
            $_SESSION['messages']['errors'][] = "Invalid CSRF token. Try again.";
            $_SESSION['request'] = $_POST;
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                              'page' => 'configuration',
                                                              'action' => 'view')));
        }

        if (!isset($_POST['course_name']) || $_POST['course_name'] == "") {
            $_SESSION['messages']['errors'][] = "Course name can not be blank";
            $_SESSION['request'] = $_POST;
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                              'page' => 'configuration',
                                                              'action' => 'view')));
        }

        foreach (array('default_hw_late_days', 'default_student_late_days') as $key) {
            $_POST[$key] = (isset($_POST[$key])) ? intval($_POST[$key]) : 0;
        }

        foreach (array('use_autograder', 'generate_diff', 'zero_rubric_grades') as $key) {
            $_POST[$key] = (isset($_POST[$key]) && $_POST[$key] == "true") ? true : false;
        }

        $save_array = array(
            'database_details' => array(
                'database_name' => $this->core->getConfig()->getDatabaseName()
            ),
            'course_details' => array(
                'course_name'               => $_POST['course_name'],
                'default_hw_late_days'      => $_POST['default_hw_late_days'],
                'default_student_late_days' => $_POST['default_student_late_days'],
                'use_autograder'            => $_POST['use_autograder'],
                'generate_diff'             => $_POST['generate_diff'],
                'zero_rubric_grades'        => $_POST['zero_rubric_grades']
            )
        );

        $ini_file = implode("/", array($this->core->getConfig()->getConfigPath(),
            $this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse().'.ini'));

        IniParser::writeFile($ini_file, $save_array);
        $_SESSION['messages']['successes'][] = "Site configuration updated";
        $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                          'page' => 'configuration',
                                                          'action' => 'view')));
    }
}