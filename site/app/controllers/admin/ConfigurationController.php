<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;

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

    public function viewConfiguration()
    {
        $fields = array(
            'course_name'                    => $this->core->getConfig()->getCourseName(),
            'course_home_url'                => $this->core->getConfig()->getCourseHomeUrl(),
            'default_hw_late_days'           => $this->core->getConfig()->getDefaultHwLateDays(),
            'default_student_late_days'      => $this->core->getConfig()->getDefaultStudentLateDays(),
            'zero_rubric_grades'             => $this->core->getConfig()->shouldZeroRubricGrades(),
            'upload_message'                 => $this->core->getConfig()->getUploadMessage(),
            'keep_previous_files'            => $this->core->getConfig()->keepPreviousFiles(),
            'display_rainbow_grades_summary' => $this->core->getConfig()->displayRainbowGradesSummary(),
            'display_custom_message'         => $this->core->getConfig()->displayCustomMessage(),
            'course_email'                   => $this->core->getConfig()->getCourseEmail(),
            'vcs_base_url'                   => $this->core->getConfig()->getVcsBaseUrl(),
            'vcs_type'                       => $this->core->getConfig()->getVcsType(),
            'forum_enabled'                  => $this->core->getConfig()->isForumEnabled(),
            'regrade_enabled'                => $this->core->getConfig()->isRegradeEnabled(),
            'regrade_message'                => $this->core->getConfig()->getRegradeMessage(),
            'private_repository'             => $this->core->getConfig()->getPrivateRepository(),
            'room_seating_gradeable_id'      => $this->core->getConfig()->getRoomSeatingGradeableId()
        );

        if (isset($_SESSION['request'])) {
            foreach (array('upload_message', 'course_email', 'regrade_message') as $key) {
                if (isset($_SESSION['request'][$key])) {
                    $fields[$key] = htmlentities($_SESSION['request'][$key]);
                }
            }

            foreach (array('default_hw_late_days', 'default_student_late_days') as $key) {
                if (isset($_SESSION['request'][$key])) {
                    $fields[$key] = intval($_SESSION['request'][$key]);
                }
            }

            foreach (array('zero_rubric_grades', 'keep_previous_files', 'display_rainbow_grades_summary',
                         'display_custom_message', 'regrade_enabled') as $key) {
                if (isset($_SESSION['request'][$key])) {
                    $fields[$key] = ($_SESSION['request'][$key] == true) ? true : false;
                }
            }

            unset($_SESSION['request']);
        }

        $gradeable_seating_options = $this->getGradeableSeatingOptions();
        $config_url = $this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper'));

        $this->core->getOutput()->renderOutput(array('admin', 'Configuration'), 'viewConfig', $fields, $gradeable_seating_options, $config_url);
    }

    public function updateConfiguration() {
        if (!$this->core->checkCsrfToken()) {
            return $this->core->getOutput()->renderJsonFail('Invalid CSRF token');
        }

        if(!isset($_POST['name'])) {
            return $this->core->getOutput()->renderJsonFail('Name of config value not provided');
        }
        $name = $_POST['name'];

        if(!isset($_POST['entry'])) {
            return $this->core->getOutput()->renderJsonFail('Name of config entry not provided');
        }
        $entry = $_POST['entry'];

        if($name === "room_seating_gradeable_id") {
            $gradeable_seating_options = $this->getGradeableSeatingOptions();
            $gradeable_ids = array();
            foreach($gradeable_seating_options as $option) {
                $gradeable_ids[] = $option['g_id'];
            }
            if(!in_array($entry, $gradeable_ids)) {
                return $this->core->getOutput()->renderJsonFail('Invalid gradeable chosen for seating');
            }
        }
        else if(in_array($name, array('default_hw_late_days', 'default_student_late_days'))) {
            if(!ctype_digit($entry)) {
                return $this->core->getOutput()->renderJsonFail('Must enter a number for this field');
            }
            $entry = intval($entry);
        }
        else if(in_array($name, array('zero_rubric_grades', 'keep_previous_files', 'display_rainbow_grades_summary',
                                      'display_custom_message', 'forum_enabled', 'regrade_enabled'))) {
            $entry = $entry === "true" ? true : false;
        }
        else if($name === 'upload_message') {
            $entry = nl2br($entry);
        }

        $config_ini = $this->core->getConfig()->readCourseIni();
        if(!isset($config_ini['course_details'][$name])) {
            return $this->core->getOutput()->renderJsonFail('Not a valid config name');
        }
        $config_ini['course_details'][$name] = $entry;
        $this->core->getConfig()->saveCourseIni(['course_details' => $config_ini['course_details']]);

        return $this->core->getOutput()->renderJsonSuccess();
    }

    private function getGradeableSeatingOptions() {
        $gradeable_seating_options = $this->core->getQueries()->getAllGradeablesIdsAndTitles();

        $seating_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'seating');

        $gradeable_seating_options = array_filter($gradeable_seating_options, function($seating_option) use($seating_dir) {
            return is_dir(FileUtils::joinPaths($seating_dir, $seating_option['g_id']));
        });

        $empty_option = [[
            'g_id' => "",
            'g_title' => "--None--"
        ]];

        return $empty_option + $gradeable_seating_options;
    }
}
