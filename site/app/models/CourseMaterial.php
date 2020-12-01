<?php

namespace app\models;

use app\exceptions\FileNotFoundException;
use app\exceptions\MalformedDataException;
use app\libraries\Core;

class CourseMaterial extends AbstractModel {

    /**
     * Determine if a course materials file has been released
     *
     * @param Core $core Application core
     * @param string $path_to_file Full path to the file that we would like to check is released or not
     * @return bool Indicates if the file has been released or not
     * @throws FileNotFoundException The course_materials_file_data.json was not found
     */
    public static function isMaterialReleased(Core $core, string $path_to_file) {
        // Before students are allowed to view or download a course materials file we must ensure
        // it has been released.  To return true the file metadata must be found in course_materials_file_data.json
        // and the current time must be greater than the release_datetime

        // Get path to the meta data json
        $meta_data_json = $core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';

        if (!is_file($meta_data_json)) {
            throw new FileNotFoundException('Unable to locate the course_materials_file_data.json file');
        }

        $meta_data = json_decode(file_get_contents($meta_data_json));

        // If file path does not exist as key in $meta_data then it has not been released
        if (!property_exists($meta_data, $path_to_file)) {
            return false;
        }
        else {
            $current_time = new \DateTime('now');
            $release_time = new \DateTime($meta_data->$path_to_file->release_datetime);

            // Ensure release time obtained from file was parsed correctly into a DateTime object
            if ($release_time === false) {
                throw new MalformedDataException("An error occurred parsing the file's release time data.");
            }

            // If current time is greater than release time return true, else return false
            return $current_time > $release_time;
        }
    }

    /**
     * Determine if a user is allowed to access a course materials file based on the file's user_allow_list.
     *
     * @param string $user_id A user_id, for example 'student' or 'aphacker'
     * @param array $json Course materials metadata as loaded from the course materials metadata json
     *                    This array must be loaded by FileUtils::readJsonFile() to be in the expected format!
     * @param string $path_to_file Absolute path to the file in question
     * @return bool True if user is allowed to access the file, or if user_allow_list is not in use
     *              False if the given user was not found in the user_allow_list for this file
     */
    public static function isUserAllowedByAllowList(string $user_id, array $json, string $path_to_file): bool {
        if (array_key_exists('user_allow_list', $json[$path_to_file])) {
            return in_array($user_id, $json[$path_to_file]['user_allow_list']);
        }

        return true;
    }

     /**
      * Determine if a course materials file can be viewed by the current user's section
      *
      * @param Core $core Application core
      * @param string $path_to_file Full path to the file that we would like to check is allowed to be viewed
      * @param user $current_user the current user
      * @return bool Indicates if the file has been released or not
      * @throws FileNotFoundException The course_materials_file_data.json was not found
      */
    public static function isSectionAllowed(Core $core, string $path_to_file, user $current_user) {
        // Before students are allowed to view or download a course materials file we must ensure
        // it has been released.  To return true the file metadata must be found in course_materials_file_data.json
        // and the user's section must be in the file's sections, or the file must not contain sections info, or the
        // user group must be greater than 4

        // Get path to the meta data json
        $meta_data_json = $core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';

        if (!is_file($meta_data_json)) {
            throw new FileNotFoundException('Unable to locate the course_materials_file_data.json file');
        }

        $meta_data = json_decode(file_get_contents($meta_data_json));

        // If file path does not exist as key in $meta_data then it has not been released
        if (!property_exists($meta_data, $path_to_file)) {
            return false;
        }
        else {
            $current_user_group = $current_user->getGroup();
            if (!isset($meta_data->$path_to_file->sections)) {
                return true;
            }
            $file_sections = $meta_data->$path_to_file->sections;
            $user_section = $current_user->getRegistrationSection();
            return ($current_user_group < 4 || in_array($user_section, $file_sections, true));
        }
    }
}
