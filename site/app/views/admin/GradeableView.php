<?php

namespace app\views\admin;

use app\libraries\FileUtils;
use app\views\AbstractView;

class GradeableView extends AbstractView {
    public function uploadConfigForm($target_dir, $all_files) {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $build_script_output_file = "/var/local/submitty/courses/" . $semester . "/" . $course . "/build_script_output.txt";
        $contents = "";

        $has_last_build_output = file_exists($build_script_output_file);
        if ($has_last_build_output) {
            $contents = file_get_contents($build_script_output_file);
        }

        return $this->core->getOutput()->renderTwigTemplate("admin/UploadConfigForm.twig", [
            "all_files" => $all_files,
            "target_dir" => $target_dir,
            "has_last_build_output" => $has_last_build_output,
            "course" => $course,
            "build_script_output_file" => $build_script_output_file,
            "contents" => $contents,
        ]);
    }
}
