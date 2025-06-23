<?php

namespace app\views\course;

use app\entities\course\CourseMaterial;
use app\libraries\FileUtils;
use app\views\AbstractView;
use app\libraries\DateUtils;

class CourseMaterialsView extends AbstractView {
    public function listCourseMaterials(array $course_materials_db) {
        $this->core->getOutput()->addSelect2WidgetCSSAndJs();
        $this->core->getOutput()->addInternalCss(FileUtils::joinPaths('fileinput.css'));
        $this->core->getOutput()->addInternalCss(FileUtils::joinPaths('course-materials.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->addBreadcrumb("Course Materials");
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->addInternalJs("drag-and-drop.js");
        $this->core->getOutput()->addInternalJs("course-materials.js");

        $base_course_material_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'uploads', 'course_materials');
        $directories = [];
        $directory_priorities = [];
        $seen = [];
        $folder_visibilities = [];
        $folder_ids = [];
        $links = [];
        $base_view_url = $this->core->buildCourseUrl(['course_material']);
        $beginning_of_time_date = DateUtils::BEGINNING_OF_TIME;
        /** @var CourseMaterial $course_material */
        foreach ($course_materials_db as $course_material) {
            $rel_path = substr($course_material->getPath(), strlen($base_course_material_path) + 1);
            if ($course_material->isDir()) {
                $directories[$rel_path] = $course_material;
                $directory_priorities[$course_material->getPath()] = $course_material->getPriority();
                $folder_ids[$course_material->getPath()] = $course_material->getId();
            }
            else {
                $path_parts = explode("/", $rel_path);
                $fin_path = "";
                foreach ($path_parts as $path_part) {
                    $fin_path .= rawurlencode($path_part) . '/';
                }
                $fin_path = substr($fin_path, 0, strlen($fin_path) - 1);
                $links[$course_material->getId()] = $base_view_url . "/" . $fin_path;
            }
        }
        $sort_priority = function (CourseMaterial $a, CourseMaterial $b) use ($base_course_material_path) {
            $rel_path_a = substr($a->getPath(), strlen($base_course_material_path) + 1);
            $rel_path_b = substr($b->getPath(), strlen($base_course_material_path) + 1);
            $dir_count_a = substr_count($rel_path_a, "/");
            $dir_count_b = substr_count($rel_path_b, "/");
            if ($dir_count_a > $dir_count_b) {
                return 1;
            }
            elseif ($dir_count_a < $dir_count_b) {
                return -1;
            }
            else {
                if ($a->getPriority() > $b->getPriority()) {
                    return 1;
                }
                elseif ($a->getPriority() < $b->getPriority()) {
                    return -1;
                }
                else {
                    return $a->getPath() > $b->getPath() ? 1 : -1;
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
            if (!$this->core->getUser()->accessGrading() && $course_material->getReleaseDate() > $date_now) {
                continue;
            }
            $rel_path = substr($course_material->getPath(), strlen($base_course_material_path) + 1);
            $dirs = explode("/", $rel_path);
            $file_name = array_pop($dirs);
            if ($course_material->isLink()) {
                $file_name = $course_material->getTitle() . $course_material->getPath();
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
            $path_to_place = array_slice($path_to_place, 0, $index, true) +
                [$file_name => $course_material] + array_slice($path_to_place, $index, null, true);
        }

        $this->removeEmptyFolders($final_structure);

        $this->setSeen($final_structure, $seen, $base_course_material_path);

        $this->setFolderVisibilities($final_structure, $folder_visibilities);
        $file_upload_limit_mb = $this->core->getConfig()->getCourseMaterialFileUploadLimitMb();

        $folder_paths = $this->compileAllFolderPaths($final_structure);
        $calendar_info = $this->setCourseMaterialMetadata($final_structure);

        return $this->core->getOutput()->renderTwigTemplate("course/CourseMaterials.twig", [
            "user_group" => $this->core->getUser()->getGroup(),
            "user_section" => $this->core->getUser()->getRegistrationSection(),
            "reg_sections" => $this->core->getQueries()->getRegistrationSections(),
            "csrf_token" => $this->core->getCsrfToken(),
            "display_file_url" => $this->core->buildCourseUrl(['display_file']),
            "seen" => $seen,
            "folder_visibilities" => $folder_visibilities,
            "base_course_material_path" => $base_course_material_path,
            "directory_priorities" => $directory_priorities,
            "material_list" => $course_materials_db,
            "materials_exist" => count($course_materials_db) != 0,
            "date_format" => $this->core->getConfig()->getDateTimeFormat()->getFormat('date_time_picker'),
            "course_materials" => $final_structure,
            "folder_ids" => $folder_ids,
            "links" => $links,
            "folder_paths" => $folder_paths,
            "gradeables" => $this->core->getQueries()->getAllElectronicGradeablesIds(),
            "current_gradeable" => null,
            "calendar_info" => $calendar_info,
            "beginning_of_time_date" => $beginning_of_time_date,
            "file_upload_limit_mb" => $file_upload_limit_mb
        ]);
    }

    private function setCourseMaterialMetadata(array &$course_materials, string $full_path = ""): array {
        $metadata = [];

        foreach ($course_materials as $name => $course_material) {
            $current_path = $full_path === '' ? '/' . $name : $full_path . '/' . $name;

            if (is_array($course_material)) {
                $metadata[$current_path] = [
                    'associatedDate' => 'none',
                    'isOnCalendar' => 'none',
                    'gradeable' => 'none'
                ];

                $metadata = array_merge($metadata, $this->setCourseMaterialMetadata($course_material, $current_path));
            }
            else {
                $metadata[$current_path] = [
                    'associatedDate' => $course_material->getCalendarDate() ? $course_material->getCalendarDate()->format("Y-m-d") : 'none',
                    'isOnCalendar' => $course_material->isOnCalendar() ? 'true' : 'none',
                    'gradeable' => $course_material->getGradeable() ?? 'none'
                ];
            }
        }
        return $metadata;
    }

    private function removeEmptyFolders(array &$course_materials): bool {
        $is_empty = true;
        foreach ($course_materials as $path => $course_material) {
            if (is_array($course_material) && $this->removeEmptyFolders($course_material)) {
                unset($course_materials[$path]);
            }
            else {
                $is_empty = false;
            }
        }
        return $is_empty;
    }

    private function setSeen(array $course_materials, array &$seen, string $cur_path): bool {
        $has_unseen = false;
        foreach ($course_materials as $path => $course_material) {
            /** @var CourseMaterial $course_material */
            if (is_array($course_material)) {
                if ($this->setSeen($course_material, $seen, FileUtils::joinPaths($cur_path, $path))) {
                    $seen[FileUtils::joinPaths($cur_path, $path)] = false;
                    $has_unseen = true;
                }
                else {
                    $seen[FileUtils::joinPaths($cur_path, $path)] = true;
                }
            }
            else {
                $seen[$course_material->getPath()] = $course_material->userHasViewed($this->core->getUser()->getId());
                $reg_sec = $this->core->getUser()->getRegistrationSection();
                if ($reg_sec !== null && !$course_material->isSectionAllowed($reg_sec)) {
                    $seen[$course_material->getPath()] = true;
                }
                if (!$seen[$course_material->getPath()]) {
                    $has_unseen = true;
                }
            }
        }
        return $has_unseen;
    }

    /**
     * Recurses through folders and decides whether they should appear to students.
     *
     * @param array $course_materials - Dictionary: path name => CourseMaterial.
     * @param array $folder_visibilities -  Dictionary: path name => bool. True if visible to students, false if not.
     */
    private function setFolderVisibilities(array $course_materials, array &$folder_visibilities): void {
        foreach ($course_materials as $path => $course_material) {
            if (is_array($course_material)) {
                // Found root-level folder; this folder could be invisible
                $this->setFolderVisibilitiesR($course_material, $folder_visibilities, "root/$path");
            }
        }
    }

    /**
     * Recurses through folders and decides whether they should appear to students.
     *
     * @param array $course_materials - Dictionary: path name => CourseMaterial.
     * @param array $folder_visibilities - Dictionary: path name => bool. True if visible to students, false if not.
     * @param string $current_path - Path to the folder that $course_materials represents.
     */
    private function setFolderVisibilitiesR(array $course_materials, array &$folder_visibilities, string $current_path): void {
        $cur_visibility = false;
        foreach ($course_materials as $name => $course_material) {
            if (is_array($course_material)) {
                // Material is actually folder
                $sub_path = "$current_path/$name";

                $this->setFolderVisibilitiesR($course_material, $folder_visibilities, $sub_path);

                // At least one file visible in this folder
                if ($folder_visibilities[$sub_path]) {
                    $cur_visibility = true;
                }
            }
            else {
                // Material is file
                if (!$course_material->isHiddenFromStudents()) {
                    $cur_visibility = true;
                }
            }
        }

        $folder_visibilities[$current_path] = $cur_visibility;
    }

    /**
     * Recurses through folders and compiles an array of all the paths to folders.
     *
     * @param array<mixed> $course_materials - Dictionary: path name => CourseMaterial.
     * @return array<string> List of folders paths.
     */
    private function compileAllFolderPaths(array $course_materials): array {
        $folder_paths = [];
        $this->compileAllFolderPathsR($course_materials, $folder_paths, "");
        return $folder_paths;
    }

    /**
     * Recurses through folders and compiles an array of all the paths to folders.
     * Helper recursive function.
     *
     * @param array<mixed> $course_materials - Dictionary: path name => CourseMaterial.
     * @param array<string>  $folder_paths - List we append
     * @param string $full_path - Current path we are examining files in.
     */
    private function compileAllFolderPathsR(
        array $course_materials,
        array &$folder_paths,
        string $full_path
    ): void {
        foreach ($course_materials as $name => $course_material) {
            if (is_array($course_material)) {
                $inner_full_path = "";
                if ($full_path === '') {
                    $inner_full_path = $name;
                }
                else {
                    $inner_full_path = $full_path . '/' . $name;
                }
                array_push($folder_paths, $inner_full_path);
                $this->compileAllFolderPathsR($course_material, $folder_paths, $inner_full_path);
            }
        }
    }
}
