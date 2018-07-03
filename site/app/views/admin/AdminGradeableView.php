<?php

namespace app\views\admin;

use app\libraries\FileUtils;
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
        $this->core->getOutput()->addBreadcrumb("add/edit gradeable");

	    // TODO: all of this should be moved to the controller when it gets overhauled

        $action           = "new"; //decides how the page's data is displayed
        $submit_text      = "Submit";
        $label_message    = "";
        $title_prefix     = "Create New Gradeable";
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
            $graders[$grader['user_group']][$grader['user_id']]['sections'] = $grader['sections'];
        }

        // if the user is editing a gradeable instead of adding
        if ($type_of_action === "edit") {
            $title_prefix = "Editing Gradeable";
            $action        = "edit";
            $submit_text   = "Save Changes";
            $label_message = ($admin_gradeable->getHasGrades()) ? "<span style='color: red;'>(Grading has started! Edit Questions At Own Peril!)</span>" : "";
        }

        $saved_path = $admin_gradeable->eg_config_path;
        //This helps determine which radio button to check when selecting config.
        //Default option is 3, which means the user has to specify the path.
        $which_config_option = 3;
        //These are hard coded default config options.
        $default_config_paths = ["/usr/local/submitty/more_autograding_examples/upload_only/config",
                          "/usr/local/submitty/more_autograding_examples/iclicker_upload/config",
                          "/usr/local/submitty/more_autograding_examples/left_right_exam_seating/config",
                          "/usr/local/submitty/more_autograding_examples/pdf_exam/config",
                          "/usr/local/submitty/more_autograding_examples/test_notes_upload/config",
                          "/usr/local/submitty/more_autograding_examples/test_notes_upload_3page/config"];
        foreach($default_config_paths as $path){
            //If this happens then select the first radio button "Using Default"
            if($path == $saved_path) $which_config_option = 0;
        }

        $uploaded_configs_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload");
        $all_uploaded_configs = FileUtils::getAllFiles($uploaded_configs_dir);
        $all_uploaded_config_paths = array();
        foreach($all_uploaded_configs as $file){
            $all_uploaded_config_paths[] = $file['path'];
            //If this happens then select the second radio button "Using Uploaded"
            if($file['path'] == $saved_path) $which_config_option = 1;
        }
        $config_repo_name = $this->core->getConfig()->getPrivateConfigRepository();
        $repository_config_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), $config_repo_name);
        $all_repository_configs = FileUtils::getAllFiles($repository_config_dir);
        $all_repository_config_paths = array();
        foreach($all_repository_configs as $file){
            $all_repository_config_paths[] = $file['path'];
            //If this happens then select the second radio button "Use Private Repository"
            if($file['path'] == $saved_path) $which_config_option = 2;
        }

        $cmake_out_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "build", $admin_gradeable->g_id, "log_cmake_output.txt");
        $cmake_output = is_file($cmake_out_dir) ? file_get_contents($cmake_out_dir) : null;
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
            "modal_title"     => $title_prefix,

            // Graders Page Specific
            "all_graders"    => $graders,
            //Repository name
            "config_repo_name"        => $config_repo_name,
            //All the uploaded config paths
            "all_repository_config_paths"    => $all_repository_config_paths,
            "all_uploaded_config_paths"      => $all_uploaded_config_paths,
            "default_config_paths"           => $default_config_paths,
            "which_config_option"            => $which_config_option,
            //build outputs
            "cmake_output"            => htmlentities($cmake_output)
        ]);
    }
    
    public function show_edit_gradeable(AdminGradeable $admin_gradeable) {
        return "";
    }
}
