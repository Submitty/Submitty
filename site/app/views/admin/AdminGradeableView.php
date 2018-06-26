<?php

namespace app\views\admin;

use app\models\GradeableComponent;
use app\views\AbstractView;
use app\models\AdminGradeable;

class AdminGradeableView extends AbstractView {

    // Converts \DateTime objects to strings in the format we want
    private static function DateTimeToString(&$date) {
        if(gettype($date) === 'string') return;
        $date = $date->format('Y-m-d H:i:s');
    }
    /**
     * The one and only...
     */
	public function show_add_gradeable($type_of_action, AdminGradeable $admin_gradeable, $nav_tab = 0) {

	    // TODO: all of this should be moved to the controller when it gets overhauled

        $action           = "new"; //decides how the page's data is displayed
        $submit_text      = "Submit";
        $label_message    = "";
        $gradeables_array = array();

        // Make sure the dates are strings
        self::DateTimeToString($admin_gradeable->g_ta_view_start_date);
        self::DateTimeToString($admin_gradeable->eg_submission_open_date);
        self::DateTimeToString($admin_gradeable->eg_submission_due_date);
        self::DateTimeToString($admin_gradeable->g_grade_start_date);
        self::DateTimeToString($admin_gradeable->g_grade_released_date);
        self::DateTimeToString($admin_gradeable->eg_team_lock_date);

        // Default autograding config is upload-only
        if($type_of_action === 'add') {
            $admin_gradeable->setEgConfigPath('/usr/local/submitty/more_autograding_examples/upload_only/config');
        }
        
        //makes an array of gradeable ids for javascript
        foreach ($admin_gradeable->getTemplateList() as $g_id_title) {
            array_push($gradeables_array, $g_id_title['g_id']);
        }

        // Generate an array by user_group for the graders
        $graders_by_usertype = $admin_gradeable->getGradersFromUsertypes();
        $graders = array();
        foreach($graders_by_usertype as $type=>$graders_of_type) {
            $graders[$type] = array();
            foreach($graders_of_type as $grader) {
                $graders[$type][$grader['user_id']] = $grader;
            }
        }

        // For each grader with sections assigned to them, add their
        //  sections to the array generated above
        foreach($admin_gradeable->getGradersAllSection() as $grader) {
            //parses the sections from string "{1, 2, 3, 4}" to a php array [1,2,3,4]
            $sections = $grader['sections'];
            $sections = ltrim($sections, '{');
            $sections = rtrim($sections, '}');
            $sections = explode(',', $sections);

            $graders[$grader['user_group']][$grader['user_id']]['sections'] = $sections;
        }

        // if the user is editing a gradeable instead of adding
        if ($type_of_action === "edit") {
            $action        = "edit";
            $submit_text   = "Save Changes";
            $label_message = ($admin_gradeable->getHasGrades()) ? "<span style='color: red;'>(Grading has started! Edit Questions At Own Peril!)</span>" : "";
        }

        return $this->core->getOutput()->renderTwigTemplate('admin/admin_gradeable/AdminGradeableBase.twig', [
            "submit_url"      => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'upload_' . $action . '_gradeable')),
            "js_gradeables_array"=> json_encode($gradeables_array),
            "admin_gradeable" => $admin_gradeable,
            "label_message"   => $label_message,
            "action"          => $action,
            "submit_text"     => $submit_text,
            "nav_tab"         => $nav_tab,
            "semester"        => $_GET['semester'],
            "course"          => $_GET['course'],

            // Graders Page Specific
            "all_graders"    => $graders
        ]);
    }
    
    public function show_edit_gradeable(AdminGradeable $admin_gradeable) {
        return "";
    }
}
