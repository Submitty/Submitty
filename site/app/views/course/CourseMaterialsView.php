<?php

namespace app\views\course;

use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\Utils;


class CourseMaterialsView extends AbstractView {
    /**
     * @param User[] $students
     * @return string
     */
    public function listCourseMaterials($user_group) {

        $this->core->getOutput()->addBreadcrumb("Course Materials", $this->core->buildUrl(array('component' => 'grading', 'page' => 'course_materials', 'action' => 'view_course_materials_page')));
        function add_files($core, &$files, &$file_datas, &$file_release_dates, $expected_path, $json, $course_materials_array, $start_dir_name, $user_group) {
            $files[$start_dir_name] = array();
            $working_dirRoot = &$files[$start_dir_name];

            $student_access = ($user_group === 4);

            $arrlength = count($course_materials_array);

            $now_date_time = (new \DateTime('now', $core->getConfig()->getTimezone()));

            foreach($course_materials_array as $file) {

                $expected_file_path = FileUtils::joinPaths($expected_path, $file);

                // Check whether the file is shared to student or not
                // If shared, will add to courseMaterialsArray

                $releaseData = $now_date_time->format("Y-m-d H:i:sO");
                $isShareToOther = '0';
                if ($json == true){
                    if ( isset( $json[$expected_file_path] ) )
                    {
                        $isShareToOther = $json[$expected_file_path]['checked'];

                        $release_date = new \DateTime($json[$expected_file_path]['release_datetime'], $core->getConfig()->getTimezone());

                        if ($isShareToOther == '1' && $release_date > $now_date_time)
                            $isShareToOther = '0';

                        $releaseData  = $json[$expected_file_path]['release_datetime'];
                    }

                }

                if ($student_access && $isShareToOther === '0') {
                    continue; // skip this so we don't add to the courseMaterialsArray
                }

                $path = explode('/', $file);
                $working_dir = &$files[$start_dir_name];
                $filename = array_pop($path);
                foreach($path as $dir) {
                    if (!isset($working_dir[$dir])) {
                        $working_dir[$dir] = array();
                    }
                    $working_dir = &$working_dir[$dir];
                }

                $working_dir[$filename] = $expected_file_path;


                $file_datas[$expected_file_path] = $isShareToOther;
                $file_release_dates[$expected_file_path] = $releaseData;
            }
        }

        $submissions = array();
        $file_shares = array();
        $file_release_dates = array();

        $course_materials_array = array();

        //Get the expected course materials path and files
        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads");
        $expected_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
        $path_length = strlen($expected_path)+1;
        $course_materials_array = FileUtils::getAllFilesTrimSearchPath($expected_path, $path_length);
        $this->core->getOutput()->addInternalJs("drag-and-drop.js");
        usort($course_materials_array, 'strnatcasecmp');

        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
        $json = FileUtils::readJsonFile($fp);

        add_files($this->core, $submissions, $file_shares, $file_release_dates, $expected_path, $json, $course_materials_array, 'course_materials', $user_group);

        $instructor_permission = ($user_group === 1);

        return $this->core->getOutput()->renderTwigTemplate("course/CourseMaterials.twig", [
            "courseMaterialsArray" => $course_materials_array,
            'date_format' => 'Y-m-d H:i:sO',
            "folderPath" => $expected_path,
            "uploadFolderPath" => $upload_path,
            "submissions" => $submissions,
            "fileShares" => $file_shares,
            "fileReleaseDates" => $file_release_dates,
            "hasInstructorPermission" => $instructor_permission
        ]);
    }
}
