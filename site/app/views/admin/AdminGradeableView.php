<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\models\AdminGradeable;

class AdminGradeableView extends AbstractView {

    public function show_add_gradeable(AdminGradeable $admin_gradeable, $action) {
        return $this->core->getOutput()->renderTwigTemplate('admin/admin_gradeable/AdminGradeableBase.twig', [
            'submit_url'        => $this->core->buildUrl([
                    'component' => 'admin',
                    'page'      => 'admin_gradeable',
                    'action'    => 'upload_new_gradeable'
                ]),
            'admin_gradeable' => $admin_gradeable,
            'gradeable'       => $admin_gradeable->getGradeable(),
            'action'          => $action
        ]);
    }

    public function show_edit_gradeable(AdminGradeable $admin_gradeable, $nav_tab, $semester, $course) {
        $label_message = ($admin_gradeable->getHasGrades()) ?
            "<span style='color: red;'>(Grading has started! Edit Questions At Own Peril!)</span>" : '';

        return $this->core->getOutput()->renderTwigTemplate('admin/admin_gradeable/AdminGradeableBase.twig', [
            'admin_gradeable' => $admin_gradeable,
            'gradeable'       => $admin_gradeable->getGradeable(),
            'label_message'   => $label_message,
            'action'          => 'edit',
            'nav_tab'         => $nav_tab,
            'semester'        => $semester,
            'course'          => $course,
            'date_format'     => 'Y-m-d H:i:sO'
        ]);
    }
}
