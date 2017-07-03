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
     * @param $user_id
     * @return Gradeable[]
     */
    public function getAllGradeables($user_id = null);

    /**
     * @param $g_id
     * @param $user_id
     *
     * @return Gradeable
     */
    public function getGradeable($g_id, $user_id = null);

    /**
     * Gets array of all gradeables ids in the database returning it in a list sorted alphabetically
     *
     * @param string|string[]|null  $g_ids
     * @param string|string[]|null  $user_id
     * @param string                $section_key
     *
     * @return Gradeable[]
     */
    public function getGradeables($g_ids = null, $user_id = null, $section_key = "registration_section");

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
    public function getGradeableVersions($g_id, $user_id, $team_id, $due_date);

    public function getUsersByRegistrationSections($sections);

    public function getTotalUserCountByRegistrationSections($sections);

    public function getGradedUserCountByRegistrationSections($g_id, $sections);

    public function getGradersForRegistrationSections($sections);

    public function getRotatingSectionsForGradeableAndUser($g_id, $user_id);

    public function getUsersByRotatingSections($sections);

    public function getTotalUserCountByRotatingSections($sections);

    public function getGradedUserCountByRotatingSections($g_id, $sections);

    public function getGradersForRotatingSections($g_id, $sections);

    public function getGradersFromUserType($user_type);

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
     * Gets all the gradeable IDs of the rotating sections
     *
     * @return array
     */
    public function getRotatingSectionsGradeableIDS();

    /**
     * Get gradeables graded by rotating section in the past and the sections each grader graded
     *
     * @return array
     */
    public function getGradeablesPastAndSection();

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

    public function getNumberRotatingSessions();

    public function getGradersForAllRotatingSections($gradeable_id);

    public function insertNewRotatingSection($section);

    public function setupRotatingSections($graders, $gradeable_id);

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
    public function insertVersionDetails($g_id, $user_id, $team_id, $version, $timestamp);

    /**
     * Updates the row in electronic_gradeable_version table for a given gradeable and student. This function should
     * only be run directly if we know that the row exists (so when changing the active version for example) as
     * otherwise it'll throw an exception as it does not do error checking on if the row exists.
     *
     * @param $g_id
     * @param $user_id
     * @param $version
     */
    public function updateActiveVersion($g_id, $user_id, $team_id, $version);

    /**
     * @param Gradeable $gradeable
     */
    public function insertGradeableData(Gradeable $gradeable);

    /**
     * @param Gradeable $gradeable
     */
    public function updateGradeableData(Gradeable $gradeable);

    /**
     * @param string             $gd_id
     * @param GradeableComponent $component
     */
    public function insertGradeableComponentData($gd_id, GradeableComponent $component);

    /**
     * @param string             $gd_id
     * @param GradeableComponent $component
     */
    public function updateGradeableComponentData($gd_id, GradeableComponent $component);

    /**
     * Creates a new gradeable in the database
     *
     * @param array $details
     */
    public function createNewGradeable($details);

    /**
     * Gets an array that contains all revelant data in a gradeable.
     * Uses the gradeable id to use the data in the database.
     *
     * @param $gradeable_id
     *
     */
    public function getGradeableData($gradeable_id);

    /**
     * Updates the current gradeable with new properties.
     *
     * @param array $details
     */
    public function updateGradeable($details);

    /**
     * This updates the viewed date on a gradeable object (assuming that it has a set $user object associated with it).
     *
     * @param \app\models\Gradeable $gradeable
     */
    public function updateUserViewedDate(Gradeable $gradeable);

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
     * gets ids of all electronic gradeables
     */
    public function getAllElectronicGradeablesIds();


    /**
     * Create a new team id and team in gradeable_teams for given gradeable, add $user_id as a member
     * @param string $g_id
     * @param string $user_id
     */
    public function newTeam($g_id, $user_id);

    /**
     * Add user $user_id to team $team_id as an invited user
     * @param string $team_id
     * @param string $user_id
     */
    public function newTeamInvite($team_id, $user_id);

    /**
     * Add user $user_id to team $team_id as a team member
     * @param string $team_id
     * @param string $user_id
     */
    public function newTeamMember($team_id, $user_id);

    /**
     * Remove a user from their current team, decline all invitiations for that user
     * @param string $g_id
     * @param string $user_id
     */
    public function removeTeamUser($g_id, $user_id);

    /**
     * Return an array of Team objects for all teams on given gradeable
     * @param string $g_id
     * @return \app\models\Team[]
     */
    public function getTeamsByGradeableId($g_id);

    /**
     * Return Team object for team which the given user belongs to on the given gradeable
     * @param string $g_id
     * @param string $user_id
     * @return \app\models\Team
     */
    public function getTeamByUserId($g_id, $user_id);

    /**
     * Update/Insert data from TA grading form to gradeable_data, gradeable_component_data, late_days_used
     *
     * @param array $details
     */
    public function submitTAGrade($details);

    /**
     * Return an array of users with late days
     */
    public function getUsersWithLateDays();

    /**
     * Return an array of users with extensions
     * @param string $gradeable_id
     */
    public function getUsersWithExtensions($gradeable_id);

    /**
     * Updates a given user's late days allowed effective at a given time
     * @param string $user_id
     * @param string $timestamp
     * @param integer $days
     */
    public function updateLateDays($user_id, $timestamp, $days);

    /**
     * Updates a given user's extensions for a given homework
     * @param string $user_id
     * @param string $g_id
     * @param integer $days
     */
    public function updateExtensions($user_id, $g_id, $days);

}
