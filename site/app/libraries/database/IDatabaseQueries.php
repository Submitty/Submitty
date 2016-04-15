<?php

namespace app\libraries\database;

/**
 * Interface DatabaseQueries
 *
 * Database Query Interface which specifies all available queries in the system and by extension
 * all queries that any implemented database type must also support for full system
 * operation.
 */
interface IDatabaseQueries {
    /**
     * Get all rows in the config table and return them
     * @return array
     */
    public function loadConfig();

    /**
     * @param $user_id
     *
     * @return array
     */
    public function getUserById($user_id);

    public function getAssignmentById($assignment_id);

    /**
     * Fetches all assignments and their details (including if rubric exists for assignment)
     * from the database ordered by their due date and then id.
     *
     * @return array
     */
    public function getAllAssignments();
}