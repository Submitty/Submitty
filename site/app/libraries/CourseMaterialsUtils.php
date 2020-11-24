<?php

namespace app\libraries;

use app\controllers\MiscController;
use app\exceptions\MalformedDataException;
use app\models\User;

class CourseMaterialsUtils {

    /**
     * Determine if a course materials file has been released.
     *
     * @param array $json Course materials metadata as loaded from the course materials metadata json
     *                    This array must be loaded by FileUtils::readJsonFile() to be in the expected format!
     * @param string $path Absolute path to the file in question
     * @return bool Indicates if the file has been released or not
     * @throws MalformedDataException An error occurred parsing the file's release time data
     */
    public static function isMaterialReleased(array $json, string $path): bool {
        if (!array_key_exists($path, $json)) {
            return false;
        }
        else {
            $current_time = new \DateTime('now');
            $release_time = new \DateTime($json[$path]['release_datetime']);

            // Ensure release time obtained from file was parsed correctly into a DateTime object
            if ($release_time === false) {
                throw new MalformedDataException("An error occurred parsing the file's release time data.");
            }

            return $current_time > $release_time;
        }
    }

    /**
     * Determine if a user is allowed to access a course materials file based on the file's user_allow_list.
     *
     * @param array $json Course materials metadata as loaded from the course materials metadata json
     *                    This array must be loaded by FileUtils::readJsonFile() to be in the expected format!
     * @param string $user_id A user_id, for example 'student' or 'aphacker'
     * @param string $path Absolute path to the file in question
     * @return bool True if user is allowed to access the file, or if user_allow_list is not in use
     *              False if the given user was not found in the user_allow_list for this file
     */
    public static function isUserAllowedByAllowList(array $json, string $user_id, string $path): bool {
        if (array_key_exists('user_allow_list', $json[$path])) {
            return in_array($user_id, $json[$path]['user_allow_list']);
        }

        return true;
    }

    /**
     * Determine if a course materials file can be viewed by the current user's section.
     *
     * @param array $json Course materials metadata as loaded from the course materials metadata json
     *                    This array must be loaded by FileUtils::readJsonFile() to be in the expected format!
     * @param string $path Absolute path to the file in question
     * @param User $current_user the current user
     * @return bool Indicates if the file has been released or not
     */
    public static function isSectionAllowed(array $json, string $path, User $current_user): bool {
        if (!array_key_exists($path, $json)) {
            return false;
        }
        else {
            if (!isset($json[$path]['sections'])) {
                return true;
            }
            $file_sections = $json[$path]['sections'];
            $user_section = $current_user->getRegistrationSection();
            return ($current_user->getGroup() < 4 || in_array($user_section, $file_sections, true));
        }
    }

    /**
     * Check if the current user has permission to access a course materials file.
     *
     * @param string $path Absolute path to the file
     * @return string An empty string indicates that all checks passed and the user should have access to the file.
     *                A non-empty string will indicate what type of restriction should prevent the user from accessing
     *                the file.
     */
    public static function accessCourseMaterialCheck(Core $core, string $path): string {
        $json = FileUtils::readJsonFile($core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json');

        if (!CourseMaterialsUtils::isMaterialReleased($json, $path)) {
            return 'You may not access this file until it is released.';
        }

        if (!CourseMaterialsUtils::isSectionAllowed($json, $path, $core->getUser())) {
            return 'Your section may not access this file.';
        }

        if (!CourseMaterialsUtils::isUserAllowedByAllowList($json, $core->getUser()->getId(), $path)) {
            return MiscController::GENERIC_NO_ACCESS_MSG;
        }

        return '';
    }
}
