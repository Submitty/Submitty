<?php

namespace app\models;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\Utils;

/**
 * Class GradeableConfig
 *
 * This class holds and loads any config file for a gradeable using a static cache store so that we only
 * need to perform the file lookup/read once instead of once per gradeable. This is especially important for
 * page such as the Electronic Gradeable summary page where we need the max autograding score for potentially 600+
 * students and we must not perform 600 file lookups on the same piece of information. We have all other information
 * for quick analysis of an assignment stored within the DB so that we can keep our general overhead down.
 *
 * @TODO: Replace this with memcached once we are using that as this is not a very good model
 *
 * @package app\models
 */
class GradeableAutogradingConfig extends AbstractModel {
    protected static $config_store = array();

    /**
     * @param Core   $core
     * @param string $gradeable_id
     */
    public static function loadConfig($core, $gradeable_id) {
        $course_path = $core->getConfig()->getCoursePath();
        $details = FileUtils::readJsonFile(FileUtils::joinPaths($course_path, "config", "build",
            "build_{$gradeable_id}.json"));

        // Was there actually a config file to read from
        if ($details === false) {
            GradeableAutogradingConfig::$config_store[$gradeable_id] = false;
            return;
        }

        if (isset($details['max_submission_size'])) {
            $details['max_submission_size'] = floatval($details['max_submission_size']);
        }

        if (isset($details['max_submissions'])) {
            $details['max_submissions'] = intval($details['max_submissions']);
        }

        if (isset($details['assignment_message'])) {
            $details['assignment_mesage'] = Utils::prepareHtmlString($details['assignment_message']);
        }

        GradeableAutogradingConfig::$config_store[$gradeable_id] = $details;
    }

    /**
     * @param Core   $core
     * @param string $gradeable_id
     * @return array
     */
    public static function getConfig($core, $gradeable_id) {
        if (!isset(GradeableAutogradingConfig::$config_store[$gradeable_id])) {
            GradeableAutogradingConfig::loadConfig($core, $gradeable_id);
        }
        return GradeableAutogradingConfig::$config_store[$gradeable_id];
    }
}
