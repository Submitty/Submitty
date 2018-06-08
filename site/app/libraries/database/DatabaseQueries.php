<?php

namespace app\libraries\database;

use app\exceptions\NotImplementedException;
use app\libraries\Core;
use app\libraries\FileUtils;
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

    public function loadAnnouncements($category_id){
        $this->course_db->query("SELECT t.*, e.category_id as category_id, w.category_desc FROM threads t, thread_categories e, categories_list w WHERE deleted = false and pinned = true and w.category_id = ? and t.id = e.thread_id and e.category_id = w.category_id ORDER BY t.id DESC", array($category_id));
        return $this->course_db->rows();
    }

    public function loadAnnouncementsWithoutCategory(){
        $this->course_db->query("SELECT t.*, e.category_id as category_id, w.category_desc FROM threads t, thread_categories e, categories_list w WHERE deleted = false and pinned = true and t.id = e.thread_id and e.category_id = w.category_id ORDER BY t.id DESC");
            return $this->course_db->rows();
    }

    public function loadThreadsWithoutCategory(){
         $this->course_db->query("SELECT t.*, e.category_id as category_id, w.category_desc FROM threads t, thread_categories e, categories_list w WHERE deleted = false and pinned = false and t.id = e.thread_id and e.category_id = w.category_id ORDER BY t.id DESC");
         return $this->course_db->rows();
    }

    public function loadThreads($category_id) {
        $this->course_db->query("SELECT t.*, e.category_id as category_id, w.category_desc FROM threads t, thread_categories e, categories_list w WHERE deleted = false and pinned = false and w.category_id = ? and t.id = e.thread_id and e.category_id = w.category_id ORDER BY t.id DESC", array($category_id));
        return $this->course_db->rows();
    }

    public function getCategoryIdForThread($thread_id) {
        $this->course_db->query("SELECT category_id from thread_categories t where t.thread_id = ?", array($thread_id));
        return $this->course_db->rows();
    }

    public function createPost($user, $content, $thread_id, $anonymous, $type, $first, $hasAttachment, $parent_post = -1){
        if(!$first && $parent_post == 0){
            $this->course_db->query("SELECT MIN(id) as id FROM posts where thread_id = ?", array($thread_id));
            $parent_post = $this->course_db->rows()[0]["id"];
        }

        try {
            $this->course_db->query("INSERT INTO posts (thread_id, parent_id, author_user_id, content, timestamp, anonymous, deleted, endorsed_by, resolved, type, has_attachment) VALUES (?, ?, ?, ?, current_timestamp, ?, ?, ?, ?, ?, ?)", array($thread_id, $parent_post, $user, $content, $anonymous, 0, NULL, 0, $type, $hasAttachment));
            $this->course_db->query("DELETE FROM viewed_responses WHERE thread_id = ?", array($thread_id));
            //retrieve generated thread_id
            $this->course_db->query("SELECT MAX(id) as max_id from posts where thread_id=? and author_user_id=?", array($thread_id, $user));
        } catch (DatabaseException $dbException){
            if($this->course_db->inTransaction()){
                $this->course_db->rollback();
            }
        }

        return $this->course_db->rows()[0]["max_id"];
    }

    public function getPosts(){
        $this->course_db->query("SELECT * FROM posts where deleted = false");
        return $this->course_db->rows();
    }

    public function getDeletedPostsByUser($user){
        $this->course_db->query("SELECT * FROM posts where deleted = true AND author_user_id = ?", array($user));
        return $this->course_db->rows();
    }

    public function getFirstPostForThread($thread_id) {
        $this->course_db->query("SELECT * FROM posts WHERE parent_id = -1 AND thread_id = ?", array($thread_id));
        return $this->course_db->rows()[0];
    }

    public function getPost($post_id){
        $this->course_db->query("SELECT * FROM posts where id = ?", array($post_id));
        return $this->course_db->rows()[0];
    }



    public function isStaffPost($author_id){
        $this->course_db->query("SELECT user_group FROM users WHERE user_id=?", array($author_id));
        return intval($this->course_db->rows()[0]['user_group']) <= 3;
    }

    public function createThread($user, $title, $content, $anon, $prof_pinned, $hasAttachment, $category_id){

        $this->course_db->beginTransaction();

        try {
        //insert data
        $this->course_db->query("INSERT INTO threads (title, created_by, pinned, deleted, merged_thread_id, merged_post_id, is_visible) VALUES (?, ?, ?, ?, ?, ?, ?)", array($title, $user, $prof_pinned, 0, -1, -1, true));

        //retrieve generated thread_id
        $this->course_db->query("SELECT MAX(id) as max_id from threads where title=? and created_by=?", array($title, $user));
        } catch(DatabaseException $dbException) {
            $this->course_db->rollback();
        }

        //Max id will be the most recent post
        $id = $this->course_db->rows()[0]["max_id"];

        $this->course_db->query("INSERT INTO thread_categories (thread_id, category_id) VALUES (?, ?)", array($id, $category_id));

        $post_id = $this->createPost($user, $content, $id, $anon, 0, true, $hasAttachment);

        $this->course_db->commit();

        return array("thread_id" => $id, "post_id" => $post_id);
    }
    public function getThreadTitle($thread_id){
        $this->course_db->query("SELECT title FROM threads where id=?", array($thread_id));
        return $this->course_db->rows()[0];
    }
    public function setAnnouncement($thread_id, $onOff){
        $this->course_db->query("UPDATE threads SET pinned = ? WHERE id = ?", array($onOff, $thread_id));
    }

    public function addPinnedThread($user_id, $thread_id, $added){
        if($added) {
            $this->course_db->query("INSERT INTO student_favorites(user_id, thread_id) VALUES (?,?)", array($user_id, $thread_id));
        } else {
            $this->course_db->query("DELETE FROM student_favorites where user_id=? and thread_id=?", array($user_id, $thread_id));
        }
    }

    public function loadPinnedThreads($user_id){
        $this->course_db->query("SELECT * FROM student_favorites WHERE user_id = ?", array($user_id));
        $rows = $this->course_db->rows();
        $favorite_threads = array();
        foreach ($rows as $row) {
            $favorite_threads[] = $row['thread_id'];
        }
        return $favorite_threads;
    }

    private function findChildren($post_id, $thread_id, &$children){
        $this->course_db->query("SELECT id from posts where deleted=false and parent_id=?", array($post_id));
        $row = $this->course_db->rows();
        for($i = 0; $i < count($row); $i++){
            $child_id = $row[$i]["id"];
            array_push($children, $child_id);
            $this->findChildren($child_id, $thread_id, $children);
        }
    }

    public function searchThreads($searchQuery){
    	$this->course_db->query("SELECT post_content, p_id, p_author, thread_id, thread_title, author, pin, anonymous, timestamp_post FROM (SELECT t.id as thread_id, t.title as thread_title, p.id as p_id, t.created_by as author, t.pinned as pin, p.timestamp as timestamp_post, p.content as post_content, p.anonymous, p.author_user_id as p_author, to_tsvector(p.content) || to_tsvector(p.author_user_id) || to_tsvector(t.title) as document from posts p, threads t JOIN (SELECT thread_id, timestamp from posts where parent_id = -1) p2 ON p2.thread_id = t.id where t.id = p.thread_id and p.deleted=false and t.deleted=false) p_doc JOIN (SELECT thread_id as t_id, timestamp from posts where parent_id = -1) p2 ON p2.t_id = p_doc.thread_id  where p_doc.document @@ plainto_tsquery(:q)", array(':q' => $searchQuery));
    	return $this->course_db->rows();
    }

    public function deletePost($post_id, $thread_id){
        $this->course_db->query("SELECT parent_id from posts where id=?", array($post_id));

        //If you delete the first post in a thread it deletes all posts in thread

        $parent_id = $this->course_db->rows()[0]["parent_id"];
        $children = array($post_id);
        $this->findChildren($post_id, $thread_id, $children);
        if($parent_id == -1){
            $this->course_db->query("UPDATE threads SET deleted = true WHERE id = ?", array($thread_id));
            $this->course_db->query("UPDATE posts SET deleted = true WHERE thread_id = ?", array($thread_id));
            return true;
        } else {
            foreach($children as $post_id){
                $this->course_db->query("UPDATE posts SET deleted = true WHERE id = ?", array($post_id));
            }
        } return false;
    }

    public function editPost($post_id, $content){
        try {
            $this->course_db->query("UPDATE posts SET content = ? where id = ?", array($content, $post_id));
        } catch(DatabaseException $dbException) {
            return false;
        } return true;
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

    /**
     * Gets the group that the user is in for a given class (used on homepage)
     *
     * Classes are distinct for each semester *and* course
     *
     * @param string $semester - class's working semester
     * @param string $course_name - class's course name
     * @param string $user_id - user id to be searched for
     * @return integer - group number of user in the given class
     */
    public function getGroupForUserInClass($semester, $course_name, $user_id) {
        $this->submitty_db->query("SELECT user_group FROM courses_users WHERE user_id = ? AND course = ? AND semester = ?", array($user_id, $course_name, $semester));
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
     * Gets whether a gradeable exists already
     *
     * @param $g_id the gradeable id to check for
     *
     * @return bool
     */
    public function existsGradeable($g_id) {
        $this->course_db->query('SELECT EXISTS (SELECT g_id FROM gradeable WHERE g_id= ?)', array($g_id));
        return $this->course_db->row()['exists'] ?? false; // This shouldn't happen, but let's assume false
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

    /** @noinspection PhpDocSignatureInspection */
    /**
     * @param null   $g_ids
     * @param null   $user_ids
     * @param string $section_key
     * @param string $sort_key
     * @param null   $g_type
     * @parma array  $extra_order_by
     *
     * @return DatabaseRowIterator
     */
    public function getGradeablesIterator($g_ids = null, $user_ids = null, $section_key="registration_section", $sort_key="u.user_id", $g_type = null, $extra_order_by = []) {
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
            $sections_keys = array_values($sections);
            $where = "WHERE {$section_key} IN (";
            foreach($sections_keys as $section) {
                $where .= "?" . ($section != $sections_keys[count($sections_keys)-1] ? "," : "");
                array_push($params, $section);
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

    public function getGradedComponentsCountByGradingSections($g_id, $sections, $section_key, $is_team) {
         $u_or_t="u";
        $users_or_teams="users";
        $user_or_team_id="user_id";
        if($is_team){
            $u_or_t="t";
            $users_or_teams="gradeable_teams";
            $user_or_team_id="team_id";
        }
        $return = array();
        $params = array($g_id);
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE {$section_key} IN (".implode(",", array_fill(0, count($sections), "?")).")";
            $params = array_merge($params, $sections);
        }
        $this->course_db->query("
SELECT {$u_or_t}.{$section_key}, count({$u_or_t}.*) as cnt
FROM {$users_or_teams} AS {$u_or_t}
INNER JOIN (
  SELECT * FROM gradeable_data AS gd
  LEFT JOIN (
  gradeable_component_data AS gcd
  INNER JOIN gradeable_component AS gc ON gc.gc_id = gcd.gc_id AND gc.gc_is_peer = {$this->course_db->convertBoolean(false)}
  )AS gcd ON gcd.gd_id = gd.gd_id WHERE gcd.g_id=?
) AS gd ON {$u_or_t}.{$user_or_team_id} = gd.gd_{$user_or_team_id}
{$where}
GROUP BY {$u_or_t}.{$section_key}
ORDER BY {$u_or_t}.{$section_key}", $params);
        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
            $return[$row[$section_key]] = intval($row['cnt']);
        }
        return $return;
    }
    public function getAverageComponentScores($g_id, $section_key, $is_team) {
        $u_or_t="u";
        $users_or_teams="users";
        $user_or_team_id="user_id";
        if($is_team){
            $u_or_t="t";
            $users_or_teams="gradeable_teams";
            $user_or_team_id="team_id";
        }
        $return = array();
        $this->course_db->query("
SELECT gc_id, gc_title, gc_max_value, gc_is_peer, gc_order, round(AVG(comp_score),2) AS avg_comp_score, round(stddev_pop(comp_score),2) AS std_dev, COUNT(*) FROM(
  SELECT gc_id, gc_title, gc_max_value, gc_is_peer, gc_order,
  CASE WHEN (gc_default + sum_points + gcd_score) > gc_upper_clamp THEN gc_upper_clamp
  WHEN (gc_default + sum_points + gcd_score) < gc_lower_clamp THEN gc_lower_clamp
  ELSE (gc_default + sum_points + gcd_score) END AS comp_score FROM(
    SELECT gcd.gc_id, gd.gd_{$user_or_team_id}, egv.{$user_or_team_id}, gc_title, gc_max_value, gc_is_peer, gc_order, gc_lower_clamp, gc_default, gc_upper_clamp,
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
      SELECT gd.gd_{$user_or_team_id}, gd.gd_id
      FROM gradeable_data AS gd
      WHERE gd.g_id=?
    ) AS gd ON gcd.gd_id=gd.gd_id
    INNER JOIN(
      SELECT {$u_or_t}.{$user_or_team_id}, {$u_or_t}.{$section_key}
      FROM {$users_or_teams} AS {$u_or_t}
      WHERE {$u_or_t}.{$section_key} IS NOT NULL
    ) AS {$u_or_t} ON gd.gd_{$user_or_team_id}={$u_or_t}.{$user_or_team_id}
    INNER JOIN(
      SELECT egv.{$user_or_team_id}, egv.active_version
      FROM electronic_gradeable_version AS egv
      WHERE egv.g_id=? AND egv.active_version>0
    ) AS egv ON egv.{$user_or_team_id}={$u_or_t}.{$user_or_team_id}
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
    public function getAverageAutogradedScores($g_id, $section_key, $is_team) {
        $u_or_t="u";
        $users_or_teams="users";
        $user_or_team_id="user_id";
        if($is_team){
            $u_or_t="t";
            $users_or_teams="gradeable_teams";
            $user_or_team_id="team_id";
        }
        $this->course_db->query("
SELECT round((AVG(score)),2) AS avg_score, round(stddev_pop(score), 2) AS std_dev, 0 AS max, COUNT(*) FROM(
   SELECT * FROM (
      SELECT (egv.autograding_non_hidden_non_extra_credit + egv.autograding_non_hidden_extra_credit + egv.autograding_hidden_non_extra_credit + egv.autograding_hidden_extra_credit) AS score
      FROM electronic_gradeable_data AS egv
      INNER JOIN {$users_or_teams} AS {$u_or_t} ON {$u_or_t}.{$user_or_team_id} = egv.{$user_or_team_id}, electronic_gradeable_version AS egd
      WHERE egv.g_id=? AND {$u_or_t}.{$section_key} IS NOT NULL AND egv.g_version=egd.active_version AND active_version>0 AND egd.{$user_or_team_id}=egv.{$user_or_team_id}
   )g
) as individual;
          ", array($g_id));
        if(count($this->course_db->rows()) == 0){
          echo("why");
          return;
        }
        return new SimpleStat($this->core, $this->course_db->rows()[0]);
    }
    public function getAverageForGradeable($g_id, $section_key, $is_team) {
        $u_or_t="u";
        $users_or_teams="users";
        $user_or_team_id="user_id";
        if($is_team){
            $u_or_t="t";
            $users_or_teams="gradeable_teams";
            $user_or_team_id="team_id";
        }
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
          SELECT egd.g_id, egd.{$user_or_team_id}, (autograding_non_hidden_non_extra_credit + autograding_non_hidden_extra_credit + autograding_hidden_non_extra_credit + autograding_hidden_extra_credit) AS autograding
          FROM electronic_gradeable_version AS egv
          LEFT JOIN electronic_gradeable_data AS egd ON egv.g_id=egd.g_id AND egv.{$user_or_team_id}=egd.{$user_or_team_id} AND active_version=g_version AND active_version>0
          )AS auto
        ON gd.g_id=auto.g_id AND gd_{$user_or_team_id}=auto.{$user_or_team_id}
        INNER JOIN {$users_or_teams} AS {$u_or_t} ON {$u_or_t}.{$user_or_team_id} = auto.{$user_or_team_id}
        WHERE gc.g_id=? AND {$u_or_t}.{$section_key} IS NOT NULL
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

    public function getNumUsersWhoViewedGrade($g_id) {
        $this->course_db->query("
SELECT COUNT(*) as cnt FROM gradeable_data
WHERE g_id = ?
AND gd_user_viewed_date IS NOT NULL
        ", array($g_id));

        return intval($this->course_db->row()['cnt']);
    }

    public function getNumUsersGraded($g_id) {
        $this->course_db->query("
SELECT COUNT(*) as cnt FROM gradeable_data
WHERE g_id = ?", array($g_id));

        return intval($this->course_db->row()['cnt']);
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
WHERE registration_section IS NOT NULL
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
WHERE registration_section IS NULL
GROUP BY rotating_section
ORDER BY rotating_section");
        return $this->course_db->rows();
    }

    public function getRegisteredUserIdsWithNullRotating() {
        $this->course_db->query("
SELECT user_id
FROM users
WHERE rotating_section IS NULL AND registration_section IS NOT NULL
ORDER BY user_id ASC");
        return array_map(function($elem) { return $elem['user_id']; }, $this->course_db->rows());
    }

    public function getRegisteredUserIds() {
        $this->course_db->query("
SELECT user_id
FROM users
WHERE registration_section IS NOT NULL
ORDER BY user_id ASC");
        return array_map(function($elem) { return $elem['user_id']; }, $this->course_db->rows());
    }

    public function setAllUsersRotatingSectionNull() {
        $this->course_db->query("UPDATE users SET rotating_section=NULL");
    }

    public function setNonRegisteredUsersRotatingSectionNull() {
        $this->course_db->query("UPDATE users SET rotating_section=NULL WHERE registration_section IS NULL");
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

    public function insertNewRegistrationSection($section) {
        $this->course_db->query("INSERT INTO sections_registration (sections_registration_id) VALUES(?)", array($section));
    }

    public function deleteRegistrationSection($section) {
        $this->course_db->query("DELETE FROM sections_registration WHERE sections_registration_id=?", array($section));
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
     * to be later filled in by the submitty_autograding_shipper.py and insert_database_version_data.py scripts.
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
     * @param AdminGradeable $gradeable
     */
    public function createNewGradeable(AdminGradeable $gradeable) {
        $params = array($gradeable->g_id,
            $gradeable->g_title,
            $gradeable->g_instructions_url,
            $gradeable->g_overall_ta_instructions,
            $gradeable->g_gradeable_type,
            var_export($gradeable->g_grade_by_registration, true),
            $gradeable->g_ta_view_start_date->format('Y/m/d H:i:s'),
            $gradeable->g_grade_start_date->format('Y/m/d H:i:s'),
            $gradeable->g_grade_released_date->format('Y/m/d H:i:s'),
            $gradeable->g_min_grading_group,
            $gradeable->g_syllabus_bucket);
        $this->course_db->query("
INSERT INTO gradeable(g_id, g_title, g_instructions_url,g_overall_ta_instructions, g_gradeable_type, g_grade_by_registration, g_ta_view_start_date, g_grade_start_date,  g_grade_released_date,  g_min_grading_group, g_syllabus_bucket)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $params);
        if ($gradeable->g_gradeable_type === GradeableType::ELECTRONIC_FILE) {
            $params = array($gradeable->g_id,
                $gradeable->eg_submission_open_date->format('Y/m/d H:i:s'),
                $gradeable->eg_submission_due_date->format('Y/m/d H:i:s'),
                var_export($gradeable->eg_is_repository, true),
                $gradeable->eg_subdirectory,
                var_export($gradeable->eg_team_assignment,true),
                $gradeable->eg_max_team_size,
                $gradeable->eg_team_lock_date->format('Y/m/d H:i:s'),
                var_export($gradeable->eg_use_ta_grading, true),
                var_export($gradeable->eg_student_view, true),
                var_export($gradeable->eg_student_submit, true),
                var_export($gradeable->eg_student_download, true),
                var_export($gradeable->eg_student_any_version, true),
                $gradeable->eg_config_path,
                $gradeable->eg_late_days,
                $gradeable->eg_precision,
                var_export($gradeable->eg_peer_grading, true),
                $gradeable->eg_peer_grade_set);
            $this->course_db->query("
INSERT INTO electronic_gradeable(g_id, eg_submission_open_date, eg_submission_due_date, eg_is_repository,
eg_subdirectory, eg_team_assignment, eg_max_team_size, eg_team_lock_date, eg_use_ta_grading, eg_student_view, eg_student_submit, eg_student_download,
eg_student_any_version, eg_config_path, eg_late_days, eg_precision, eg_peer_grading, eg_peer_grade_set)
VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $params);
        }
    }

    /**
     * Updates the current gradeable with new properties.
     * TODO: it could be worth while to be able to only update some properties
     *
     * @param AdminGradeable $gradeable
     */
    public function updateGradeable(AdminGradeable $gradeable) {
        $params = array($gradeable->g_title, $gradeable->g_instructions_url, $gradeable->g_overall_ta_instructions,
                        $gradeable->g_gradeable_type,
                        var_export($gradeable->g_grade_by_registration, true),
                        $gradeable->g_ta_view_start_date->format('Y/m/d H:i:s'),
                        $gradeable->g_grade_start_date->format('Y/m/d H:i:s'),
                        $gradeable->g_grade_released_date->format('Y/m/d H:i:s'),
                        $gradeable->g_min_grading_group, $gradeable->g_syllabus_bucket, $gradeable->g_id);
        $this->course_db->query("
UPDATE gradeable SET g_title=?, g_instructions_url=?, g_overall_ta_instructions=?,
g_gradeable_type=?, g_grade_by_registration=?, g_ta_view_start_date=?, g_grade_start_date=?,
g_grade_released_date=?, g_min_grading_group=?, g_syllabus_bucket=? WHERE g_id=?", $params);
        if ($gradeable->g_gradeable_type === 0) {
            $params = array($gradeable->eg_submission_open_date->format('Y/m/d H:i:s'),
                $gradeable->eg_submission_due_date->format('Y/m/d H:i:s'), var_export($gradeable->eg_is_repository, true),
                $gradeable->eg_subdirectory, var_export($gradeable->eg_team_assignment,true),
                $gradeable->eg_max_team_size, $gradeable->eg_team_lock_date->format('Y/m/d H:i:s'),
                var_export($gradeable->eg_use_ta_grading, true), var_export($gradeable->eg_student_view, true),
                var_export($gradeable->eg_student_submit, true), var_export($gradeable->eg_student_download, true),
                var_export($gradeable->eg_student_any_version, true), $gradeable->eg_config_path,
                $gradeable->eg_late_days, $gradeable->eg_precision, var_export($gradeable->eg_peer_grading, true),
                $gradeable->eg_peer_grade_set, $gradeable->g_id);
            $this->course_db->query("
UPDATE electronic_gradeable SET eg_submission_open_date=?, eg_submission_due_date=?, eg_is_repository=?,
eg_subdirectory=?, eg_team_assignment=?, eg_max_team_size=?, eg_team_lock_date=?, eg_use_ta_grading=?, eg_student_view=?, eg_student_submit=?,
eg_student_download=?, eg_student_any_version=?, eg_config_path=?, eg_late_days=?, eg_precision=?, eg_peer_grading=?, eg_peer_grade_set=? WHERE g_id=?", $params);
        }
    }

    public function createNewGradeableComponent(GradeableComponent $component, $gradeable_id) {
        $params = array($gradeable_id, $component->getTitle(), $component->getTaComment(),
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
        if ($gradeable->getGdId() !== null && $gradeable->getUser() !== null) {
            $this->course_db->query("UPDATE gradeable_data SET gd_user_viewed_date = NOW() WHERE gd_id=? and gd_user_id=?",
                array($gradeable->getGdId(), $gradeable->getUser()->getId()));
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * This updates the viewed date on a gradeable object (assuming that it has a set
     * $user object associated with it).
     *
     * @param \app\models\Gradeable $gradeable
     */
    public function resetUserViewedDate(Gradeable $gradeable) {
        if ($gradeable->getGdId() !== null && $gradeable->getUser() !== null) {
            $this->course_db->query("UPDATE gradeable_data SET gd_user_viewed_date = NULL WHERE gd_id=? and gd_user_id=?",
                array($gradeable->getGdId(), $gradeable->getUser()->getId()));
            return true;
        } else {
            return false;
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
     * Gets id's and titles of the electronic gradeables that have non-inherited teams
     * @return string
     */
    // public function getAllElectronicGradeablesWithBaseTeams() {
    //     $this->course_db->query('SELECT g_id, g_title FROM gradeable WHERE g_id=ANY(SELECT g_id FROM electronic_gradeable WHERE eg_team_assignment IS TRUE AND (eg_inherit_teams_from=\'\') IS NOT FALSE) ORDER BY g_title ASC');
    //     return $this->course_db->rows();
    // }

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
        $this->course_db->query("SELECT * FROM teams WHERE team_id=? AND state=1", array($team_id));
        if(count($this->course_db->rows()) == 0){
           //If this happens, then remove all invitations
            $this->course_db->query("DELETE FROM teams AS t
              WHERE team_id=?", array($team_id));
        }

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
     * Add ($g_id,$user_id) pair to table seeking_team
     * @param string $g_id
     * @param string $user_id
     */
    public function addToSeekingTeam($g_id,$user_id) {
        $this->course_db->query("INSERT INTO seeking_team(g_id, user_id) VALUES (?,?)", array($g_id, $user_id));
    }

    /**
     * Remove ($g_id,$user_id) pair from table seeking_team
     * @param string $g_id
     * @param string $user_id
     */
    public function removeFromSeekingTeam($g_id,$user_id) {
        $this->course_db->query("DELETE FROM seeking_team WHERE g_id=? AND user_id=?", array($g_id, $user_id));
    }

    /**
     * Return an array of user_id who are seeking team who passed gradeable_id
     * @param string $g_id
     * @return array $users_seeking_team
     */
    public function getUsersSeekingTeamByGradeableId($g_id) {
        $this->course_db->query("
          SELECT user_id
          FROM seeking_team
          WHERE g_id=?
          ORDER BY user_id",
            array($g_id));

        $users_seeking_team = array();
        foreach($this->course_db->rows() as $row) {
            array_push($users_seeking_team,$row['user_id']);
        }
        return $users_seeking_team;
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
public function getSubmittedTeamCountByGradingSections($g_id, $sections, $section_key) {
        $return = array();
        $params = array($g_id);
        $where = "";
        if (count($sections) > 0) {
            // Expand out where clause
            $sections_keys = array_values($sections);
            $where = "WHERE {$section_key} IN (";
            foreach($sections_keys as $section) {
                $where .= "?" . ($section != $sections_keys[count($sections_keys)-1] ? "," : "");
                array_push($params, $section);
            }
            $where .= ")";
        }
        $this->course_db->query("
SELECT count(*) as cnt, {$section_key}
FROM gradeable_teams
INNER JOIN electronic_gradeable_version
ON
gradeable_teams.team_id = electronic_gradeable_version.team_id
AND gradeable_teams.". $section_key . " IS NOT NULL
AND electronic_gradeable_version.active_version>0
AND electronic_gradeable_version.g_id=?
{$where}
GROUP BY {$section_key}
ORDER BY {$section_key}", $params);

        foreach ($this->course_db->rows() as $row) {
            $return[$row[$section_key]] = intval($row['cnt']);
        }

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
    public function getUsersWithTeamByGradingSections($g_id, $sections, $section_key) {
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
WHERE {$sections_query} user_id IN (
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
     * "Upserts" a given user's late days allowed effective at a given time.
     *
     * About $csv_options:
     * default behavior is to overwrite all late days for user and timestamp.
     * null value is for updating via form where radio button selection is
     * ignored, so it should do default behavior.  'csv_option_overwrite_all'
     * invokes default behavior for csv upload.  'csv_option_preserve_higher'
     * will preserve existing values when uploaded csv value is lower.
     *
     * @param string $user_id
     * @param string $timestamp
     * @param integer $days
     * @param string $csv_option value determined by selected radio button
     */
    public function updateLateDays($user_id, $timestamp, $days, $csv_option=null) {
		//q.v. PostgresqlDatabaseQueries.php
		throw new NotImplementedException();
	}

    /**
     * Delete a given user's allowed late days entry at given effective time
     * @param string $user_id
     * @param string $timestamp
     */
    public function deleteLateDays($user_id, $timestamp){
        $this->course_db->query("
          DELETE FROM late_days
          WHERE user_id=?
          AND since_timestamp=?", array($user_id, $timestamp));
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

	/**
	 * Retrieves all courses (and details) that are accessible by $user_id
	 *
	 * (u.user_id=? AND u.user_group=1) checks if $user_id is an instructor
	 * Instructors may access all of their courses
	 * (u.user_id=? AND c.status=1) checks if a course is active
	 * An active course may be accessed by all users
	 * Inactive courses may only be accessed by the instructor
	 *
	 * @param string $user_id
	 * @param string $submitty_path
	 * @return array - courses (and their details) accessible by $user_id
	 */
    public function getStudentCoursesById($user_id, $submitty_path) {
        $this->submitty_db->query("
SELECT u.semester, u.course
FROM courses_users u
INNER JOIN courses c ON u.course=c.course AND u.semester=c.semester
WHERE (u.user_id=? AND u.user_group=1) OR (u.user_id=? AND c.status=1)
ORDER BY u.course", array($user_id, $user_id));
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

    public function existsThread($thread_id){
        $this->course_db->query("SELECT 1 FROM threads where deleted = false AND id = ?", array($thread_id));
        $result = $this->course_db->rows();
        return count($result) > 0;
    }

    public function existsAnnouncements(){
        $this->course_db->query("SELECT MAX(id) FROM threads where deleted = false AND pinned = true");
        $result = $this->course_db->rows();
        return empty($result[0]["max"]) ? -1 : $result[0]["max"];
    }

    public function viewedThread($user, $thread_id){
      $this->course_db->query("SELECT * from viewed_responses where thread_id = ? and user_id = ?", array($thread_id, $user));
      return count($this->course_db->rows()) > 0;
    }

    public function getDisplayUserNameFromUserId($user_id){
      $this->course_db->query("SELECT user_firstname, user_preferred_firstname, user_lastname from users where user_id = ?", array($user_id));
      $name_rows = $this->course_db->rows()[0];
      $last_name =  " " . $name_rows["user_lastname"];
      if(empty($name_rows["user_preferred_firstname"])){
        $name = $name_rows["user_firstname"];
      } else {
        $name = $name_rows["user_preferred_firstname"];
      }
      $ar = array();
      $ar["first_name"] = $name;
      $ar["last_name"] = $last_name;
      return $ar;
    }

    public function addNewCategory($category) {
        //Can't get "RETURNING category_id" syntax to work
        $this->course_db->query("INSERT INTO categories_list (category_desc) VALUES (?) RETURNING category_id", array($category));
        $this->course_db->query("SELECT MAX(category_id) as category_id from categories_list");
        return $this->course_db->rows()[0];
    }

    public function getCategories(){
    	$this->course_db->query("SELECT * from categories_list ORDER BY category_id DESC");
    	return $this->course_db->rows();
    }

    public function getPostsForThread($current_user, $thread_id, $option = "tree"){
      if($thread_id == -1) {
        $announcement_id = $this->existsAnnouncements();
        if($announcement_id == -1){
          $this->course_db->query("SELECT MAX(id) as max from threads WHERE deleted = false and pinned = false");
          $thread_id = $this->course_db->rows()[0]["max"];
        } else {
          $thread_id = $announcement_id;
        }
      }
      if($option == 'alpha'){
        $this->course_db->query("SELECT posts.*, users.user_lastname FROM posts INNER JOIN users ON posts.author_user_id=users.user_id WHERE thread_id=? AND deleted = false ORDER BY user_lastname, posts.timestamp;", array($thread_id));
      } else {
        $this->course_db->query("SELECT * FROM posts WHERE thread_id=? AND deleted = false ORDER BY timestamp ASC", array($thread_id));
      }
      
      $result_rows = $this->course_db->rows();

      if(count($result_rows) > 0){
        $this->course_db->query("INSERT INTO viewed_responses(thread_id,user_id,timestamp) SELECT ?, ?, current_timestamp WHERE NOT EXISTS (SELECT 1 FROM viewed_responses WHERE thread_id=? AND user_id=?)", array($thread_id, $current_user, $thread_id, $current_user));
      }
      return $result_rows;
    }

    public function getRootPostOfNonMergedThread($thread_id, &$title, &$message) {
        $this->course_db->query("SELECT title FROM threads WHERE id = ? and merged_thread_id = -1 and merged_post_id = -1 and deleted = false", array($thread_id));
        $result_rows = $this->course_db->rows();
        if(count($result_rows) == 0) {
            $message = "Can't find thread";
            return false;
        }
        $title = $result_rows[0]['title'] . "\n";
        $this->course_db->query("SELECT id FROM posts where thread_id = ? and parent_id = -1", array($thread_id));
        $root_post = $this->course_db->rows()[0]['id'];
        return $root_post;
    }

    public function mergeThread($parent_thread_id, $child_thread_id, &$message){
        try{
            $this->course_db->beginTransaction();
            $parent_thread_title = null;
            $child_thread_title = null;
            if(!($parent_root_post = $this->getRootPostOfNonMergedThread($parent_thread_id, $parent_thread_title, $message))) {
                $this->course_db->rollback();
                return false;
            }
            if(!($child_root_post = $this->getRootPostOfNonMergedThread($child_thread_id, $child_thread_title, $message))) {
                $this->course_db->rollback();
                return false;
            }

            if($child_root_post <= $parent_root_post) {
                $message = "Child thread must be newer than parent thread";
                $this->course_db->rollback();
                return false;
            }

            $children = array($child_root_post);
            $this->findChildren($child_root_post, $child_thread_id, $children);

            // $merged_post_id is PK of linking node and $merged_thread_id is immediate parent thread_id
            $this->course_db->query("UPDATE threads SET merged_thread_id = ?, merged_post_id = ?, deleted = true WHERE id = ?", array($parent_thread_id, $child_root_post, $child_thread_id));
            foreach($children as $post_id){
                $this->course_db->query("UPDATE posts SET thread_id = ? WHERE id = ?", array($parent_thread_id,$post_id));
            }
            $this->course_db->query("UPDATE posts SET parent_id = ?, content = ? || content WHERE id = ?", array($parent_root_post, $child_thread_title, $child_root_post));

            $this->course_db->commit();
            return true;
        } catch (DatabaseException $dbException){
             $this->course_db->rollback();
        }
        return false;
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

    /**
     * Determines if a course is 'active' or if it was dropped.
     *
     * This is used to filter out courses displayed on the home screen, for when
     * a student has dropped a course.  SQL query checks for user_group=4 so
     * that only students are considered.  Returns false when course is dropped.
     * Returns true when course is still active, or user is not a student.
     *
     * @param string $user_id
     * @param string $course
     * @param string $semester
     * @return boolean
     */
    public function checkStudentActiveInCourse($user_id, $course, $semester) {
        $this->submitty_db->query("
            SELECT
                CASE WHEN registration_section IS NULL AND user_group=4 THEN FALSE
                ELSE TRUE
                END
            AS active
            FROM courses_users WHERE user_id=? AND course=? AND semester=?", array($user_id, $course, $semester));
        return $this->submitty_db->row()['active'];

    }

    /**
     * @param string $g_id
     */
    public function deleteGradeable($g_id) {
        $this->course_db->query("DELETE FROM gradeable WHERE g_id=?", array($g_id));
    }
}
