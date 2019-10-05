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
     * @param User[] $students
     * @return string
     */
    public function listCourseMaterials($user_group) {
        $this->core->getOutput()->addInternalCss(FileUtils::joinPaths('fileinput.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->addBreadcrumb("Course Materials");
        $add_files = function (Core $core, &$files, &$file_datas, &$file_release_dates, $expected_path, $json, $course_materials_array, $start_dir_name, $user_group, &$in_dir,$fp) {
            $files[$start_dir_name] = array();
            $student_access = ($user_group === 4);
            $now_date_time = $core->getDateTimeNow();
            $no_json = array();

            foreach($course_materials_array as $file) {

                $expected_file_path = FileUtils::joinPaths($expected_path, $file);

                array_push($in_dir,$expected_file_path);

                // Check whether the file is shared to student or not
                // If shared, will add to courseMaterialsArray

                $releaseData = $now_date_time->format("Y-m-d H:i:sO");
                $isShareToOther = '0';
                if ($json == true){
                    if ( isset( $json[$expected_file_path] ) )
                    {
                        $json[$expected_file_path]['checked'] = '1';
                        $isShareToOther = $json[$expected_file_path]['checked'];

                       $release_date = DateUtils::parseDateTime($json[$expected_file_path]['release_datetime'], $core->getConfig()->getTimezone());

                       if ($isShareToOther == '1' && $release_date > $now_date_time)
                            $isShareToOther = '0';

                        $releaseData  = $json[$expected_file_path]['release_datetime'];
                    }
                    else{
                        //fill with upload time for new files add all files to json when uploaded
                        $json[$expected_file_path]['checked'] = '1';
                        $isShareToOther = $json[$expected_file_path]['checked'];
                        $release_date = $json['release_time'];
                        $json[$expected_file_path]['release_datetime'] = $release_date;
                        $releaseData = $json[$expected_file_path]['release_datetime'];
                    }

                }
                else{

                    $ex_file_path = $expected_file_path;
                    $ex_file_path = array();
                    $ex_file_path['checked'] = '1';
                    $isShareToOther = $ex_file_path['checked'];
                    $date = $now_date_time->format("Y-m-d H:i:sO");
                    $date=substr_replace($date,"9999",0,4);
                    $ex_file_path['release_datetime'] = $date;
                    $releaseData = $ex_file_path['release_datetime'];
                    $no_json[$expected_file_path] = $ex_file_path;

                }

                if ($student_access && $isShareToOther === '0') {
                    continue; // skip this so don't add to the courseMaterialsArray
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

                if( $releaseData == $now_date_time->format("Y-m-d H:i:sO")){
                    //for uploaded files that have had no manually set date to be set to never and maintained as never
                    //also permission set to yes
                    $releaseData=substr_replace($releaseData,"9999",0,4);
                    $json[$expected_file_path]['checked']='1';
                    $json[$expected_file_path]['release_datetime']= $releaseData;
                }
                $file_release_dates[$expected_file_path] = $releaseData;
            }

            if($json == false){
                FileUtils::writeJsonFile($fp,$no_json);
            }
            $can_write =is_writable($fp);
            if(!$can_write){
               $core->addErrorMessage("This json does not have write permissions, and therefore you cannot change the release date. Please change the permissions or contact someone who can.");
            }
        };

        $submissions = array();
        $file_shares = array();
        $file_release_dates = array();
        $in_dir = array();

        //Get the expected course materials path and files
        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads");
        $expected_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
        $path_length = strlen($expected_path)+1;
        $course_materials_array = FileUtils::getAllFilesTrimSearchPath($expected_path, $path_length);
        $this->core->getOutput()->addInternalJs("drag-and-drop.js");
        //Sort the files/folders in alphabetical order
        usort($course_materials_array, 'strnatcasecmp');

        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
        $json = FileUtils::readJsonFile($fp);

        $add_files($this->core, $submissions, $file_shares, $file_release_dates, $expected_path, $json, $course_materials_array, 'course_materials', $user_group,$in_dir,$fp);

        //Check if user has permissions to access page (not instructor when no course materials available)
        if ($user_group !== 1 && count($course_materials_array) == 0) {
            // nothing to view
            $this->core->addErrorMessage("You have no permission to access this page");
            $this->core->redirect($this->core->buildCourseUrl());
            return;
        }

        $max_size = Utils::returnBytes(ini_get('upload_max_filesize'));
        $max_size_string = Utils::formatBytes("MB", $max_size ) . " (" . Utils::formatBytes("KB", $max_size) . ")";

        $server_time = DateUtils::getServerTimeJson($this->core);

        return $this->core->getOutput()->renderTwigTemplate("course/CourseMaterials.twig", [
            "courseMaterialsArray" => $course_materials_array,
            'date_format' => 'Y-m-d H:i:sO',
            "folderPath" => $expected_path,
            "uploadFolderPath" => $upload_path,
            "submissions" => $submissions,
            "fileShares" => $file_shares,
            "fileReleaseDates" => $file_release_dates,
            "userGroup" => $user_group,
            "inDir" => $in_dir,
            "csrf_token" => $this->core->getCsrfToken(),
            "delete_url" => $this->core->buildCourseUrl(["course_materials", "delete"]),
            "delete_folder_url" => $this->core->buildCourseUrl(["course_materials", "delete_folder"]),
            "max_size_string" => $max_size_string,
            'server_time' => $server_time,
            "display_file_url" => $this->core->buildCourseUrl(['display_file'])
        ]);
    }
}
