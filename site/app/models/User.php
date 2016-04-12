<?php

namespace app\models;

use app\database\Database;

class User {
    /**
     * @var array
     */
    private static $details = array();

    /**
     * @var bool
     */
    private static $user_loaded = false;

    /**
     * This is a singleton class, so no instation or duplication
     */
    private function __construct() { }
    private function __clone() { }

    /**
     * @param string $staff_rcs: This is a user's RCS/username on the database
     *
     * @return bool
     */
    public static function loadStaff($staff_rcs) {
        $details = Database::queries()->getUserById($staff_rcs);
        if (count($details) == 0) {
            return false;
        }

        static::$details = $details;

        return true;
    }

    public static function getDetail($detail) {
        return static::$details[$detail];
    }

    public static function userLoaded() {
        return static::$user_loaded;
    }

    public static function accessGrading() {
        return static::$details['user_group'] > 1;
    }
}