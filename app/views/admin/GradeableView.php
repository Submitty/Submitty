<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\Utils;

class GradeableView extends AbstractView {
    public function uploadConfigForm($target_dir, $all_files, $gradeable_id, $inuse_config) {
        $this->core->getOutput()->addBreadcrumb("upload config", $this->core->buildCourseUrl(['autograding_config']));
        $course = $this->core->getConfig()->getCourse();

        return $this->core->getOutput()->renderTwigTemplate("admin/UploadConfigForm.twig", [
            "all_files" => $all_files,
            "target_dir" => $target_dir,
            "course" => $course,
            "gradeable_id" => $gradeable_id,
            "inuse_config" => $inuse_config,
            "upload_url" => $this->core->buildCourseUrl(['autograding_config', 'upload']),
            "delete_url" => $this->core->buildCourseUrl(['autograding_config', 'delete']),
            "rename_url" => $this->core->buildCourseUrl(['autograding_config', 'rename']),
            "display_url" => $this->core->buildCourseUrl(['display_file']),
            "back_url" => $gradeable_id !== ''
                ? $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'update?nav_tab=1'])
                : '',
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }

    public function AdminGradeableEditPeersForm($gradeable) {
        return $this->core->getOutput()->renderTwigTemplate("admin/admin_gradeable/AdminGradeableEditPeersForm.twig", [
            'pair_grader_pairs' => json_encode($this->core->getQueries()->getPeerGradingAssignment($gradeable->getId())),
            'student_autofill' => Utils::getAutoFillData($this->core->getQueries()->getAllUsers()),
            'peers_submit_url' => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'update_peer_assignment']),
            'csrf_token' => $this->core->getCsrfToken()
        ]);
    }

    public function AdminGradeableAddPeersForm($gradeable) {
        return $this->core->getOutput()->renderTwigTemplate("admin/admin_gradeable/AdminGradeableAddPeersForm.twig", [
            'pair_grader_pairs' => json_encode($this->core->getQueries()->getPeerGradingAssignment($gradeable->getId())),
            'student_autofill' => Utils::getAutoFillData($this->core->getQueries()->getAllUsers()),
            'peer_grader_submit_url' => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'new_peer_grader']),
            'csrf_token' => $this->core->getCsrfToken()
        ]);
    }
}
