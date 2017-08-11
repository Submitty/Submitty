<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\IniParser;
use app\libraries\Output;

class ConfigurationController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'view':
                $this->viewConfiguration();
                break;
            case 'update':
                $this->updateConfiguration();
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller");
                break;
        }
    }

    public function viewConfiguration() {
        $fields = array(
            'course_name'               => $this->core->getConfig()->getCourseName(),
            'course_home_url'           => $this->core->getConfig()->getCourseHomeUrl(),
            'default_hw_late_days'      => $this->core->getConfig()->getDefaultHwLateDays(),
            'default_student_late_days' => $this->core->getConfig()->getDefaultStudentLateDays(),
            'zero_rubric_grades'        => $this->core->getConfig()->shouldZeroRubricGrades(),
            'upload_message'            => $this->core->getConfig()->getUploadMessage(),
            'keep_previous_files'       => $this->core->getConfig()->keepPreviousFiles(),
            'display_iris_grades_summary' => $this->core->getConfig()->displayIrisGradesSummary(),
            'display_custom_message'      => $this->core->getConfig()->displayCustomMessage(),
            'course_email'              => $this->core->getConfig()->getCourseEmail(),
            'vcs_base_url'              => $this->core->getConfig()->getVcsBaseUrl(),
            'vcs_type'                  => $this->core->getConfig()->getVcsType()
        );

        foreach (array('course_name', 'upload_message', 'course_email') as $key) {
            if (isset($_SESSION['request'][$key])) {
                $fields[$key] = htmlentities($_SESSION['request'][$key]);
            }
        }

        foreach (array('default_hw_late_days', 'default_student_late_days') as $key) {
            if (isset($_SESSION['request'][$key])) {
                $fields[$key] = intval($_SESSION['request'][$key]);
            }
        }

        foreach (array('zero_rubric_grades', 'keep_previous_files', 'display_iris_grades_summary', 'display_custom_message') as $key) {
            if (isset($_SESSION['request'][$key])) {
                $fields[$key] = ($_SESSION['request'][$key] == true) ? true : false;
            }
        }

        if (isset($_SESSION['request'])) {
            unset($_SESSION['request']);
        }

        $this->core->getOutput()->renderOutput(array('admin', 'Configuration'), 'viewConfig', $fields);
    }

    public function updateConfiguration() {
        if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $core->addErrorMessage("Invalid CSRF token. Try again.");
            $_SESSION['request'] = $_POST;
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                              'page' => 'configuration',
                                                              'action' => 'view')));
        }

        if (!isset($_POST['course_name']) || $_POST['course_name'] == "") {
            $core->addErrorMessage("Course name can not be blank");
            $_SESSION['request'] = $_POST;
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                              'page' => 'configuration',
                                                              'action' => 'view')));
        }

        foreach (array('default_hw_late_days', 'default_student_late_days') as $key) {
            $_POST[$key] = (isset($_POST[$key])) ? intval($_POST[$key]) : 0;
        }

        foreach (array('zero_rubric_grades', 'keep_previous_files', 'display_iris_grades_summary', 'display_custom_message') as $key) {
            $_POST[$key] = (isset($_POST[$key]) && $_POST[$key] == "true") ? true : false;
        }

        $save_array = array(
            'hidden_details' => $this->core->getConfig()->getHiddenDetails(),
            'course_details' => array(
                'course_name'               => $_POST['course_name'],
                'course_home_url'           => $_POST['course_home_url'],
                'default_hw_late_days'      => $_POST['default_hw_late_days'],
                'default_student_late_days' => $_POST['default_student_late_days'],
                'zero_rubric_grades'        => $_POST['zero_rubric_grades'],
                'upload_message'            => nl2br($_POST['upload_message']),
                'keep_previous_files'       => $_POST['keep_previous_files'],
                'display_iris_grades_summary' => $_POST['display_iris_grades_summary'],
                'display_custom_message'      => $_POST['display_custom_message'],
                'course_email'                => $_POST['course_email'],
                'vcs_base_url'              => $_POST['vcs_base_url'],
                'vcs_type'                  => $_POST['vcs_type']
            )
        );
        
        IniParser::writeFile($this->core->getConfig()->getCourseIniPath(), $save_array);
        $core->addSuccessMessage("Site configuration updated");
        $this->core->redirect($this->core->buildUrl(array('component' => 'admin',
                                                          'page' => 'configuration',
                                                          'action' => 'view')));
    }
}