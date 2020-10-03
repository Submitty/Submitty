<?php

namespace app\views\course;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\Utils;

class CourseMaterialsView extends AbstractView {
    /**
     * @param User $user
     */
    public function listCourseMaterials($user) {
        $this->core->getOutput()->addInternalCss(FileUtils::joinPaths('fileinput.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->addBreadcrumb("Course Materials");
        $this->core->getOutput()->enableMobileViewport();
        $user_group = $user->getGroup();
        $user_section = $user->getRegistrationSection();
        $add_files = function (Core $core, &$files, &$file_release_dates, $expected_path, $json, $course_materials_array, $folders, $start_dir_name, $user_group, &$in_dir, $fp, &$file_sections, &$hide_from_students, &$external_link) {
            $files[$start_dir_name] = [];
            $student_access = ($user_group === 4);
            $now_date_time = $core->getDateTimeNow();
            $no_json = [];

            foreach ($course_materials_array as $file) {
                if (in_array($file, $folders)) {
                    $path = explode('/', $file);
                    $working_dir = &$files[$start_dir_name];
                    $filename = array_pop($path);

                    foreach ($path as $dir) {
                        if (!isset($working_dir[$dir])) {
                            $working_dir[$dir] = [];
                        }
                        $working_dir = &$working_dir[$dir];
                    }
                    $working_dir[$filename] = [];
                    continue;
                }

                $expected_file_path = FileUtils::joinPaths($expected_path, $file);

                array_push($in_dir, $expected_file_path);

                // Check whether the file is shared to student or not
                // If shared, will add to courseMaterialsArray

                $releaseData = $now_date_time->format("Y-m-d H:i:sO");
                $isMaterialReleased = '0';
                if ($json == true) {
                    if (isset($json[$expected_file_path])) {
                        $isMaterialReleased = '1';

                        if (isset($json[$expected_file_path]['sections'])) {
                            $file_sections[$expected_file_path] = $json[$expected_file_path]['sections'];
                        }
                        $release_date = DateUtils::parseDateTime($json[$expected_file_path]['release_datetime'], $core->getConfig()->getTimezone());
                        if (isset($json[$expected_file_path]['hide_from_students'])) {
                            $hide_from_students[$expected_file_path] = $json[$expected_file_path]['hide_from_students'];
                        }
                        if (isset($json[$expected_file_path]['external_link']) && $json[$expected_file_path]['external_link'] === true) {
                            $contents = json_decode(file_get_contents($expected_file_path));
                            $external_link[$expected_file_path] = [$contents->url, $contents->name];
                        }

                        if ($release_date > $now_date_time) {
                            $isMaterialReleased = '0';
                        }

                        $releaseData  = $json[$expected_file_path]['release_datetime'];
                    }
                    else {
                        //fill with upload time for new files add all files to json when uploaded
                        $isMaterialReleased = '1';
                        $release_date = $json['release_time'];
                        if (isset($json[$expected_file_path]['hide_from_students'])) {
                            $hide_from_students[$expected_file_path] = $json[$expected_file_path]['hide_from_students'];
                        }
                        if (isset($json[$expected_file_path]['external_link']) && $json[$expected_file_path]['external_link'] === true) {
                            $contents = json_decode(file_get_contents($expected_file_path));
                            $external_link[$expected_file_path] = [$contents->url, $contents->name];
                        }
                        $json[$expected_file_path]['release_datetime'] = $release_date;
                        if (isset($json[$expected_file_path]['sections'])) {
                            $file_sections[$expected_file_path] = $json[$expected_file_path]['sections'];
                        }
                        $releaseData = $json[$expected_file_path]['release_datetime'];
                    }
                }
                else {
                    $ex_file_path = [];
                    $isMaterialReleased = '1';
                    $date = $now_date_time->format("Y-m-d H:i:sO");
                    $date = substr_replace($date, "9999", 0, 4);
                    $ex_file_path['release_datetime'] = $date;
                    $ex_file_path['hide_from_students'] = "on";
                    $ex_file_path['external_link'] = false;
                    $releaseData = $ex_file_path['release_datetime'];
                    $no_json[$expected_file_path] = $ex_file_path;
                }

                // Share with student only when course material is released
                if ($student_access && $isMaterialReleased === '0') {
                    continue;
                }

                $path = explode('/', $file);
                $working_dir = &$files[$start_dir_name];
                $filename = array_pop($path);

                foreach ($path as $dir) {
                    if (!isset($working_dir[$dir])) {
                        $working_dir[$dir] = [];
                    }
                    $working_dir = &$working_dir[$dir];
                }
                $working_dir[$filename] = $expected_file_path;

                if ($releaseData == $now_date_time->format("Y-m-d H:i:sO")) {
                    //for uploaded files that have had no manually set date to be set to never and maintained as never
                    $releaseData = substr_replace($releaseData, "9999", 0, 4);
                    $json[$expected_file_path]['release_datetime'] = $releaseData;
                }
                $file_release_dates[$expected_file_path] = DateUtils::convertTimeStamp($this->core->getUser(), $releaseData, $this->core->getConfig()->getDateTimeFormat()->getFormat('date_time_picker'));
            }

            if ($json == false) {
                FileUtils::writeJsonFile($fp, $no_json);
            }
            $can_write = is_writable($fp);
            if (!$can_write) {
                $core->addErrorMessage("This json does not have write permissions, and therefore you cannot change the release date. Please change the permissions or contact someone who can.");
            }
        };

        $submissions = [];
        $file_release_dates = [];
        $in_dir = [];
        $file_sections = [];
        $hide_from_students = [];
        $external_link = [];
        $priorities = [];
        $folders = [];
        //Get the expected course materials path and files
        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads");
        $expected_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
        $path_length = strlen($expected_path) + 1;
        $course_materials_array = FileUtils::getAllFilesTrimSearchPath($expected_path, $path_length);
        $this->core->getOutput()->addInternalJs("drag-and-drop.js");
        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
        $json = FileUtils::readJsonFile($fp);
        $sort_default = 0;

        //Compound the priorities of directories inside of folders to preserve order
        foreach ($course_materials_array as $key => &$material) {
            $dirs = explode('/', $material);
            array_pop($dirs);
            $curr_path = "";

            if (!isset($priorities[$material])) {
                $priorities[$material] = 0;
            }
            $path = FileUtils::joinPaths($expected_path, $material);
            $priorities[$material] += isset($json[$path]['sort_priority']) ? $json[$path]['sort_priority'] : $sort_default;

            foreach ($dirs as $dir) {
                $curr_path = $curr_path . $dir;
                $path = FileUtils::joinPaths($expected_path, $curr_path);
                    $priorities[$material] += isset($json[$path]['sort_priority']) ? $json[$path]['sort_priority'] : $sort_default;
                if (!in_array($curr_path, $course_materials_array)) {
                    array_push($course_materials_array, $curr_path);
                    array_push($folders, $curr_path);
                }
                $curr_path = $curr_path . "/";
            }
        }

        //Sort the files/folders by prioriy then alphabetical order
        $sort_priotity = function ($a, $b) use ($priorities) {
            if ($priorities[$b] == $priorities[$a]) {
                if (strtolower($a) < strtolower($b)) {
                    return -1;
                }
                else {
                    return 1;
                }
            }
            return $priorities[$a] - $priorities[$b];
        };
        uasort($course_materials_array, $sort_priotity);

        //Restore the priorities for each file/folder
        foreach ($priorities as $key => &$priority) {
            $path = FileUtils::joinPaths($expected_path, $key);
            $priorities[$key] = isset($json[$path]['sort_priority']) ? $json[$path]['sort_priority'] : $sort_default;
        }
        
        $add_files($this->core, $submissions, $file_release_dates, $expected_path, $json, $course_materials_array, $folders, 'course_materials', $user_group, $in_dir, $fp, $file_sections, $hide_from_students, $external_link);
        //Check if user has permissions to access page (not instructor when no course materials available)
        if ($user_group !== 1 && count($course_materials_array) == 0) {
            // nothing to view
            $this->core->addErrorMessage("You have no permission to access this page");
            $this->core->redirect($this->core->buildCourseUrl());
            return;
        }

        $max_size = Utils::returnBytes(ini_get('upload_max_filesize'));
        $max_size_string = Utils::formatBytes("MB", $max_size) . " (" . Utils::formatBytes("KB", $max_size) . ")";
        $reg_sections = $this->core->getQueries()->getRegistrationSections();

        return $this->core->getOutput()->renderTwigTemplate("course/CourseMaterials.twig", [
            "courseMaterialsArray" => $course_materials_array,
            'date_format' => 'Y-m-d H:i:s',
            "folderPath" => $expected_path,
            "uploadFolderPath" => $upload_path,
            "submissions" => $submissions,
            "priorities" => $priorities,
            "fileReleaseDates" => $file_release_dates,
            "userGroup" => $user_group,
            "inDir" => $in_dir,
            "csrf_token" => $this->core->getCsrfToken(),
            "delete_url" => $this->core->buildCourseUrl(["course_materials", "delete"]),
            "delete_folder_url" => $this->core->buildCourseUrl(["course_materials", "delete_folder"]),
            "max_size_string" => $max_size_string,
            "display_file_url" => $this->core->buildCourseUrl(['display_file']),
            "user_section" => $user_section,
            "reg_sections" => $reg_sections,
            "file_sections" => $file_sections,
            "hide_from_students" => $hide_from_students,
            "external_link" => $external_link
        ]);
    }
}
