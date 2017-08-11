<?php

namespace app\libraries\database;

use app\libraries\Core;
use app\libraries\Database;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\GradeableComponentMark;
use app\models\GradeableVersion;
use app\models\User;

/**
 * Interface DatabaseQueries
 *
 * Database Query Interface which specifies all available queries in the system and by extension
 * all queries that any implemented database type must also support for full system operation.
 * The "get" queries should return models if possible.
 */
abstract class AbstractDatabaseQueries {

    /** @var Core */
    protected $core;

    /** @var Database */
    protected $submitty_db;

    /** @var Database */
    protected $course_db;

    public function __construct(Core $core) {
        $this->core = $core;
        $this->submitty_db = $core->getSubmittyDB();
        $this->course_db = $core->getCourseDB();
    }

    /**
     * Gets a user from the submitty database given a user_id.
     * @param $user_id
     *
     * @return User
     */
    abstract public function getSubmittyUser($user_id);

    /**
     * Gets a user from the database given a user_id.
     * @param string $user_id
     *
     * @return User
     */
    abstract public function getUserById($user_id);

    abstract public function getGradingSectionsByUserId($user_id);

    /**
     * Fetches all students from the users table, ordering by course section than user_id.
     *
     * @param string $section_key
     * @return User[]
     */
    abstract public function getAllUsers($section_key="registration_section");

    /**
     * @return User[]
     */
    abstract public function getAllGraders();

    /**
     * @param User $user
     */
    abstract public function insertSubmittyUser(User $user);

    /**
     * @param User $user
     * @param string $semester
     * @param string $course
     */
    abstract public function insertCourseUser(User $user, $semester, $course);

    /**
     * @param User $user
     * @param string $semester
     * @param string $course
     */
    abstract public function updateUser(User $user, $semester, $course);

    /**
     * @param string    $user_id
     * @param integer   $user_group
     * @param integer[] $sections
     */
    abstract public function updateGradingRegistration($user_id, $user_group, $sections);

    /**
     * @param $user_id
     * @return Gradeable[]
     */
    abstract public function getAllGradeables($user_id = null);

    /**
     * @param $g_id
     * @param $user_id
     *
     * @return Gradeable
     */
    abstract public function getGradeable($g_id, $user_id = null);

    /**
     * Gets array of all gradeables ids in the database returning it in a list sorted alphabetically
     *
     * @param string|string[]|null  $g_ids
     * @param string|string[]|null  $user_id
     * @param string                $section_key
     *
     * @return Gradeable[]
     */
    abstract public function getGradeables($g_ids = null, $user_id = null, $section_key = "registration_section");

    /**
     * @param $g_id
     * @param $gd_id
     *
     * @return GradeableComponent[]
     */
    abstract public function getGradeableComponents($g_id, $gd_id);

    abstract public function getGradeableComponentsMarks($gc_id);

    /**
     * @param string   $g_id
     * @param string   $user_id
     * @param string   $team_id
     * @param \DateTime $due_date
     * @return GradeableVersion[]
     */
    abstract public function getGradeableVersions($g_id, $user_id, $team_id, $due_date);

    abstract public function getLateDayUpdates($user_id);

    abstract public function getLateDayInformation($user_id);

    abstract public function getUsersByRegistrationSections($sections);

    abstract public function getTotalUserCountByGradingSections($sections, $section_key);

    abstract public function getTotalComponentCount($g_id);

    abstract public function getGradedComponentsCountByGradingSections($g_id, $sections, $section_key);

    abstract public function getAverageComponentScores($g_id);

    abstract public function getGradersForRegistrationSections($sections);

    abstract public function getRotatingSectionsForGradeableAndUser($g_id, $user_id);

    abstract public function getUsersByRotatingSections($sections);

    abstract public function getGradersForRotatingSections($g_id, $sections);

    /**
     * Gets all registration sections from the sections_registration table

     * @return array
     */
    abstract public function getRegistrationSections();

    /**
     * Gets all rotating sections from the sections_rotating table
     *
     * @return array
     */
    abstract public function getRotatingSections();

    /**
     * Gets all the gradeable IDs of the rotating sections
     *
     * @return array
     */
    abstract public function getRotatingSectionsGradeableIDS();

    /**
     * Get gradeables graded by rotating section in the past and the sections each grader graded
     *
     * @return array
     */
    abstract public function getGradeablesPastAndSection();

