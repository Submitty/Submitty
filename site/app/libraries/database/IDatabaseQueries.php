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
     * Gets a user from the database given a user_id. It will return an associate array with the
     * form of:
     *
     * array(
     *      'user_id',
     *      'user_firstname',
     *      'user_lastname',
     *      'user_email',
     *      'user_group',
     *      'user_course_section',
     *      'user_assignment_section'
     * );
     *
     * @param string $user_id
     *
     * @return array
     */
    public function getUserById($user_id);

    /**
     * Gets an assignment from the database given an assignment_id. It will return an associate array
     * with the form of:
     *
     * @todo: write the return array structure
     * array(
     *      '',
     * );
     *
     * @param string $assignment_id
     *
     * @return array
     */
    public function getAssignmentById($assignment_id);

    /**
     * Fetches all assignments and their details (including if rubric exists for assignment)
     * from the database ordered by their due date and then id. This is a multidimensional array
     * where each inner array has the form of:
     *
     * @todo: write the return array structure
     * array(
     *      '',
     * );
     *
     * @return array
     */
    public function getAllGradeables();

    /**
     * Fetches all students from the users table, ordering by course section than user_id. All users
     * with group number one are considered students.
     *
     * @todo: write the return array structure
     *
     * @return array
     */
    public function getAllStudents();

    /**
     * @todo: write phpdoc
     *
     * @todo: write the return array structure
     *
     * @return array
     */
    public function getAllGroups();

    /**
     * @todo: write phpdoc
     *
     * @todo: write the return array structure
     *
     * @return array
     */
    public function getAllCourseSections();

    /**
     * @todo: write phpdoc
     *
     * @param $session_id
     *
     * @return array
     */
    public function getSession($session_id);

    /**
     * @todo: write phpdoc
     *
     * @param string $session_id
     * @param string $user_id
     * @param string $csrf_token
     *
     * @return string
     */
    public function newSession($session_id, $user_id, $csrf_token);

    /**
     * Updates a given session by setting it's expiration date to be 2 weeks into the future
     * @param string $session_id
     */
    public function updateSessionExpiration($session_id);

    /**
     * Remove sessions which have their expiration date before the
     * current timestamp
     */
    public function removeExpiredSessions();

    /**
     * Remove a session associated with a given session_id
     * @param $session_id
     */
    public function removeSessionById($session_id);
    
    /**
     * Gets array of all gradeables ids in the database returning it in a list sorted alphabetically
     * @return mixed
     */
    public function getAllGradeablesIds();
}