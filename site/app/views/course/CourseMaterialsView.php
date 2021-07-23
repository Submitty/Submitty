<?php

namespace app\views\course;

use app\entities\course\CourseMaterial;
use app\libraries\FileUtils;
use app\views\AbstractView;

class CourseMaterialsView extends AbstractView {

    public function listCourseMaterials(array $course_materials_db) {
        $this->core->getOutput()->addInternalCss(FileUtils::joinPaths('fileinput.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->addBreadcrumb("Course Materials");
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->addInternalJs("drag-and-drop.js");

        $base_course_material_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'uploads', 'course_materials');
        $directories = [];
        $directory_priorities = [];

        /** @var CourseMaterial $course_material */
        foreach ($course_materials_db as $course_material) {
            if ($course_material->isDir()) {
                $rel_path = substr($course_material->getPath(), strlen($base_course_material_path) + 1);
                $directories[$rel_path] = substr_count($rel_path, "/");
                $directory_priorities[$course_material->getPath()] = $course_material->getPriority();
            }
        }
        array_multisort(array_values($directories), SORT_ASC, array_keys($directories), SORT_ASC, $directories);

        $final_structure = [];

        foreach ($directories as $directory => $num) {
            $dirs = explode("/", $directory);
            $cur_dir = &$final_structure;
            $folder_to_make = array_pop($dirs);
            foreach ($dirs as $dir) {
                $cur_dir = &$cur_dir[$dir];
            }
            $cur_dir[$folder_to_make] = [];
        }

        $date_now = new \DateTime();

        foreach ($course_materials_db as $course_material) {
            if ($course_material->isDir()) {
                continue;
            }
            if ($this->core->getUser()->getGroup() != 1 && $course_material->getReleaseDate() > $date_now) {
                continue;
            }
            $rel_path = substr($course_material->getPath(), strlen($base_course_material_path) + 1);
            $dirs = explode("/", $rel_path);
            $file_name = array_pop($dirs);
            if ($course_material->isLink()) {
                $file_name = $course_material->getUrlTitle();
            }
            $path_to_place = &$final_structure;
            foreach ($dirs as $dir) {
                $path_to_place = &$path_to_place[$dir];
            }
            $path_to_place[$file_name] = $course_material;
        }

        return $this->core->getOutput()->renderTwigTemplate("course/CourseMaterials.twig", [
            "user_group" => $this->core->getUser()->getGroup(),
            "user_section" => $this->core->getUser()->getRegistrationSection(),
            "reg_sections" => $this->core->getQueries()->getRegistrationSections(),
            "folderPath" => $base_course_material_path,
            "csrf_token" => $this->core->getCsrfToken(),
            "display_file_url" => $this->core->buildCourseUrl(['display_file']),
            "base_course_material_path" => $base_course_material_path,
            "directory_priorities" => $directory_priorities,
            "material_list" => $course_materials_db,
            "materials_exist" => count($course_materials_db) != 0,
            "date_format" => $this->core->getConfig()->getDateTimeFormat()->getFormat('date_time_picker'),
            "course_materials" => $final_structure
        ]);
    }
}
