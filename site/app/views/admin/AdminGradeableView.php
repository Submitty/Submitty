<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\models\AdminGradeable;

class AdminGradeableView extends AbstractView {
    /**
     * Shows creation part
     */
	public function show_add_gradeable($type_of_action, AdminGradeable $admin_gradeable) {

        $action           = "add"; //decides how the page's data is displayed
        $button_string    = "Add";
        $submit_text      = "Submit";
        $label_message    = "";
        $gradeables_array = array();

        foreach ($admin_gradeable->getTemplateList() as $g_id_title) { //makes an array of gradeable ids for javascript
            array_push($gradeables_array, $g_id_title['g_id']);
        }

        // //if the user is editing a gradeable instead of adding
        if ($type_of_action === "edit") {
            $action        = "edit";
            $button_string = "Save changes to";
            $submit_text   = "Save Changes";
            $label_message = ($admin_gradeable->getHasGrades()) ? "<span style='color: red;'>(Grading has started! Edit Questions At Own Peril!)</span>" : "";
        }

        return $this->core->getOutput()->renderTwigTemplate('admin/admin_gradeable/AdminGradeableBase.twig', [
            "submit_url"      => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => $action)),
            "js_gradeables_array"=> json_encode($gradeables_array),
            "admin_gradeable" => $admin_gradeable,
            "label_message"   => $label_message,
            "action"          => $action,
            "submit_text"     => $submit_text,
            "nav_tab"         => "0",    // TODO: allow the request of a particular tab (so save-and-continue on first page doesn't jump back to first page)
        ]);
    }
    
    public function show_edit_gradeable(AdminGradeable $admin_gradeable) {
        return "";
    }
}
