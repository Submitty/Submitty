<?php

namespace app\libraries\database;
use app\models\User;

/**
 * Interface DatabaseQueries
 *
 * Database Query Interface which specifies all available queries in the system and by extension
 * all queries that any implemented database type must also support for full system operation.
 * The "get" queries should return models if possible.
 */
interface IDatabaseQueries {

    /**
     * Gets a user from the database given a user_id.
     * @param string $user_id
     *
     * @return User
     */
    public function getUserById($user_id);

    /**
     * Fetches all students from the users table, ordering by course section than user_id.
     *
     * @return User[]
     */
    public function getAllUsers();

    /**
     * @return User[]
     */
    public function getAllGraders();

    public function createUser(User $user);

    /**
     * @param User $user
     */
    public function updateUser(User $user);

    /**
     * @param string    $user_id
     * @param integer   $user_group
     * @param integer[] $sections
     */
    public function updateGradingRegistration($user_id, $user_group, $sections);
    
    /**
     * Gets array of all gradeables ids in the database returning it in a list sorted alphabetically
     * @return mixed
     */
    public function getAllGradeableIds();
    
    /**
     * Gets gradeable for the the given id
     *
     * @param $g_id
     *
     * @return mixed
     */
    public function getGradeableById($g_id);

    /**
     * Gets all registration sections from the sections_registration table

     * @return array
     */
    public function getRegistrationSections();

    /**
     * Gets all rotating sections from the sections_rotating table
     *
     * @return array
     */
    public function getRotatingSections();

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
}