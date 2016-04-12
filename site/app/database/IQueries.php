<?php

namespace app\database;

/**
 * Interface Queries
 *
 * Query Interface which specifies all available queries in the system and by extension
 * all queries that any implemented database type must also support for full system
 * operation.
 */
interface IQueries {
    /**
     * Get all rows in the config table and return them
     * @return array
     */
    public function loadConfig();

    /**
     * @param $user_id
     *
     * @return mixed
     */
    public function getUserById($user_id);

    /**
     * @param $user_rcs
     *
     * @return mixed
     */
    public function getStaffByRcs($user_rcs);

    /**
     * @param $student_rcs
     *
     * @return mixed
     */
    public function getStudentByRcs($student_rcs);
}