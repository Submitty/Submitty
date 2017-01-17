<?php

namespace app\libraries\database;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\GradeableVersion;
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
    public function getAllUsers($section_key="registration_section");

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
     *
     * @param $user_id
     *
     * @return Gradeable[]
     */
    public function getAllGradeables($user_id = null);
    
    /**
     * Gets gradeable for the the given id
     *
     * @param $g_id
     * @param $user_id
     *
     * @return Gradeable
     */
    public function getGradeableById($g_id, $user_id = null);

    /**
     * @param $g_id
     * @param $gd_id
     *
     * @return GradeableComponent[]
     */
    public function getGradeableComponents($g_id, $gd_id);

    /**
     * @param string   $g_id
     * @param string   $user_id
     * @param \DateTime $due_date
     * @return GradeableVersion[]
     */
    public function getGradeableVersions($g_id, $user_id, $due_date);

    /**
     * Given a gradeable id and an array of user ids, it returns an array of gradeables for each user.
     *
     * @param string $g_id
     * @param array  $users
     * @param string $section_key
     * @return Gradeable
     */
    public function getGradeableForUsers($g_id, $users, $section_key="registration_section");

    public function getUsersByRegistrationSections($sections);

    public function getTotalUserCountByRegistrationSections($sections);

    public function getGradedUserCountByRegistrationSections($g_id, $sections);

    public function getGradersForRegistrationSections($sections);

    public function getRotatingSectionsForGradeableAndUser($g_id, $user_id);

    public function getUsersByRotatingSections($sections);

    public function getTotalUserCountByRotatingSections($sections);

    public function getGradedUserCountByRotatingSections($g_id, $sections);

    public function getGradersForRotatingSections($g_id, $sections);

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
     * Returns the count of all users in rotating sections that are in a non-null registration section. These are
     * generally students who have late added a course and have been automatically added to the course, but this
     * was done after rotating sections had already been set-up.
     *
     * @return array
     */
    public function getCountUsersRotatingSections();

    /**
     * Returns the count of all users that are in a rotating section, but are not in an assigned registration section.
     * These are generally students who have dropped the course and have not yet been removed from a rotating
     * section.
     *
     * @return array
     */
    public function getCountNullUsersRotatingSections();

    public function getRegisteredUserIdsWithNullRotating();

    public function getRegisteredUserIds();

    public function setAllUsersRotatingSectionNull();

    public function setNonRegisteredUsersRotatingSectionNull();

    public function deleteAllRotatingSections();

    public function getMaxRotatingSection();

    public function insertNewRotatingSection($section);

    public function updateUsersRotatingSection($section, $users);

    /**
     * This inserts an row in the electronic_gradeable_data table for a given gradeable/user/version combination.
     * The values for the row are set to defaults (0 for numerics and NOW() for the timestamp) with the actual values
     * to be later filled in by the grade_students.sh routine. We do it this way as we can properly deal with the
     * electronic_gradeable_version table here as the "active_version" is a concept strictly within the PHP application
     * code and grade_students.sh has no concept of it. This will either update or insert the row in
     * electronic_gradeable_version for the given gradeable and student.
     *
     * @param $g_id
     * @param $user_id
     * @param $version
     */
    public function insertVersionDetails($g_id, $user_id, $version, $timestamp);

    /**
     * Updates the row in electronic_gradeable_version table for a given gradeable and student. This function should
     * only be run directly if we know that the row exists (so when changing the active version for example) as
     * otherwise it'll throw an exception as it does not do error checking on if the row exists.
     *
     * @param $g_id
     * @param $user_id
     * @param $version
     */
    public function updateActiveVersion($g_id, $user_id, $version);

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