<?php

namespace app\libraries\database;

use app\exceptions\NotImplementedException;
use app\libraries\Core;
use app\libraries\Utils;
use app\libraries\GradeableType;
use app\models\AdminGradeable;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\GradeableComponentMark;
use app\models\GradeableVersion;
use app\models\User;
use app\models\SimpleLateUser;
use app\models\Team;
use app\models\Course;
use app\models\SimpleStat;


/**
 * DatabaseQueries
 *
 * This class contains all database functions that the Submitty application will run against either
 * of the two connected databases (the main submitty one and the course specific one). Each query in
 * each function is defined by the general SQL specification so we could reasonably expect it possible
 * to run each function against a wide-range of database providers that Submitty can target. However,
 * some database providers can provide their own extended class of Queries to overwrite some functions
 * to take advantage of DB specific functions (like DB array functions) that give a good performance
 * boost for that particular provider.
 *
 * Generally, when adding new queries to the system, you should first add them here, and then
 * only after that should you add them to the dataprovider specific implementation assuming you can
 * achieve some level of speed-up via native DB functions. If it's hard to go that direction initially,
 * (you're using array aggregation heavily), then you'd want to at least create a stub here that just
 * raises a NotImplementedException. All documentation for functions should also reside here with at the
 * minimum an understanding of the contract of the function (parameter types and return type).
 *
 * @see \app\exceptions\NotImplementedException
 */
class DatabaseQueries {

    /** @var Core */
    protected $core;

    /** @var AbstractDatabase */
    protected $submitty_db;

    /** @var AbstractDatabase */
    protected $course_db;

    public function __construct(Core $core) {
        $this->core = $core;
        $this->submitty_db = $core->getSubmittyDB();
        if ($this->core->getConfig()->isCourseLoaded()) {
            $this->course_db = $core->getCourseDB();
        }
    }

    /**
     * Gets a user from the submitty database given a user_id.
     * @param $user_id
     *
     * @return User
     */
    public function getSubmittyUser($user_id) {
        $this->submitty_db->query("SELECT * FROM users WHERE user_id=?", array($user_id));
        return ($this->submitty_db->getRowCount() > 0) ? new User($this->core, $this->submitty_db->row()) : null;
    }

    /**
     * Gets a user from the database given a user_id.
     * @param string $user_id
     *
     * @return User
     */
    public function getUserById($user_id) {
        throw new NotImplementedException();
    }

    public function getGradingSectionsByUserId($user_id) {
        throw new NotImplementedException();
    }

    /**
     * Fetches all students from the users table, ordering by course section than user_id.
     *
     * @param string $section_key
     * @return User[]
     */
    public function getAllUsers($section_key="registration_section") {
        throw new NotImplementedException();
    }

    /**
     * @return User[]
     */
    public function getAllGraders() {
        throw new NotImplementedException();
    }

    /**
     * @param User $user
     */
    public function insertSubmittyUser(User $user) {
        throw new NotImplementedException();
    }

    public function loadThreads(){
        $this->course_db->query("SELECT * FROM threads ORDER BY id DESC LIMIT 25");
        return $this->course_db->rows();
    }

    public function createPost($user, $content, $thread_id, $anonymous, $type){
        $this->course_db->query("INSERT INTO posts (thread_id, parent_id, author_user_id, content, timestamp, anonymous, deleted, endorsed_by, resolved, type) VALUES (?, ?, ?, ?, current_timestamp, ?, ?, ?, ?, ?)", array($thread_id, -1, $user, $content, $anonymous, 0, NULL, 0, $type));
    }

    public function createThread($user, $title, $content, $prof_pinned = 0){

        //insert data
        $this->course_db->query("INSERT INTO threads (title, created_by, pinned, deleted, merged_id, is_visible) VALUES (?, ?, ?, ?, ?, ?)", array($title, $user, 0, 0, -1, true));

        //retrieve generated thread_id
        $this->course_db->query("SELECT MAX(id) as max_id from threads where title=? and created_by=?", array($title, $user));

        //Max id will be the most recent post
        $id = $this->course_db->rows()[0]["max_id"];

        $this->createPost($user, $content, $id, 0, 0);

        return $id;
    }

    /**
     * @param User $user
     * @param string $semester
     * @param string $course
     */
    public function insertCourseUser(User $user, $semester, $course) {
        throw new NotImplementedException();
    }

    /**
     * @param User $user
     * @param string $semester
     * @param string $course
     */
    public function updateUser(User $user, $semester=null, $course=null) {
        throw new NotImplementedException();
    }