    /**
     * Returns the count of all users in rotating sections that are in a non-null registration section. These are
     * generally students who have late added a course and have been automatically added to the course, but this
     * was done after rotating sections had already been set-up.
     *
     * @return array
     */
    abstract public function getCountUsersRotatingSections();

    abstract public function getGradersForAllRotatingSections($gradeable_id);

    abstract public function getGradersFromUserType($user_type);

    /**
     * Returns the count of all users that are in a rotating section, but are not in an assigned registration section.
     * These are generally students who have dropped the course and have not yet been removed from a rotating
     * section.
     *
     * @return array
     */
    abstract public function getCountNullUsersRotatingSections();

    abstract public function getRegisteredUserIdsWithNullRotating();

    abstract public function getRegisteredUserIds();

    abstract public function setAllUsersRotatingSectionNull();

    abstract public function setNonRegisteredUsersRotatingSectionNull();

    abstract public function deleteAllRotatingSections();

    abstract public function getMaxRotatingSection();

    abstract public function getNumberRotatingSections();

    abstract public function insertNewRotatingSection($section);

    abstract public function setupRotatingSections($graders, $gradeable_id);

    abstract public function updateUsersRotatingSection($section, $users);

    /**
     * This inserts an row in the electronic_gradeable_data table for a given gradeable/user/version combination.
     * The values for the row are set to defaults (0 for numerics and NOW() for the timestamp) with the actual values
     * to be later filled in by the submitty_grading_scheduler.py and insert_database_version_data.py scripts.
     * We do it this way as we can properly deal with the
     * electronic_gradeable_version table here as the "active_version" is a concept strictly within the PHP application
     * code and the grading scripts have no concept of it. This will either update or insert the row in
     * electronic_gradeable_version for the given gradeable and student.
     *
     * @param $g_id
     * @param $user_id
     * @param $version
     */
    abstract public function insertVersionDetails($g_id, $user_id, $team_id, $version, $timestamp);

    /**
     * Updates the row in electronic_gradeable_version table for a given gradeable and student. This function should
     * only be run directly if we know that the row exists (so when changing the active version for example) as
     * otherwise it'll throw an exception as it does not do error checking on if the row exists.
     *
     * @param $g_id
     * @param $user_id
     * @param $version
     */
    abstract public function updateActiveVersion($g_id, $user_id, $team_id, $version);

    /**
     * @param Gradeable $gradeable
     */
    abstract public function insertGradeableData(Gradeable $gradeable);

    /**
     * @param Gradeable $gradeable
     */
    abstract public function updateGradeableData(Gradeable $gradeable);

    /**
     * @param string             $gd_id
     * @param GradeableComponent $component
     */
    abstract public function insertGradeableComponentData($gd_id, GradeableComponent $component);

    /**
     * @param string             $gd_id
     * @param GradeableComponent $component
     */
    abstract public function updateGradeableComponentData($gd_id, GradeableComponent $component);

    abstract public function insertGradeableComponentMarkData($gd_id, $gc_id, $gcd_grader_id, GradeableComponentMark $mark);

    abstract public function deleteGradeableComponentMarkData($gd_id, $gc_id, $gcd_grader_id, GradeableComponentMark $mark);

    /**
     * Creates a new gradeable in the database
     *
     * @param array $details
     */
    abstract public function createNewGradeable(Gradeable $gradeable);

    /**
     * Updates the current gradeable with new properties.
     *
     * @param array $details
     */
    abstract public function updateGradeable(Gradeable $gradeable);

    abstract public function createNewGradeableComponent(GradeableComponent $component, Gradeable $gradeable);

    abstract public function updateGradeableComponent(GradeableComponent $component);

    abstract public function deleteGradeableComponent(GradeableComponent $component);

    abstract public function createGradeableComponentMark(GradeableComponentMark $mark);

    abstract public function updateGradeableComponentMark(GradeableComponentMark $mark);

    abstract public function deleteGradeableComponentMark(GradeableComponentMark $mark);

    /**
     * Gets an array that contains all revelant data in a gradeable.
     * Uses the gradeable id to use the data in the database.
     *
     * @param $gradeable_id
     *
     */
    abstract public function getGradeableData($gradeable_id);

    /**
     * This updates the viewed date on a gradeable object (assuming that it has a set $user object associated with it).
     *
     * @param \app\models\Gradeable $gradeable
     */
    abstract public function updateUserViewedDate(Gradeable $gradeable);

    /**
     * @todo: write phpdoc
     *
     * @param $session_id
     *
     * @return array
     */
    abstract public function getSession($session_id);

