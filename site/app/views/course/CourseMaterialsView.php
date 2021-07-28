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
                $directories[$rel_path] = $course_material;
                $directory_priorities[$course_material->getPath()] = $course_material->getPriority();
            }
        }
        $sort_priority = function (CourseMaterial $a, CourseMaterial $b) use ($base_course_material_path) {
            $rel_path_a = substr($a->getPath(), strlen($base_course_material_path) + 1);
            $rel_path_b = substr($b->getPath(), strlen($base_course_material_path) + 1);
            $dir_count_a = substr_count($rel_path_a, "/");
            $dir_count_b = substr_count($rel_path_b, "/");
            if ($dir_count_a > $dir_count_b) {
                return true;
            }
            elseif ($dir_count_a < $dir_count_b) {
                return false;
            }
            else {
                if ($a->getPriority() > $b->getPriority()) {
                    return true;
                }
                elseif ($a->getPriority() < $b->getPriority()) {
                    return false;
                }
                else {
                    return $a->getPath() > $b->getPath();
                }
            }
        };
        uasort($directories, $sort_priority);

        $final_structure = [];

        foreach ($directories as $rel_path => $directory) {
            $dirs = explode("/", $rel_path);
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
            $path = "";
            foreach ($dirs as $dir) {
                $path_to_place = &$path_to_place[$dir];
                $path = FileUtils::joinPaths($path, $dir);
            }
            $index = 0;
            foreach ($path_to_place as $key => $value) {
                if (is_array($value)) {
                    $priority = $directories[FileUtils::joinPaths($path, $key)]->getPriority();
                }
                else {
                    $priority = $value->getPriority();
                }
                if ($course_material->getPriority() > $priority) {
                    $index++;
                }
                elseif ($course_material->getPriority() === $priority) {
                    if (is_array($value)) {
                        $index++;
                    }
                    else {
                        if ($course_material->getPath() > $value->getPath()) {
                            $index++;
                        }
                    }
                }
                else {
                    break;
                }
            }
            $path_to_place = array_merge(
                array_slice($path_to_place, 0, $index),
                [$file_name => $course_material],
                array_slice($path_to_place, $index)
            );
        }

        return $this->core->getOutput()->renderTwigTemplate("course/CourseMaterials.twig", [
            "user_group" => $this->core->getUser()->getGroup(),
            "user_section" => $this->core->getUser()->getRegistrationSection(),
            "reg_sections" => $this->core->getQueries()->getRegistrationSections(),
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