    /**
     * @param string    $user_id
     * @param integer   $user_group
     * @param integer[] $sections
     */
    public function updateGradingRegistration($user_id, $user_group, $sections) {
        $this->course_db->query("DELETE FROM grading_registration WHERE user_id=?", array($user_id));
        if ($user_group < 4) {
            foreach ($sections as $section) {
                $this->course_db->query("
    INSERT INTO grading_registration (user_id, sections_registration_id) VALUES(?, ?)", array($user_id, $section));
            }
        }
    }

    /*  Gets the group that the user is in for a given class (used on homepage)
     *  as the user isn't within a class yet.
     *  @param $user_id - user id to be searched for
     *  @return group of user in the given class
    */
    public function getGroupForUserInClass($course_name, $user_id){
        $this->submitty_db->query("SELECT user_group FROM courses_users WHERE user_id = ? AND course = ?", array($user_id, $course_name));
        return intval($this->submitty_db->row()['user_group']);
    }

    public function getAllGradeables($user_id = null) {
        return $this->getGradeables(null, $user_id);
    }

    /**
     * @param $g_id
     * @param $user_id
     *
     * @return Gradeable
     */
    public function getGradeable($g_id = null, $user_id = null) {
        return $this->getGradeables($g_id, $user_id)[0];
    }

    /**
     * Gets array of all gradeables ids in the database returning it in a list sorted alphabetically
     *
     * @param string|string[]|null  $g_ids
     * @param string|string[]|null  $user_ids
     * @param string                $section_key
     * @param string                $sort_key
     * @param                       $g_type
     *
     * @return Gradeable[]
     */
    public function getGradeables($g_ids = null, $user_ids = null, $section_key="registration_section", $sort_key="u.user_id", $g_type = null) {
        $return = array();
        foreach ($this->getGradeablesIterator($g_ids, $user_ids, $section_key, $sort_key, $g_type) as $row) {
            $return[] = $row;
        }

        return $return;
    }

    public function getGradeablesIterator($g_ids = null, $user_ids = null, $section_key="registration_section", $sort_key="u.user_id", $g_type = null) {
        throw new NotImplementedException();
    }

    /**
     * @param $g_id
     * @param $gd_id
     *
     * @return GradeableComponent[]
     */
    public function getGradeableComponents($g_id, $gd_id=null) {
        $left_join = "";
        $gcd = "";

        $params = array();
        if($gd_id != null) {
            $params[] = $gd_id;
            $left_join = "LEFT JOIN (
  SELECT *
  FROM gradeable_component_data
  WHERE gd_id = ?
) as gcd ON gc.gc_id = gcd.gc_id";
            $gcd = ', gcd.*';
        }

        $params[] = $g_id;
        $this->course_db->query("
SELECT gc.*{$gcd}
FROM gradeable_component AS gc
{$left_join}
WHERE gc.g_id=?
", $params);

        $return = array();
        foreach ($this->course_db->rows() as $row) {
            $return[$row['gc_id']] = new GradeableComponent($this->core, $row);
        }
        return $return;
    }

    public function getGradeableComponentsMarks($gc_id) {
        $this->course_db->query("
SELECT *
FROM gradeable_component_mark
WHERE gc_id=?
ORDER BY gcm_order ASC", array($gc_id));
        $return = array();
        foreach ($this->course_db->rows() as $row) {
            $return[$row['gcm_id']] = new GradeableComponentMark($this->core, $row);
        }
        return $return;
    }

    public function getGradeableComponentMarksData($gc_id, $gd_id, $gcd_grader_id="") {
        $params = array($gc_id, $gd_id);
        $and = "";
        if($gcd_grader_id != "") {
            $and = " AND gcd_grader_id = {$gcd_grader_id}";
            $params[] = $gcd_grader_id;
        }
        $this->course_db->query("SELECT gcm_id FROM gradeable_component_mark_data WHERE gc_id = ? AND gd_id=?{$and}", $params);
        return $this->course_db->rows();
    }

    /**
     * @param string   $g_id
     * @param string   $user_id
     * @param string   $team_id
     * @param \DateTime $due_date
     * @return GradeableVersion[]
     */
    public function getGradeableVersions($g_id, $user_id, $team_id, $due_date) {
        if ($user_id === null) {
            $this->course_db->query("
SELECT egd.*, egv.active_version = egd.g_version as active_version
FROM electronic_gradeable_data AS egd
LEFT JOIN (
  SELECT *
  FROM electronic_gradeable_version
) AS egv ON egv.active_version = egd.g_version AND egv.team_id = egd.team_id AND egv.g_id = egd.g_id
WHERE egd.g_id=? AND egd.team_id=?
ORDER BY egd.g_version", array($g_id, $team_id));
        }
        else {
            $this->course_db->query("
SELECT egd.*, egv.active_version = egd.g_version as active_version
FROM electronic_gradeable_data AS egd
LEFT JOIN (
  SELECT *
  FROM electronic_gradeable_version
) AS egv ON egv.active_version = egd.g_version AND egv.user_id = egd.user_id AND egv.g_id = egd.g_id
WHERE egd.g_id=? AND egd.user_id=?
ORDER BY egd.g_version", array($g_id, $user_id));
        }

        $return = array();
        foreach ($this->course_db->rows() as $row) {
            $row['submission_time'] = new \DateTime($row['submission_time'], $this->core->getConfig()->getTimezone());
            $return[$row['g_version']] = new GradeableVersion($this->core, $row, $due_date);
        }

        return $return;
    }


    // Moved from class LateDaysCalculation on port from TAGrading server.  May want to incorporate late day information into gradeable object rather than having a separate query
    public function getLateDayUpdates($user_id) {
        if($user_id != null) {
            $query = "SELECT * FROM late_days WHERE user_id";
            if (is_array($user_id)) {
                $query .= ' IN ('.implode(',', array_fill(0, count($user_id), '?')).')';
                $params = $user_id;
            }
            else {
                $query .= '=?';
                $params = array($user_id);
            }
            $this->course_db->query($query, $params);
        }
        else {
            $this->course_db->query("SELECT * FROM late_days");
        }
        return $this->course_db->rows();
    }

    public function getLateDayInformation($user_id) {
        throw new NotImplementedException();
    }

    public function getUsersByRegistrationSections($sections, $orderBy="registration_section") {
        $return = array();
        if (count($sections) > 0) {
            $query = implode(",", array_fill(0, count($sections), "?"));
            $this->course_db->query("SELECT * FROM users AS u WHERE registration_section IN ({$query}) ORDER BY {$orderBy}", $sections);
            foreach ($this->course_db->rows() as $row) {
                $return[] = new User($this->core, $row);
            }
        }
        return $return;
    }

    public function getUsersInNullSection($orderBy="user_id"){
      $return = array();
      $this->course_db->query("SELECT * FROM users AS u WHERE registration_section IS NULL ORDER BY {$orderBy}");
      foreach ($this->course_db->rows() as $row) {
        $return[] = new User($this->core, $row);
      }
      return $return;
    }

    public function getTotalUserCountByGradingSections($sections, $section_key) {
        $return = array();
        $params = array();
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE {$section_key} IN (".implode(",", array_fill(0, count($sections), "?")).")";
            $params = $sections;
        }
        $this->course_db->query("
SELECT count(*) as cnt, {$section_key}
FROM users
{$where}
GROUP BY {$section_key}
ORDER BY {$section_key}", $params);
        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
            $return[$row[$section_key]] = intval($row['cnt']);
        }
        return $return;
    }
    
    public function getTotalSubmittedUserCountByGradingSections($g_id, $sections, $section_key) {
        $return = array();
        $params = array();
        $where = "";
        if (count($sections) > 0) {
            // Expand out where clause
            $sections_keys = array_keys($sections);
            $where = "WHERE {$section_key} IN (";
            foreach($sections_keys as $section) {
                $where .= "?" . ($section != $sections_keys[count($sections_keys)-1] ? "," : "");
                array_push($params, $section+1);
            }
            $where .= ")";
        }
        $this->course_db->query("
SELECT count(*) as cnt, {$section_key}
FROM users
INNER JOIN electronic_gradeable_version
ON
users.user_id = electronic_gradeable_version.user_id
AND users.". $section_key . " IS NOT NULL
AND electronic_gradeable_version.active_version>0
AND electronic_gradeable_version.g_id='{$g_id}'
{$where}
GROUP BY {$section_key}
ORDER BY {$section_key}", $params);

        foreach ($this->course_db->rows() as $row) {
            $return[$row[$section_key]] = intval($row['cnt']);
        }

        return $return;
    }

    public function getTotalComponentCount($g_id) {
        $this->course_db->query("SELECT count(*) AS cnt FROM gradeable_component WHERE g_id=?", array($g_id));
        return intval($this->course_db->row()['cnt']);
    }

    public function getGradedComponentsCountByGradingSections($g_id, $sections, $section_key) {
        $return = array();
        $params = array($g_id);
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE {$section_key} IN (".implode(",", array_fill(0, count($sections), "?")).")";
            $params = array_merge($params, $sections);
        }
        $this->course_db->query("
SELECT  u.{$section_key}, count(u.*) as cnt
FROM users AS u
INNER JOIN (
  SELECT * FROM gradeable_data AS gd
  LEFT JOIN (
  gradeable_component_data AS gcd
  INNER JOIN gradeable_component AS gc ON gc.gc_id = gcd.gc_id AND gc.gc_is_peer = {$this->course_db->convertBoolean(false)}
  )AS gcd ON gcd.gd_id = gd.gd_id WHERE gcd.g_id=?
) AS gd ON u.user_id = gd.gd_user_id
{$where}
GROUP BY u.{$section_key}
ORDER BY u.{$section_key}", $params);
        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
            $return[$row[$section_key]] = intval($row['cnt']);
        }
        return $return;
    }

    public function getAverageComponentScores($g_id, $section_key) {
        $return = array();
        $this->course_db->query("
SELECT gc_id, gc_title, gc_max_value, gc_is_peer, gc_order, round(AVG(comp_score),2) AS avg_comp_score, round(stddev_pop(comp_score),2) AS std_dev, COUNT(*) FROM(
  SELECT gc_id, gc_title, gc_max_value, gc_is_peer, gc_order,
  CASE WHEN (gc_default + sum_points + gcd_score) > gc_upper_clamp THEN gc_upper_clamp
  WHEN (gc_default + sum_points + gcd_score) < gc_lower_clamp THEN gc_lower_clamp
  ELSE (gc_default + sum_points + gcd_score) END AS comp_score FROM(
    SELECT gcd.gc_id, gd.gd_user_id, egv.user_id, gc_title, gc_max_value, gc_is_peer, gc_order, gc_lower_clamp, gc_default, gc_upper_clamp,
    CASE WHEN sum_points IS NULL THEN 0 ELSE sum_points END AS sum_points, gcd_score
    FROM gradeable_component_data AS gcd
    LEFT JOIN gradeable_component AS gc ON gcd.gc_id=gc.gc_id
    LEFT JOIN(
      SELECT SUM(gcm_points) AS sum_points, gcmd.gc_id, gcmd.gd_id
      FROM gradeable_component_mark_data AS gcmd
      LEFT JOIN gradeable_component_mark AS gcm ON gcmd.gcm_id=gcm.gcm_id AND gcmd.gc_id=gcm.gc_id
      GROUP BY gcmd.gc_id, gcmd.gd_id
      )AS marks
    ON gcd.gc_id=marks.gc_id AND gcd.gd_id=marks.gd_id
    LEFT JOIN(
      SELECT gd.gd_user_id, gd.gd_id
      FROM gradeable_data AS gd
      WHERE gd.g_id=?
    ) AS gd ON gcd.gd_id=gd.gd_id
    INNER JOIN(
      SELECT u.user_id, u.{$section_key}
      FROM users AS u
      WHERE u.{$section_key} IS NOT NULL
    ) AS u ON gd.gd_user_id=u.user_id
    INNER JOIN(
      SELECT egv.user_id, egv.active_version
      FROM electronic_gradeable_version AS egv
      WHERE egv.g_id=? AND egv.active_version>0
    ) AS egv ON egv.user_id=u.user_id
    WHERE g_id=?
  )AS parts_of_comp
)AS comp
GROUP BY gc_id, gc_title, gc_max_value, gc_is_peer, gc_order
ORDER BY gc_order
        ", array($g_id, $g_id, $g_id));
        foreach ($this->course_db->rows() as $row) {
            $return[] = new SimpleStat($this->core, $row);
        }
        return $return;
    }
    
    public function getAverageAutogradedScores($g_id, $section_key) {
        $this->course_db->query("
SELECT round((AVG(score)),2) AS avg_score, round(stddev_pop(score), 2) AS std_dev, 0 AS max, COUNT(*) FROM(
   SELECT * FROM (
      SELECT (egv.autograding_non_hidden_non_extra_credit + egv.autograding_non_hidden_extra_credit + egv.autograding_hidden_non_extra_credit + egv.autograding_hidden_extra_credit) AS score
      FROM electronic_gradeable_data AS egv 
      INNER JOIN users AS u ON u.user_id = egv.user_id
      WHERE egv.g_id=? AND u.{$section_key} IS NOT NULL
   )g
) as individual;
          ", array($g_id));
        if(count($this->course_db->rows()) == 0){
          echo("why");
          return;
        }
        return new SimpleStat($this->core, $this->course_db->rows()[0]);
    }

    public function getAverageForGradeable($g_id, $section_key) {
        $this->course_db->query("
SELECT COUNT(*) from gradeable_component where g_id=?
          ", array($g_id));
        $count = $this->course_db->rows()[0][0];
        $this->course_db->query("
SELECT round((AVG(g_score) + AVG(autograding)),2) AS avg_score, round(stddev_pop(g_score),2) AS std_dev, round(AVG(max),2) AS max, COUNT(*) FROM(
  SELECT * FROM(
    SELECT gd_id, SUM(comp_score) AS g_score, SUM(gc_max_value) AS max, COUNT(comp.*), autograding FROM(
      SELECT  gd_id, gc_title, gc_max_value, gc_is_peer, gc_order, autograding,
      CASE WHEN (gc_default + sum_points + gcd_score) > gc_upper_clamp THEN gc_upper_clamp
      WHEN (gc_default + sum_points + gcd_score) < gc_lower_clamp THEN gc_lower_clamp
      ELSE (gc_default + sum_points + gcd_score) END AS comp_score FROM(
        SELECT gcd.gd_id, gc_title, gc_max_value, gc_is_peer, gc_order, gc_lower_clamp, gc_default, gc_upper_clamp,
        CASE WHEN sum_points IS NULL THEN 0 ELSE sum_points END AS sum_points, gcd_score, CASE WHEN autograding IS NULL THEN 0 ELSE autograding END AS autograding
        FROM gradeable_component_data AS gcd
        LEFT JOIN gradeable_component AS gc ON gcd.gc_id=gc.gc_id
        LEFT JOIN(
          SELECT SUM(gcm_points) AS sum_points, gcmd.gc_id, gcmd.gd_id
          FROM gradeable_component_mark_data AS gcmd
          LEFT JOIN gradeable_component_mark AS gcm ON gcmd.gcm_id=gcm.gcm_id AND gcmd.gc_id=gcm.gc_id
          GROUP BY gcmd.gc_id, gcmd.gd_id
          )AS marks
        ON gcd.gc_id=marks.gc_id AND gcd.gd_id=marks.gd_id
        LEFT JOIN gradeable_data AS gd ON gd.gd_id=gcd.gd_id
        LEFT JOIN (
          SELECT egd.g_id, egd.user_id, (autograding_non_hidden_non_extra_credit + autograding_non_hidden_extra_credit + autograding_hidden_non_extra_credit + autograding_hidden_extra_credit) AS autograding
          FROM electronic_gradeable_version AS egv
          LEFT JOIN electronic_gradeable_data AS egd ON egv.g_id=egd.g_id AND egv.user_id=egd.user_id AND active_version=g_version AND active_version>0
          )AS auto
        ON gd.g_id=auto.g_id AND gd_user_id=auto.user_id
        INNER JOIN users AS u ON u.user_id = auto.user_id
        WHERE gc.g_id=? AND u.{$section_key} IS NOT NULL
      )AS parts_of_comp
    )AS comp
    GROUP BY gd_id, autograding
  )g WHERE count=?
)AS individual
          ", array($g_id, $count));
        if(count($this->course_db->rows()) == 0){
          echo("why");
          return;
        }
        return new SimpleStat($this->core, $this->course_db->rows()[0]);
    }

    //gets ids of students with non null registration section and null rotating section
    public function getRegisteredUsersWithNoRotatingSection(){
       $this->course_db->query("
SELECT user_id
FROM users AS u
WHERE registration_section IS NOT NULL
AND rotating_section IS NULL;");

       return $this->course_db->rows();
    }

    //gets ids of students with non null rotating section and null registration section
    public function getUnregisteredStudentsWithRotatingSection(){
    $this->course_db->query("
SELECT user_id
FROM users AS u
WHERE registration_section IS NULL
AND rotating_section IS NOT NULL;");

       return $this->course_db->rows();
    }

    public function getGradersForRegistrationSections($sections) {
        $return = array();
        $params = array();
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE sections_registration_id IN (" . implode(",", array_fill(0, count($sections), "?")) . ")";
            $params = $sections;
        }
        $this->course_db->query("
SELECT g.*, u.*
FROM grading_registration AS g
LEFT JOIN (
  SELECT *
  FROM users
) AS u ON u.user_id = g.user_id
{$where}
ORDER BY g.sections_registration_id, g.user_id", $params);
        $user_store = array();
        foreach ($this->course_db->rows() as $row) {
            if ($row['sections_registration_id'] === null) {
                $row['sections_registration_id'] = "NULL";
            }

            if (!isset($return[$row['sections_registration_id']])) {
                $return[$row['sections_registration_id']] = array();
            }

            if (!isset($user_store[$row['user_id']])) {
                $user_store[$row['user_id']] = new User($this->core, $row);
            }
            $return[$row['sections_registration_id']][] = $user_store[$row['user_id']];
        }
        return $return;
    }

    public function getGradersForRotatingSections($g_id, $sections) {
        $return = array();
        $params = array($g_id);
        $where = "";
        if (count($sections) > 0) {
            $where = " AND sections_rotating_id IN (" . implode(",", array_fill(0, count($sections), "?")) . ")";
            $params = array_merge($params, $sections);
        }
        $this->course_db->query("
SELECT g.*, u.*
FROM grading_rotating AS g
LEFT JOIN (
  SELECT *
  FROM users
) AS u ON u.user_id = g.user_id
WHERE g.g_id=? {$where}
ORDER BY g.sections_rotating_id, g.user_id", $params);
        $user_store = array();
        foreach ($this->course_db->rows() as $row) {
            if ($row['sections_rotating_id'] === null) {
                $row['sections_rotating_id'] = "NULL";
            }
            if (!isset($return[$row['sections_rotating_id']])) {
                $return[$row['sections_rotating_id']] = array();
            }

            if (!isset($user_store[$row['user_id']])) {
                $user_store[$row['user_id']] = new User($this->core, $row);
            }
            $return[$row['sections_rotating_id']][] = $user_store[$row['user_id']];
        }
        return $return;
    }

    public function getRotatingSectionsForGradeableAndUser($g_id, $user) {
        $this->course_db->query(
          "SELECT sections_rotating_id FROM grading_rotating WHERE g_id=? AND user_id=?", array($g_id, $user));
        $return = array();
        foreach ($this->course_db->rows() as $row) {
            $return[] = $row['sections_rotating_id'];
        }
        return $return;
    }

    public function getUsersByRotatingSections($sections, $orderBy="rotating_section") {
        $return = array();
        if (count($sections) > 0) {
            $query = implode(",", array_fill(0, count($sections), "?"));
            $this->course_db->query("SELECT * FROM users AS u WHERE rotating_section IN ({$query}) ORDER BY {$orderBy}", $sections);
            foreach ($this->course_db->rows() as $row) {
                $return[] = new User($this->core, $row);
            }
        }
        return $return;
    }

    /**
     * Gets all registration sections from the sections_registration table

     * @return array
     */
    public function getRegistrationSections() {
        $this->course_db->query("SELECT * FROM sections_registration ORDER BY sections_registration_id");
        return $this->course_db->rows();
    }

    /**
     * Gets all rotating sections from the sections_rotating table
     *
     * @return array
     */
    public function getRotatingSections() {
        $this->course_db->query("SELECT * FROM sections_rotating ORDER BY sections_rotating_id");
        return $this->course_db->rows();
    }

    /**
     * Gets all the gradeable IDs of the rotating sections
     *
     * @return array
     */
    public function getRotatingSectionsGradeableIDS() {
        $this->course_db->query("SELECT g_id FROM gradeable WHERE g_grade_by_registration = {$this->course_db->convertBoolean(false)} ORDER BY g_grade_start_date ASC");
        return $this->course_db->rows();
    }

    /**
     * Get gradeables graded by rotating section in the past and the sections each grader graded
     *
     * @return array
     */
    public function getGradeablesPastAndSection() {
        throw new NotImplementedException();
    }

    /**
     * Returns the count of all users in rotating sections that are in a non-null registration section. These are
     * generally students who have late added a course and have been automatically added to the course, but this
     * was done after rotating sections had already been set-up.
     *
     * @return array
     */
    public function getCountUsersRotatingSections() {
        $this->course_db->query("
SELECT rotating_section, count(*) as count
FROM users
WHERE (registration_section IS NOT NULL OR manual_registration)
GROUP BY rotating_section
ORDER BY rotating_section");
        return $this->course_db->rows();
    }

    public function getGradersForAllRotatingSections($gradeable_id) {
        throw new NotImplementedException();
    }

    public function getGradersFromUserType($user_type) {
        $this->course_db->query("SELECT user_id FROM users WHERE user_group=? ORDER BY user_id ASC", array($user_type));
        return $this->course_db->rows();
    }

    /**
     * Returns the count of all users that are in a rotating section, but are not in an assigned registration section.
     * These are generally students who have dropped the course and have not yet been removed from a rotating
     * section.
     *
     * @return array
     */
    public function getCountNullUsersRotatingSections() {
        $this->course_db->query("
SELECT rotating_section, count(*) as count
FROM users
WHERE (registration_section IS NULL and NOT manual_registration) AND rotating_section IS NOT NULL
GROUP BY rotating_section
ORDER BY rotating_section");
        return $this->course_db->rows();
    }

    public function getRegisteredUserIdsWithNullRotating() {
        $this->course_db->query("
SELECT user_id
FROM users
WHERE
    (rotating_section IS NULL) and
    (registration_section IS NOT NULL or manual_registration)
ORDER BY user_id ASC");
        return array_map(function($elem) { return $elem['user_id']; }, $this->course_db->rows());
    }

    public function getRegisteredUserIds() {
        $this->course_db->query("
SELECT user_id
FROM users
WHERE
    (registration_section IS NOT NULL) OR
    (manual_registration)
ORDER BY user_id ASC");
        return array_map(function($elem) { return $elem['user_id']; }, $this->course_db->rows());
    }

    public function setAllUsersRotatingSectionNull() {
        $this->course_db->query("UPDATE users SET rotating_section=NULL");
    }

    public function setNonRegisteredUsersRotatingSectionNull() {
        $this->course_db->query("UPDATE users SET rotating_section=NULL WHERE registration_section IS NULL AND NOT manual_registration");
    }

    public function deleteAllRotatingSections() {
        $this->course_db->query("DELETE FROM sections_rotating");
    }

    public function getMaxRotatingSection() {
        $this->course_db->query("SELECT MAX(sections_rotating_id) as max FROM sections_rotating");
        $row = $this->course_db->row();
        return $row['max'];
    }

    public function getNumberRotatingSections() {
        $this->course_db->query("SELECT COUNT(*) AS cnt FROM sections_rotating");
        return $this->course_db->row()['cnt'];
    }

    public function insertNewRotatingSection($section) {
        $this->course_db->query("INSERT INTO sections_rotating (sections_rotating_id) VALUES(?)", array($section));
    }

    public function setupRotatingSections($graders, $gradeable_id) {
        $this->course_db->query("DELETE FROM grading_rotating WHERE g_id=?", array($gradeable_id));
        foreach ($graders as $grader => $sections){
            foreach($sections as $i => $section){
                $this->course_db->query("INSERT INTO grading_rotating(g_id, user_id, sections_rotating_id) VALUES(?,?,?)", array($gradeable_id ,$grader, $section));
            }
        }
    }

    public function updateUsersRotatingSection($section, $users) {
        $update_array = array_merge(array($section), $users);
        $update_string = implode(',', array_pad(array(), count($users), '?'));
        $this->course_db->query("UPDATE users SET rotating_section=? WHERE user_id IN ({$update_string})", $update_array);
    }

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
     * @param $team_id
     * @param $version
     * @param $timestamp
     */
    public function insertVersionDetails($g_id, $user_id, $team_id, $version, $timestamp) {
        $this->course_db->query("
INSERT INTO electronic_gradeable_data
(g_id, user_id, team_id, g_version, autograding_non_hidden_non_extra_credit, autograding_non_hidden_extra_credit,
autograding_hidden_non_extra_credit, autograding_hidden_extra_credit, submission_time)

VALUES(?, ?, ?, ?, 0, 0, 0, 0, ?)", array($g_id, $user_id, $team_id, $version, $timestamp));
        if ($user_id === null) {
            $this->course_db->query("SELECT * FROM electronic_gradeable_version WHERE g_id=? AND team_id=?",
                array($g_id, $team_id));
        }
        else {
            $this->course_db->query("SELECT * FROM electronic_gradeable_version WHERE g_id=? AND user_id=?",
                array($g_id, $user_id));
        }
        $row = $this->course_db->row();
        if (!empty($row)) {
            $this->updateActiveVersion($g_id, $user_id, $team_id, $version);
        }
        else {
            $this->course_db->query("INSERT INTO electronic_gradeable_version (g_id, user_id, team_id, active_version) VALUES(?, ?, ?, ?)",
                array($g_id, $user_id, $team_id, $version));
        }
    }

    /**
     * Updates the row in electronic_gradeable_version table for a given gradeable and student. This function should
     * only be run directly if we know that the row exists (so when changing the active version for example) as
     * otherwise it'll throw an exception as it does not do error checking on if the row exists.
     *
     * @param $g_id
     * @param $user_id
     * @param $team_id
     * @param $version
     */
    public function updateActiveVersion($g_id, $user_id, $team_id, $version) {
        if ($user_id === null) {
            $this->course_db->query("UPDATE electronic_gradeable_version SET active_version=? WHERE g_id=? AND team_id=?",
                array($version, $g_id, $team_id));
        }
        else {
            $this->course_db->query("UPDATE electronic_gradeable_version SET active_version=? WHERE g_id=? AND user_id=?",
                array($version, $g_id, $user_id));
        }
    }

    /**
     * @param Gradeable $gradeable
     * @return int ID of the inserted row
     */
    public function insertGradeableData(Gradeable $gradeable) {
        if ($gradeable->isTeamAssignment()) {
            $params = array($gradeable->getId(), $gradeable->getTeam()->getId(),
                            $gradeable->getOverallComment());
            $this->course_db->query("INSERT INTO
gradeable_data (g_id, gd_team_id, gd_overall_comment)
VALUES (?, ?, ?)", $params);
        }
        else {
            $params = array($gradeable->getId(), $gradeable->getUser()->getId(),
                            $gradeable->getOverallComment());
            $this->course_db->query("INSERT INTO
gradeable_data (g_id, gd_user_id, gd_overall_comment)
VALUES (?, ?, ?)", $params);
        }
        return $this->course_db->getLastInsertId("gradeable_data_gd_id_seq");
    }

    /**
     * @param Gradeable $gradeable
     */
    public function updateGradeableData(Gradeable $gradeable) {
        $params = array($gradeable->getOverallComment(), $gradeable->getGdId());
        $this->course_db->query("UPDATE gradeable_data SET gd_overall_comment=? WHERE gd_id=?", $params);
    }

    /**
     * @param string             $gd_id
     * @param GradeableComponent $component
     */
    public function insertGradeableComponentData($gd_id, GradeableComponent $component) {
        $params = array($component->getId(), $gd_id, $component->getScore(), $component->getComment(), $component->getGrader()->getId(), $component->getGradedVersion(), $component->getGradeTime()->format("Y-m-d H:i:s"));
        $this->course_db->query("
INSERT INTO gradeable_component_data (gc_id, gd_id, gcd_score, gcd_component_comment, gcd_grader_id, gcd_graded_version, gcd_grade_time)
VALUES (?, ?, ?, ?, ?, ?, ?)", $params);
    }

    // FIXME
    //
    //public function updateGradeableComponentData($gd_id, $grader_id, GradeableComponent $component) {
    //    $params = array($component->getScore(), $component->getComment(), $component->getGradedVersion(), $component->getGradeTime()->format("Y-m-d H:i:s"), $grader_id, $component->getId(), $gd_id);
    //    $this->course_db->query("
//UPDATE gradeable_component_data SET gcd_score=?, gcd_component_comment=?, gcd_graded_version=?, gcd_grade_time=?, gcd_grader_id=? WHERE gc_id=? AND gd_id=?", $params);
    //}

    /**
     * @param string             $gd_id
     * @param string             $grader_id
     * @param GradeableComponent $component
     */
    public function updateGradeableComponentData($gd_id, $grader_id, GradeableComponent $component) {
        $params = array($component->getScore(), $component->getComment(), $component->getGradedVersion(),
                        $component->getGradeTime()->format("Y-m-d H:i:s"), $grader_id, $component->getId(), $gd_id);
        $this->course_db->query("
UPDATE gradeable_component_data 
SET 
  gcd_score=?, gcd_component_comment=?, gcd_graded_version=?, gcd_grade_time=?, 
  gcd_grader_id=? 
WHERE gc_id=? AND gd_id=?", $params);
    }


    // END FIXME

    public function replaceGradeableComponentData($gd_id, GradeableComponent $component) {
        $params = array($component->getId(), $gd_id);
        $this->course_db->query("DELETE FROM gradeable_component_data WHERE gc_id=? AND gd_id=?", $params);
        $this->insertGradeableComponentData($gd_id, $component);
    }

    /**
     * TODO: is this actually used somewhere?
     * @param                                $gd_id
     * @param                                $grader_id
     * @param \app\models\GradeableComponent $component
     */
    public function deleteGradeableComponentData($gd_id, $grader_id, GradeableComponent $component) {
        $params = array($component->getId(), $gd_id);
        $this->course_db->query("
DELETE FROM gradeable_component_data WHERE gc_id=? AND gd_id=?", $params);
    }



// FIXME: THIS CODE REQUIRING GRADER_IDS MATCH FOR PEER GRADING BREAKS REGULAR GRADING
//
//    public function deleteGradeableComponentMarkData($gd_id, $gc_id, $grader_id, GradeableComponentMark $mark) {
//        $params = array($gc_id, $gd_id, $grader_id, $mark->getId());
//        $this->course_db->query("
//DELETE FROM gradeable_component_mark_data WHERE gc_id=? AND gd_id=? AND gcd_grader_id=? AND gcm_id=?", $params);
//    }
//

   public function deleteGradeableComponentMarkData($gd_id, $gc_id, $grader_id, GradeableComponentMark $mark) {
           $params = array($gc_id, $gd_id, $mark->getId());
	           $this->course_db->query("
DELETE FROM gradeable_component_mark_data WHERE gc_id=? AND gd_id=? AND gcm_id=?", $params);
    }

// END FIXME



    public function getDataFromGCMD($gc_id, GradeableComponentMark $mark) {
        $return_data = array();
        $params = array($gc_id, $mark->getId());
        $this->course_db->query("
SELECT gd_id FROM gradeable_component_mark_data WHERE gc_id=? AND gcm_id=?", $params);
        $rows = $this->course_db->rows();
        foreach ($rows as $row) {
            $this->course_db->query("
SELECT gd_user_id FROM gradeable_data WHERE gd_id=?", array($row['gd_id']));
            $temp_array = array();
            $temp_array['gd_user_id'] = $this->course_db->row()['gd_user_id'];
            $return_data[] = $temp_array;
        }

        return $return_data;
    }

    public function insertGradeableComponentMarkData($gd_id, $gc_id, $gcd_grader_id, GradeableComponentMark $mark) {
        $params = array($gc_id, $gd_id, $gcd_grader_id, $mark->getId());
        $this->course_db->query("
INSERT INTO gradeable_component_mark_data (gc_id, gd_id, gcd_grader_id, gcm_id)
VALUES (?, ?, ?, ?)", $params);
    }

    /**
     * Creates a new gradeable in the database
     *
     * @param Gradeable $gradeable
     */
    public function createNewGradeable(Gradeable $gradeable) {
        $params = array($gradeable->getId(), $gradeable->getName(), $gradeable->getInstructionsUrl(), $gradeable->getTaInstructions(), $gradeable->getType(), var_export($gradeable->getGradeByRegistration(), true), $gradeable->getTaViewDate()->format('Y/m/d H:i:s'), $gradeable->getGradeStartDate()->format('Y/m/d H:i:s'), $gradeable->getGradeReleasedDate()->format('Y/m/d H:i:s'), $gradeable->getMinimumGradingGroup(), $gradeable->getBucket());
        $this->course_db->query("
INSERT INTO gradeable(g_id, g_title, g_instructions_url,g_overall_ta_instructions, g_gradeable_type, g_grade_by_registration, g_ta_view_start_date, g_grade_start_date,  g_grade_released_date,  g_min_grading_group, g_syllabus_bucket)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $params);
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            $params = array($gradeable->getId(), $gradeable->getOpenDate()->format('Y/m/d H:i:s'), $gradeable->getDueDate()->format('Y/m/d H:i:s'), var_export($gradeable->getIsRepository(), true), $gradeable->getSubdirectory(), var_export($gradeable->getTeamAssignment(),true), $gradeable->getMaxTeamSize(), $gradeable->getTeamLockDate()->format('Y/m/d H:i:s'), var_export($gradeable->getTaGrading(), true), var_export($gradeable->getStudentView(), true), var_export($gradeable->getStudentSubmit(), true),  var_export($gradeable->getStudentDownload(), true), var_export($gradeable->getStudentAnyVersion(), true), $gradeable->getConfigPath(), $gradeable->getLateDays(), $gradeable->getPointPrecision(), var_export($gradeable->getPeerGrading(), true), $gradeable->getPeerGradeSet());
            $this->course_db->query("
INSERT INTO electronic_gradeable(g_id, eg_submission_open_date, eg_submission_due_date, eg_is_repository,
eg_subdirectory, eg_team_assignment, eg_max_team_size, eg_team_lock_date, eg_use_ta_grading, eg_student_view, eg_student_submit, eg_student_download,
eg_student_any_version, eg_config_path, eg_late_days, eg_precision, eg_peer_grading, eg_peer_grade_set)
VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $params);
        }
    }

    /**
     * Updates the current gradeable with new properties.
     *
     * @param Gradeable $gradeable
     */
    public function updateGradeable(Gradeable $gradeable) {
        $params = array($gradeable->getName(), $gradeable->getInstructionsUrl(), $gradeable->getTaInstructions(),
                        $gradeable->getType(), $this->course_db->convertBoolean($gradeable->getGradeByRegistration()),
                        $gradeable->getTaViewDate()->format('Y/m/d H:i:s'),
                        $gradeable->getGradeStartDate()->format('Y/m/d H:i:s'),
                        $gradeable->getGradeReleasedDate()->format('Y/m/d H:i:s'),
                        $gradeable->getMinimumGradingGroup(), $gradeable->getBucket(), $gradeable->getId());
        $this->course_db->query("
UPDATE gradeable SET g_title=?, g_instructions_url=?, g_overall_ta_instructions=?,
g_gradeable_type=?, g_grade_by_registration=?, g_ta_view_start_date=?, g_grade_start_date=?,
g_grade_released_date=?, g_min_grading_group=?, g_syllabus_bucket=? WHERE g_id=?", $params);
        if ($gradeable->getType() === 0) {
            $params = array($gradeable->getOpenDate()->format('Y/m/d H:i:s'), $gradeable->getDueDate()->format('Y/m/d H:i:s'), var_export($gradeable->getIsRepository(), true), $gradeable->getSubdirectory(), var_export($gradeable->getTeamAssignment(),true), $gradeable->getMaxTeamSize(), $gradeable->getTeamLockDate()->format('Y/m/d H:i:s'), var_export($gradeable->getTaGrading(), true), var_export($gradeable->getStudentView(), true), var_export($gradeable->getStudentSubmit(), true), var_export($gradeable->getStudentDownload(), true), var_export($gradeable->getStudentAnyVersion(), true), $gradeable->getConfigPath(), $gradeable->getLateDays(), $gradeable->getPointPrecision(), var_export($gradeable->getPeerGrading(), true), $gradeable->getPeerGradeSet(), $gradeable->getId());
            $this->course_db->query("
UPDATE electronic_gradeable SET eg_submission_open_date=?, eg_submission_due_date=?, eg_is_repository=?,
eg_subdirectory=?, eg_team_assignment=?, eg_max_team_size=?, eg_team_lock_date=?, eg_use_ta_grading=?, eg_student_view=?, eg_student_submit=?,
eg_student_download=?, eg_student_any_version=?, eg_config_path=?, eg_late_days=?, eg_precision=?, eg_peer_grading=?, eg_peer_grade_set=? WHERE g_id=?", $params);
        }
    }

    public function createNewGradeableComponent(GradeableComponent $component, Gradeable $gradeable) {
        $params = array($gradeable->getId(), $component->getTitle(), $component->getTaComment(),
                        $component->getStudentComment(), $component->getLowerClamp(), $component->getDefault(),
                        $component->getMaxValue(), $component->getUpperClamp(),
                        $this->course_db->convertBoolean($component->getIsText()), $component->getOrder(),
                        $this->course_db->convertBoolean($component->getIsPeer()), $component->getPage());
        $this->course_db->query("
INSERT INTO gradeable_component(g_id, gc_title, gc_ta_comment, gc_student_comment, gc_lower_clamp, gc_default, gc_max_value, gc_upper_clamp,
gc_is_text, gc_order, gc_is_peer, gc_page)
VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $params);
    }

    public function updateGradeableComponent(GradeableComponent $component) {
        $params = array($component->getTitle(), $component->getTaComment(), $component->getStudentComment(),
                        $component->getLowerClamp(), $component->getDefault(), $component->getMaxValue(),
                        $component->getUpperClamp(), $this->course_db->convertBoolean($component->getIsText()),
                        $component->getOrder(), $this->course_db->convertBoolean($component->getIsPeer()),
                        $component->getPage(), $component->getId());
        $this->course_db->query("
UPDATE gradeable_component SET gc_title=?, gc_ta_comment=?, gc_student_comment=?, gc_lower_clamp=?, gc_default=?, gc_max_value=?, gc_upper_clamp=?, gc_is_text=?, gc_order=?, gc_is_peer=?, gc_page=? WHERE gc_id=?", $params);
    }

    public function deleteGradeableComponent(GradeableComponent $component) {
        $this->course_db->query("DELETE FROM gradeable_component_data WHERE gc_id=?",array($component->getId()));
        $this->course_db->query("DELETE FROM gradeable_component WHERE gc_id=?", array($component->getId()));
    }

    public function createGradeableComponentMark(GradeableComponentMark $mark) {
        $bool_value = $this->course_db->convertBoolean($mark->getPublish());
        $params = array($mark->getGcId(), $mark->getPoints(), $mark->getNoteNoDecode(), $mark->getOrder(), $bool_value);

        $this->course_db->query("
INSERT INTO gradeable_component_mark (gc_id, gcm_points, gcm_note, gcm_order, gcm_publish)
VALUES (?, ?, ?, ?, ?)", $params);
        return $this->course_db->getLastInsertId();
    }

    public function updateGradeableComponentMark(GradeableComponentMark $mark) {
        $bool_value = $this->course_db->convertBoolean($mark->getPublish());
        $params = array($mark->getGcId(), $mark->getPoints(), $mark->getNoteNoDecode(), $mark->getOrder(), $bool_value, $mark->getId());
        $this->course_db->query("
UPDATE gradeable_component_mark SET gc_id=?, gcm_points=?, gcm_note=?, gcm_order=?, gcm_publish=?
WHERE gcm_id=?", $params);
    }

    public function deleteGradeableComponentMark(GradeableComponentMark $mark) {
        $this->course_db->query("DELETE FROM gradeable_component_mark_data WHERE gcm_id=?",array($mark->getId()));
        $this->course_db->query("DELETE FROM gradeable_component_mark WHERE gcm_id=?", array($mark->getId()));
    }

    public function getGreatestGradeableComponentMarkOrder(GradeableComponent $component) {
    	$this->course_db->query("SELECT MAX(gcm_order) as max FROM gradeable_component_mark WHERE gc_id=? ", array($component->getId()));
    	$row = $this->course_db->row();
        return $row['max'];

    }

    /**
     * Gets an array that contains all revelant data in a gradeable.
     * Uses the gradeable id to use the data in the database.
     *
     * @param $gradeable_id
     * @param $admin_gradeable
     * @param $template
     *
     */
    public function getGradeableInfo($gradeable_id, AdminGradeable $admin_gradeable, $template=false) {
        throw new NotImplementedException();
    }

    /**
     * This updates the viewed date on a gradeable object (assuming that it has a set
     * $user object associated with it).
     *
     * @param \app\models\Gradeable $gradeable
     */
    public function updateUserViewedDate(Gradeable $gradeable) {
        if ($gradeable->getGdId() !== null) {
            $this->course_db->query("UPDATE gradeable_data SET gd_user_viewed_date = NOW() WHERE gd_id=?",
                array($gradeable->getGdId()));
        }
    }

    /**
     * @todo: write phpdoc
     *
     * @param $session_id
     *
     * @return array
     */
    public function getSession($session_id) {
        $this->submitty_db->query("SELECT * FROM sessions WHERE session_id=?", array($session_id));
        return $this->submitty_db->row();
    }

    /**
     * @todo: write phpdoc
     *
     * @param string $session_id
     * @param string $user_id
     * @param string $csrf_token
     *
     * @return string
     */
    public function newSession($session_id, $user_id, $csrf_token) {
        $this->submitty_db->query("INSERT INTO sessions (session_id, user_id, csrf_token, session_expires)
                                   VALUES(?,?,?,current_timestamp + interval '336 hours')",
            array($session_id, $user_id, $csrf_token));

    }

    /**
     * Updates a given session by setting it's expiration date to be 2 weeks into the future
     * @param string $session_id
     */
    public function updateSessionExpiration($session_id) {
        $this->submitty_db->query("UPDATE sessions SET session_expires=(current_timestamp + interval '336 hours')
                                   WHERE session_id=?", array($session_id));
    }

    /**
     * Remove sessions which have their expiration date before the
     * current timestamp
     */
    public function removeExpiredSessions() {
        $this->submitty_db->query("DELETE FROM sessions WHERE session_expires < current_timestamp");
    }

    /**
     * Remove a session associated with a given session_id
     * @param $session_id
     */
    public function removeSessionById($session_id) {
        $this->submitty_db->query("DELETE FROM sessions WHERE session_id=?", array($session_id));
    }

    public function getAllGradeablesIdsAndTitles() {
        $this->course_db->query("SELECT g_id, g_title FROM gradeable ORDER BY g_title ASC");
        return $this->course_db->rows();
    }

    public function getAllGradeablesIds() {
        $this->course_db->query("SELECT g_id FROM gradeable ORDER BY g_id");
        return $this->course_db->rows();
    }

    /**
     * gets ids of all electronic gradeables
     */
    public function getAllElectronicGradeablesIds() {
        $this->course_db->query("SELECT g_id, g_title FROM gradeable WHERE g_gradeable_type=0 ORDER BY g_grade_released_date DESC");
        return $this->course_db->rows();
    }

    /**
     * Create a new team id and team in gradeable_teams for given gradeable, add $user_id as a member
     * @param string $g_id
     * @param string $user_id
     * @param integer $registration_section
     * @param integer $rotating_section
     * @return string $team_id
     */
    public function createTeam($g_id, $user_id, $registration_section, $rotating_section) {
        $this->course_db->query("SELECT COUNT(*) AS cnt FROM gradeable_teams");
        $team_id_prefix = strval($this->course_db->row()['cnt']);
        if (strlen($team_id_prefix) < 5) $team_id_prefix = str_repeat("0", 5-strlen($team_id_prefix)) . $team_id_prefix;
        $team_id = "{$team_id_prefix}_{$user_id}";

        $params = array($team_id, $g_id, $registration_section, $rotating_section);
        $this->course_db->query("INSERT INTO gradeable_teams (team_id, g_id, registration_section, rotating_section) VALUES(?,?,?,?)", $params);
        $this->course_db->query("INSERT INTO teams (team_id, user_id, state) VALUES(?,?,1)", array($team_id, $user_id));
        return $team_id;
    }

    /**
     * Set team $team_id's registration/rotating section to $section
     * @param string $team_id
     * @param int $section
     */
    public function updateTeamRegistrationSection($team_id, $section) {
        $this->course_db->query("UPDATE gradeable_teams SET registration_section=? WHERE team_id=?", array($section, $team_id));
    }

    public function updateTeamRotatingSection($team_id, $section) {
        $this->course_db->query("UPDATE gradeable_teams SET rotating_section=? WHERE team_id=?", array($section, $team_id));
    }

    /**
     * Remove a user from their current team
     * @param string $team_id
     * @param string $user_id
     */
    public function leaveTeam($team_id, $user_id) {
        $this->course_db->query("DELETE FROM teams AS t
          WHERE team_id=? AND user_id=? AND state=1", array($team_id, $user_id));
    }

    /**
     * Add user $user_id to team $team_id as an invited user
     * @param string $team_id
     * @param string $user_id
     */
    public function sendTeamInvitation($team_id, $user_id) {
        $this->course_db->query("INSERT INTO teams (team_id, user_id, state) VALUES(?,?,0)", array($team_id, $user_id));
    }

    /**
     * Add user $user_id to team $team_id as a team member
     * @param string $team_id
     * @param string $user_id
     */
    public function acceptTeamInvitation($team_id, $user_id) {
        $this->course_db->query("INSERT INTO teams (team_id, user_id, state) VALUES(?,?,1)", array($team_id, $user_id));
    }

    /**
     * Cancel a pending team invitation
     * @param string $team_id
     * @param string $user_id
     */
    public function cancelTeamInvitation($team_id, $user_id) {
        $this->course_db->query("DELETE FROM teams WHERE team_id=? AND user_id=? AND state=0", array($team_id, $user_id));
    }

    /**
     * Decline all pending team invitiations for a user
     * @param string $g_id
     * @param string $user_id
     */
    public function declineAllTeamInvitations($g_id, $user_id) {
        $this->course_db->query("DELETE FROM teams AS t USING gradeable_teams AS gt
          WHERE gt.g_id=? AND gt.team_id = t.team_id AND t.user_id=? AND t.state=0", array($g_id, $user_id));
    }

    /**
     * Return Team object for team whith given Team ID
     * @param string $team_id
     * @return \app\models\Team
     */
    public function getTeamById($team_id) {
        $this->course_db->query("
          SELECT team_id, registration_section, rotating_section
          FROM gradeable_teams
          WHERE team_id=?",
            array($team_id));
        if (count($this->course_db->rows()) === 0) {
            return null;
        }
        $details = $this->course_db->row();

        $this->course_db->query("SELECT user_id, state FROM teams WHERE team_id=? ORDER BY user_id", array($team_id));
        $details['users'] = $this->course_db->rows();
        return new Team($this->core, $details);
    }

    /**
     * Return Team object for team which the given user belongs to on the given gradeable
     * @param string $g_id
     * @param string $user_id
     * @return \app\models\Team
     */
    public function getTeamByGradeableAndUser($g_id, $user_id) {
        $this->course_db->query("
          SELECT team_id, registration_section, rotating_section
          FROM gradeable_teams
          WHERE g_id=? AND team_id IN (
            SELECT team_id
            FROM teams
            WHERE user_id=? AND state=1)",
            array($g_id, $user_id));
        if (count($this->course_db->rows()) === 0) {
            return null;
        }
        $details = $this->course_db->row();

        $this->course_db->query("SELECT user_id, state FROM teams WHERE team_id=? ORDER BY user_id", array($details['team_id']));
        $details['users'] = $this->course_db->rows();
        return new Team($this->core, $details);
    }

    /**
     * Return an array of Team objects for all teams on given gradeable
     * @param string $g_id
     * @return \app\models\Team[]
     */
    public function getTeamsByGradeableId($g_id) {
        $this->course_db->query("
          SELECT team_id, registration_section, rotating_section
          FROM gradeable_teams
          WHERE g_id=?
          ORDER BY team_id",
            array($g_id));

        $all_teams_details = array();
        foreach($this->course_db->rows() as $row) {
            $all_teams_details[$row['team_id']] = $row;
        }

        $teams = array();
        foreach($all_teams_details as $team_id => $details) {
            $this->course_db->query("SELECT user_id, state FROM teams WHERE team_id=? ORDER BY user_id", array($team_id));
            $details['users'] = $this->course_db->rows();
            $teams[] = new Team($this->core, $details);
        }

        return $teams;
    }

    /**
     * Return array of counts of teams/users without team/graded components
     * corresponding to each registration/rotating section
     * @param string $g_id
     * @param rray(int) $sections
     * @param string $section_key
     * @return array(int) $return
     */
    public function getTotalTeamCountByGradingSections($g_id, $sections, $section_key) {
        $return = array();
        $params = array($g_id);
        $sections_query = "";
        if (count($sections) > 0) {
            $sections_query = "{$section_key} IN (".implode(",", array_fill(0, count($sections), "?")).") AND";
            $params = array_merge($sections, $params);
        }
        $this->course_db->query("
SELECT count(*) as cnt, {$section_key}
FROM gradeable_teams
WHERE {$sections_query} g_id=? AND team_id IN (
  SELECT team_id
  FROM teams
)
GROUP BY {$section_key}
ORDER BY {$section_key}", $params);
        foreach ($this->course_db->rows() as $row) {
            $return[$row[$section_key]] = intval($row['cnt']);
        }
        foreach ($sections as $section) {
            if (!isset($return[$section])) $return[$section] = 0;
        }
        ksort($return);
        return $return;
    }

    public function getUsersWithoutTeamByGradingSections($g_id, $sections, $section_key) {
        $return = array();
        $params = array($g_id);
        $sections_query = "";
        if (count($sections) > 0) {
            $sections_query= "{$section_key} IN (".implode(",", array_fill(0, count($sections), "?")).") AND";
            $params = array_merge($sections, $params);
        }
        $this->course_db->query("
SELECT count(*) as cnt, {$section_key}
FROM users
WHERE {$sections_query} user_id NOT IN (
  SELECT user_id
  FROM gradeable_teams NATURAL JOIN teams
  WHERE g_id=?
  ORDER BY user_id
)
GROUP BY {$section_key}
ORDER BY {$section_key}", $params);
        foreach ($this->course_db->rows() as $row) {
            $return[$row[$section_key]] = intval($row['cnt']);
        }
        foreach ($sections as $section) {
            if (!isset($return[$section])) {
                $return[$section] = 0;
            }
        }
        ksort($return);
        return $return;
    }

    public function getGradedComponentsCountByTeamGradingSections($g_id, $sections, $section_key) {
        $return = array();
        $params = array($g_id);
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE {$section_key} IN (".implode(",", array_fill(0, count($sections), "?")).")";
            $params = array_merge($params, $sections);
        }
        $this->course_db->query("
SELECT count(gt.*) as cnt, gt.{$section_key}
FROM gradeable_teams AS gt
INNER JOIN (
  SELECT * FROM gradeable_data AS gd LEFT JOIN gradeable_component_data AS gcd ON gcd.gd_id = gd.gd_id WHERE g_id=?
) AS gd ON gt.team_id = gd.gd_team_id
{$where}
GROUP BY gt.{$section_key}
ORDER BY gt.{$section_key}", $params);
        foreach ($this->course_db->rows() as $row) {
            $return[$row[$section_key]] = intval($row['cnt']);
        }
        return $return;
    }

    /**
     * Return an array of users with late days
     *
     * @return array
     */
    public function getUsersWithLateDays() {
      throw new NotImplementedException();
    }

    /**
     * Return an array of users with extensions
     * @param string $gradeable_id
     * @return SimpleLateUser[]
     */
    public function getUsersWithExtensions($gradeable_id) {
        $this->course_db->query("
        SELECT u.user_id, user_firstname,
          user_preferred_firstname, user_lastname, late_day_exceptions
        FROM users as u
        FULL OUTER JOIN late_day_exceptions as l
          ON u.user_id=l.user_id
        WHERE g_id=?
          AND late_day_exceptions IS NOT NULL
          AND late_day_exceptions>0
        ORDER BY user_email ASC;", array($gradeable_id));

        $return = array();
        foreach($this->course_db->rows() as $row){
            $return[] = new SimpleLateUser($this->core, $row);
        }
        return $return;
    }

    /**
     * Updates a given user's late days allowed effective at a given time
     * @param string $user_id
     * @param string $timestamp
     * @param integer $days
     */
    public function updateLateDays($user_id, $timestamp, $days){
        $this->course_db->query("
          UPDATE late_days
          SET allowed_late_days=?
          WHERE user_id=?
            AND since_timestamp=?", array($days, $user_id, $timestamp));
        if ($this->course_db->getRowCount() === 0) {
            $this->course_db->query("
            INSERT INTO late_days
            (user_id, since_timestamp, allowed_late_days)
            VALUES(?,?,?)", array($user_id, $timestamp, $days));
        }
    }

    /**
     * Updates a given user's extensions for a given homework
     * @param string $user_id
     * @param string $g_id
     * @param integer $days
     */
    public function updateExtensions($user_id, $g_id, $days){
        $this->course_db->query("
          UPDATE late_day_exceptions
          SET late_day_exceptions=?
          WHERE user_id=?
            AND g_id=?;", array($days, $user_id, $g_id));
        if ($this->course_db->getRowCount() === 0) {
            $this->course_db->query("
            INSERT INTO late_day_exceptions
            (user_id, g_id, late_day_exceptions)
            VALUES(?,?,?)", array($user_id, $g_id, $days));
        }
    }
    
    /**
     * Removes peer grading assignment if instructor decides to change the number of people each person grades for assignment
     * @param string $gradeable_id
     */
    public function clearPeerGradingAssignments($gradeable_id) {
        $this->course_db->query("DELETE FROM peer_assign WHERE g_id=?", array($gradeable_id));
    }
    
    /**
     * Adds an assignment for someone to grade another person for peer grading
     * @param string $student
     * @param string $grader
     * @param string $gradeable_id
    */
    public function insertPeerGradingAssignment($grader, $student, $gradeable_id) {
        $this->course_db->query("INSERT INTO peer_assign(grader_id, user_id, g_id) VALUES (?,?,?)", array($grader, $student, $gradeable_id));
    }

    public function getStudentCoursesById($user_id, $submitty_path) {
        $this->submitty_db->query("
SELECT semester, course
FROM courses_users u
WHERE u.user_id=? ORDER BY course", array($user_id));
       $return = array();
        foreach ($this->submitty_db->rows() as $row) {
          $course = new Course($this->core, $row);
          $course->loadDisplayName($submitty_path);
          $return[] = $course;
        }
        return $return;
    }

    public function getPeerAssignment($gradeable_id, $grader) {
        $this->course_db->query("SELECT user_id FROM peer_assign WHERE g_id=? AND grader_id=?", array($gradeable_id, $grader));
        $return = array();
        foreach($this->course_db->rows() as $id) {
            $return[] = $id['user_id'];
        }
        return $return;
    }

    public function getPeerGradingAssignNumber($g_id) {
        $this->course_db->query("SELECT eg_peer_grade_set FROM electronic_gradeable WHERE g_id=?", array($g_id));
        return $this->course_db->rows()[0]['eg_peer_grade_set'];
    }

    public function getNumPeerComponents($g_id) {
        $this->course_db->query("SELECT COUNT(*) as cnt FROM gradeable_component WHERE gc_is_peer='t' and g_id=?", array($g_id));
        return intval($this->course_db->rows()[0]['cnt']);
    }

    public function getNumGradedPeerComponents($gradeable_id, $grader) {
        if (!is_array($grader)) {
            $params = array($grader);
        }
        else {
            $params = $grader;
        }
        $grader_list = implode(",", array_fill(0, count($params), "?"));
        $params[] = $gradeable_id;
        $this->course_db->query("SELECT COUNT(*) as cnt
FROM gradeable_component_data as gcd
WHERE gcd.gcd_grader_id IN ({$grader_list})
AND gc_id IN (
  SELECT gc_id
  FROM gradeable_component
  WHERE gc_is_peer='t' AND g_id=?
)", $params);

        return intval($this->course_db->rows()[0]['cnt']);
    }

    public function getGradedPeerComponentsByRegistrationSection($gradeable_id, $sections=array()) {
        $where = "";
        $params = array();
        if(count($sections) > 0) {
            $where = "WHERE registration_section IN (".implode(",", arrayfill(0,count($sections),"?"));
            $params = $sections;
        }
        $params[] = $gradeable_id;
        $this->course_db->query("
        SELECT count(u.*), u.registration_section
        FROM users as u
        INNER JOIN(
            SELECT gd.* FROM gradeable_data as gd
            LEFT JOIN(
                gradeable_component_data as gcd
                LEFT JOIN gradeable_component as gc
                ON gcd.gc_id = gc.gc_id and gc.gc_is_peer = 't'
            ) as gcd ON gcd.gd_id = gd.gd_id
            WHERE gd.g_id = ?
            GROUP BY gd.gd_id
        ) as gd ON gd.gd_user_id = u.user_id
        {$where}
        GROUP BY u.registration_section
        ORDER BY u.registration_section", $params);

        $return = array();
        foreach($this->course_db->rows() as $row) {
            $return[$row['registration_section']] = intval($row['count']);
        }
        return $return;
    }

    public function getGradedPeerComponentsByRotatingSection($gradeable_id, $sections=array()) {
        $where = "";
        $params = array();
        if(count($sections) > 0) {
            $where = "WHERE rotating_section IN (".implode(",", arrayfill(0,count($sections),"?"));
            $params = $sections;
        }
        $params[] = $gradeable_id;
        $this->course_db->query("
        SELECT count(u.*), u.rotating_section
        FROM users as u
        INNER JOIN(
            SELECT gd.* FROM gradeable_data as gd
            LEFT JOIN(
                gradeable_component_data as gcd
                LEFT JOIN gradeable_component as gc
                ON gcd.gc_id = gc.gc_id and gc.gc_is_peer = 't'
            ) as gcd ON gcd.gd_id = gd.gd_id
            WHERE gd.g_id = ?
            GROUP BY gd.gd_id
        ) as gd ON gd.gd_user_id = u.user_id
        {$where}
        GROUP BY u.rotating_section
        ORDER BY u.rotating_section", $params);

        $return = array();
        foreach($this->course_db->rows() as $row) {
            $return[$row['rotating_section']] = intval($row['count']);
        }
        return $return;
    }

    public function getPostsForThread($thread_id){

      if($thread_id != -1) {
        $this->course_db->query("SELECT * FROM posts WHERE thread_id=?", array($thread_id));
      } else {
        $this->course_db->query("SELECT * FROM posts WHERE thread_id= (SELECT MAX(id) from threads)"); 
      }
      return $this->course_db->rows();
    }

    public function getAnonId($user_id) {
        $params = (is_array($user_id)) ? $user_id : array($user_id);

        $question_marks = implode(",", array_fill(0, count($params), "?"));
        $this->course_db->query("SELECT user_id, anon_id FROM users WHERE user_id IN({$question_marks})", $params);
        $return = array();
        foreach($this->course_db->rows() as $id_map) {
            $return[$id_map['user_id']] = $id_map['anon_id'];
        }
        return $return;
    }

    public function getUserFromAnon($anon_id) {
        $params = is_array($anon_id) ? $anon_id : array($anon_id);

        $question_marks = implode(",", array_fill(0, count($params), "?"));
        $this->course_db->query("SELECT anon_id, user_id FROM users WHERE anon_id IN ({$question_marks})", $params);
        $return = array();
        foreach($this->course_db->rows() as $id_map) {
            $return[$id_map['anon_id']] = $id_map['user_id'];
        }
        return $return;
    }

    public function getAllAnonIds() {
        $this->course_db->query("SELECT anon_id FROM users");
        return $this->course_db->rows();
    }
}