    /**
     * @todo: write phpdoc
     *
     * @param string $session_id
     * @param string $user_id
     * @param string $csrf_token
     *
     * @return string
     */
    abstract public function newSession($session_id, $user_id, $csrf_token);

    /**
     * Updates a given session by setting it's expiration date to be 2 weeks into the future
     * @param string $session_id
     */
    abstract public function updateSessionExpiration($session_id);

    /**
     * Remove sessions which have their expiration date before the
     * current timestamp
     */
    abstract public function removeExpiredSessions();

    /**
     * Remove a session associated with a given session_id
     * @param $session_id
     */
    abstract public function removeSessionById($session_id);


    abstract public function getAllGradeablesIdsAndTitles();

    /**
     * gets ids of all electronic gradeables
     */
    abstract public function getAllElectronicGradeablesIds();


    /**
     * Create a new team id and team in gradeable_teams for given gradeable, add $user_id as a member
     * @param string $g_id
     * @param string $user_id
     * @param integer $registration_section
     * @param integer $rotating_section
     * @return string $team_id
     */
    abstract public function createTeam($g_id, $user_id, $registration_section, $rotating_section);

    /**
     * Set team $team_id's registration/rotating section to $section
     * @param string $team_id
     * @param int $section
     */
    abstract public function updateTeamRegistrationSection($team_id, $section);

    abstract public function updateTeamRotatingSection($team_id, $section);

    /**
     * Remove a user from their current team
     * @param string $team_id
     * @param string $user_id
     */
    abstract public function leaveTeam($team_id, $user_id);

    /**
     * Add user $user_id to team $team_id as an invited user
     * @param string $team_id
     * @param string $user_id
     */
    abstract public function sendTeamInvitation($team_id, $user_id);

    /**
     * Add user $user_id to team $team_id as a team member
     * @param string $team_id
     * @param string $user_id
     */
    abstract public function acceptTeamInvitation($team_id, $user_id);

    /**
     * Cancel a pending team invitation
     * @param string $team_id
     * @param string $user_id
     */
    abstract public function cancelTeamInvitation($team_id, $user_id);

    /**
     * Decline all pending team invitiations for a user
     * @param string $g_id
     * @param string $user_id
     */
    abstract public function declineAllTeamInvitations($g_id, $user_id);

    /**
     * Return Team object for team whith given Team ID
     * @param string $team_id
     * @return \app\models\Team
     */
    abstract public function getTeamById($team_id);

    /**
     * Return Team object for team which the given user belongs to on the given gradeable
     * @param string $g_id
     * @param string $user_id
     * @return \app\models\Team
     */
    abstract public function getTeamByGradeableAndUser($g_id, $user_id);

    /**
     * Return an array of Team objects for all teams on given gradeable
     * @param string $g_id
     * @return \app\models\Team[]
     */
    abstract public function getTeamsByGradeableId($g_id);

    /**
     * Return array of counts of teams/users without team/graded components
     * corresponding to each registration/rotating section
     * @param string $g_id
     * @param rray(int) $sections
     * @param string $section_key
     * @return array(int) $return
     */
    abstract public function getTotalTeamCountByGradingSections($g_id, $sections, $section_key);

    abstract public function getUsersWithoutTeamByGradingSections($g_id, $sections, $section_key);

    abstract public function getGradedComponentsCountByTeamGradingSections($g_id, $sections, $section_key);

    /**
     * Return an array of users with late days
     */
    abstract public function getUsersWithLateDays();

    /**
     * Return an array of users with extensions
     * @param string $gradeable_id
     */
    abstract public function getUsersWithExtensions($gradeable_id);

    /**
     * Updates a given user's late days allowed effective at a given time
     * @param string $user_id
     * @param string $timestamp
     * @param integer $days
     */
    abstract public function updateLateDays($user_id, $timestamp, $days);

    /**
     * Updates a given user's extensions for a given homework
     * @param string $user_id
     * @param string $g_id
     * @param integer $days
     */
    abstract public function updateExtensions($user_id, $g_id, $days);
    
    /**
     * Removes peer grading assignment if instructor decides to change the number of people each person grades for assignment
     * @param string $gradeable_id
     */
    abstract public function clearPeerGradingAssignments($gradeable_id);
    
    /**
     * Adds an assignment for someone to grade another person for peer grading
     * @param string $student
     * @param string $grader
     * @param string $gradeable_id
    */
    abstract public function insertPeerGradingAssignment($grader, $student, $gradeable_id);
}
