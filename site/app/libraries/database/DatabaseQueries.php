<?php

namespace app\libraries\database;

use app\exceptions\DatabaseException;
use app\exceptions\NotImplementedException;
use app\exceptions\ValidationException;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\libraries\GradeableType;
use app\models\Gradeable;
use app\models\gradeable\Component;
use app\models\gradeable\GradedComponent;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Mark;
use app\models\gradeable\RegradeRequest;
use app\models\gradeable\Submitter;
use app\models\gradeable\TaGradedGradeable;
use app\models\GradeableComponent;
use app\models\GradeableComponentMark;
use app\models\GradeableVersion;
use app\models\User;
use app\models\Notification;
use app\models\Email;
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

    /**
     * Helper function for generating sql query according to the given requirements
     */
    public function buildLoadThreadQuery($categories_ids, $thread_status, $unread_threads, $show_deleted, $show_merged_thread, $current_user,
                                        &$query_select, &$query_join, &$query_where, &$query_order, &$query_parameters,
                                        $want_categories, $want_order) {
        $query_raw_select = array();
        $query_raw_join   = array();
        $query_raw_where  = array("true");
        $query_raw_order  = array();
        $query_parameters = array();

        // Query Generation
        if(count($categories_ids) == 0) {
            $query_multiple_qmarks = "NULL";
        } else {
            $query_multiple_qmarks = "?".str_repeat(",?", count($categories_ids)-1);
        }
        if(count($thread_status) == 0) {
            $query_status = "true";
        } else {
            $query_status = "status in (?".str_repeat(",?", count($thread_status)-1).")";
        }
        $query_favorite = "case when sf.user_id is NULL then false else true end";

        // General
        {
            if($want_order){
                $query_raw_select[]     = "row_number() over(ORDER BY pinned DESC, ({$query_favorite}) DESC, t.id DESC) AS row_number";
            }
            $query_raw_select[]     = "t.*";
            $query_raw_select[]     = "({$query_favorite}) as favorite";
            $query_raw_select[]     = "CASE
                                        WHEN EXISTS(SELECT * FROM (posts p LEFT JOIN forum_posts_history fp ON p.id = fp.post_id AND p.author_user_id != fp.edit_author) AS pfp WHERE (pfp.author_user_id = ? OR pfp.edit_author = ?) AND pfp.thread_id = t.id) THEN true 
                                        ELSE false 
                                        END as current_user_posted";

            $query_parameters[]     = $current_user;
            $query_parameters[]     = $current_user;
            $query_raw_join[]       = "LEFT JOIN student_favorites sf ON sf.thread_id = t.id and sf.user_id = ?";
            $query_parameters[]     = $current_user;

            if(!$show_deleted) {
                $query_raw_where[]  = "deleted = false";
            }
            if(!$show_merged_thread) {
                $query_raw_where[]  = "merged_thread_id = -1";
            }

            $query_raw_where[]  = "? = (SELECT count(*) FROM thread_categories tc WHERE tc.thread_id = t.id and category_id IN ({$query_multiple_qmarks}))";
            $query_parameters[] = count($categories_ids);
            $query_parameters   = array_merge($query_parameters, $categories_ids);
            $query_raw_where[]  = "{$query_status}";
            $query_parameters   = array_merge($query_parameters, $thread_status);

            if($want_order){
                $query_raw_order[]  = "row_number";
            } else {
                $query_raw_order[]  = "true";
            }
        }
        // Categories
        if($want_categories) {
            $query_select_categories = "SELECT thread_id, array_to_string(array_agg(cl.category_id order by cl.rank nulls last, cl.category_id),'|')  as categories_ids, array_to_string(array_agg(cl.category_desc order by cl.rank nulls last, cl.category_id),'|') as categories_desc, array_to_string(array_agg(cl.color order by cl.rank nulls last, cl.category_id),'|') as categories_color FROM categories_list cl JOIN thread_categories e ON e.category_id = cl.category_id GROUP BY thread_id";

            $query_raw_select[] = "categories_ids";
            $query_raw_select[] = "categories_desc";
            $query_raw_select[] = "categories_color";

            $query_raw_join[] = "JOIN ({$query_select_categories}) AS QSC ON QSC.thread_id = t.id";
        }
        // Unread Threads
        if($unread_threads) {
            $query_raw_where[] = 

            "EXISTS(
                SELECT thread_id 
                FROM (posts LEFT JOIN forum_posts_history ON posts.id = forum_posts_history.post_id) AS jp
                WHERE(
                    jp.thread_id = t.id
                    AND NOT EXISTS(
                        SELECT thread_id 
                        FROM viewed_responses v 
                        WHERE v.thread_id = jp.thread_id
                            AND v.user_id = ?
                            AND (v.timestamp >= jp.timestamp
                            AND (jp.edit_timestamp IS NULL OR (jp.edit_timestamp IS NOT NULL AND v.timestamp >= jp.edit_timestamp))))))";
            $query_parameters[] = $current_user;
        }

        $query_select   = implode(", ", $query_raw_select);
        $query_join     = implode(" ", $query_raw_join);
        $query_where    = implode(" and ", $query_raw_where);
        $query_order    = implode(", ", $query_raw_order);
    }

    /**
     * Order: Favourite and Announcements => Announcements only => Favourite only => Others
     *
     * @param  array(int)    categories_ids     Filter threads having atleast provided categories
     * @param  array(int)    thread_status      Filter threads having thread status among $thread_status
     * @param  bool          unread_threads     Filter threads to show only unread threads
     * @param  bool          show_deleted       Consider deleted threads
     * @param  bool          show_merged_thread Consider merged threads
     * @param  string        current_user       user_id of currrent user
     * @param  int           blockNumber        Index of window of thread list(-1 for last)
     * @param  int           thread_id          If blockNumber is not known, find it using thread_id
     * @return array('block_number' => int, 'threads' => array(threads))    Ordered filtered threads
     */
    public function loadThreadBlock($categories_ids, $thread_status, $unread_threads, $show_deleted, $show_merged_thread, $current_user, $blockNumber, $thread_id){
        $blockSize = 30;
        $loadLastPage = false;

        $query_raw_select = null;
        $query_raw_join   = null;
        $query_raw_where  = null;
        $query_raw_order  = null;
        $query_parameters = null;
        // $blockNumber is 1 based index
        if($blockNumber <= -1) {
            // Find the last block
            $this->buildLoadThreadQuery($categories_ids, $thread_status, $unread_threads, $show_deleted, $show_merged_thread, $current_user, $query_select, $query_join, $query_where, $query_order, $query_parameters, false, false);
            $query = "SELECT count(*) FROM (SELECT {$query_select} FROM threads t {$query_join} WHERE {$query_where}) AS SUBQUERY";
            $this->course_db->query($query, $query_parameters);
            $results = $this->course_db->rows();
            $row_count = $results[0]['count'];
            $blockNumber = 1 + floor(($row_count-1)/$blockSize);
        } else if($blockNumber == 0) {
            // Load first block as default
            $blockNumber = 1;
            if($thread_id >= 1)
            {
                // Find $blockNumber
                $this->buildLoadThreadQuery($categories_ids, $thread_status, $unread_threads, $show_deleted, $show_merged_thread, $current_user, $query_select, $query_join, $query_where, $query_order, $query_parameters, false, true);
                $query = "SELECT SUBQUERY.row_number as row_number FROM (SELECT {$query_select} FROM threads t {$query_join} WHERE {$query_where} ORDER BY {$query_order}) AS SUBQUERY WHERE SUBQUERY.id = ?";
                $query_parameters[] = $thread_id;
                $this->course_db->query($query, $query_parameters);
                $results = $this->course_db->rows();
                if(count($results) > 0) {
                    $row_number = $results[0]['row_number'];
                    $blockNumber = 1 + floor(($row_number-1)/$blockSize);
                }
            }
        }
        $query_offset = ($blockNumber-1) * $blockSize;
        $this->buildLoadThreadQuery($categories_ids, $thread_status, $unread_threads, $show_deleted, $show_merged_thread, $current_user, $query_select, $query_join, $query_where, $query_order, $query_parameters, true, true);
        $query = "SELECT {$query_select} FROM threads t {$query_join} WHERE {$query_where} ORDER BY {$query_order} LIMIT ? OFFSET ?";
        $query_parameters[] = $blockSize;
        $query_parameters[] = $query_offset;
        // Execute
        $this->course_db->query($query, $query_parameters);
        $results = array();
        $results['block_number'] = $blockNumber;
        $results['threads'] = $this->course_db->rows();
        return $results;
    }

    public function getCategoriesIdForThread($thread_id) {
        $this->course_db->query("SELECT category_id from thread_categories t where t.thread_id = ?", array($thread_id));
        $categories_list = array();
        foreach ($this->course_db->rows() as $row) {
            $categories_list[] = (int)$row["category_id"];
        }
        return $categories_list;
    }

    public function createPost($user, $content, $thread_id, $anonymous, $type, $first, $hasAttachment, $parent_post = -1){
        if(!$first && $parent_post == 0){
            $this->course_db->query("SELECT MIN(id) as id FROM posts where thread_id = ?", array($thread_id));
            $parent_post = $this->course_db->rows()[0]["id"];
        }

        try {
            $this->course_db->query("INSERT INTO posts (thread_id, parent_id, author_user_id, content, timestamp, anonymous, deleted, endorsed_by, type, has_attachment) VALUES (?, ?, ?, ?, current_timestamp, ?, ?, ?, ?, ?)", array($thread_id, $parent_post, $user, $content, $anonymous, 0, NULL, $type, $hasAttachment));
            $this->course_db->query("SELECT MAX(id) as max_id from posts where thread_id=? and author_user_id=?", array($thread_id, $user));
        } catch (DatabaseException $dbException){
            if($this->course_db->inTransaction()){
                $this->course_db->rollback();
            }
        }

        return $this->course_db->rows()[0]["max_id"];
    }

	public function getResolveState($thread_id) {
		$this->course_db->query("SELECT status from threads where id = ?", array($thread_id));
		return $this->course_db->rows();
	}

	public function updateResolveState($thread_id, $state) {
		if(in_array($state, array(-1, 0, 1))) {
			$this->course_db->query("UPDATE threads set status = ? where id = ?", array($state, $thread_id));
			return true;
		}
		return false;
	}

    public function updateNotificationSettings($results) {
        $values = implode(', ', array_fill(0, count($results)+1, '?'));
        $keys = implode(', ', array_keys($results));
        $updates = '';

        foreach($results as $key => $value) {
            if($value != 'false') {
                $results[$key] = 'true';
            }
            $this->core->getUser()->updateUserNotificationSettings($key, $results[$key] == 'true' ? true : false);
            $updates .= $key . ' = ?,';
        }

        $updates = substr($updates, 0, -1);
        $test = array_merge(array_merge(array($this->core->getUser()->getId()), array_values($results)), array_values($results));
        $this->course_db->query("INSERT INTO notification_settings (user_id, $keys)
                                    VALUES
                                     (
                                        $values
                                     )
                                    ON CONFLICT (user_id)
                                    DO
                                     UPDATE
                                        SET $updates", $test);
    }

	public function getAuthorOfThread($thread_id) {
		$this->course_db->query("SELECT created_by from threads where id = ?", array($thread_id));
		return $this->course_db->rows()[0]['created_by'];
	}

    public function getPosts(){
        $this->course_db->query("SELECT * FROM posts where deleted = false ORDER BY timestamp ASC");
        return $this->course_db->rows();
    }

    public function getPostHistory($post_id){
        $this->course_db->query("SELECT * FROM forum_posts_history where post_id = ? ORDER BY edit_timestamp DESC", array($post_id));
        return $this->course_db->rows();
    }

    public function getDeletedPostsByUser($user){
        $this->course_db->query("SELECT * FROM posts where deleted = true AND author_user_id = ?", array($user));
        return $this->course_db->rows();
    }

    public function getFirstPostForThread($thread_id) {
        $this->course_db->query("SELECT * FROM posts WHERE parent_id = -1 AND thread_id = ?", array($thread_id));
        $rows = $this->course_db->rows();
        if(count($rows) > 0) {
            return $rows[0];
        } else {
            return null;
        }
    }

    public function getPost($post_id){
        $this->course_db->query("SELECT * FROM posts where id = ?", array($post_id));
        return $this->course_db->rows()[0];
    }

    public function removeNotificationsPost($post_id) {
        //Deletes all children notifications i.e. this posts replies
        $this->course_db->query("DELETE FROM notifications where metadata::json->>1 = ?", array($post_id));
        //Deletes parent notification i.e. this post is a reply
        $this->course_db->query("DELETE FROM notifications where metadata::json->>2 = ?", array($post_id));
    }

    public function isStaffPost($author_id){
        $this->course_db->query("SELECT user_group FROM users WHERE user_id=?", array($author_id));
        return intval($this->course_db->rows()[0]['user_group']) <= 3;
    }

    public function getUnviewedPosts($thread_id, $user_id){
        if($thread_id == -1) {
            $this->course_db->query("SELECT MAX(id) as max from threads WHERE deleted = false and merged_thread_id = -1 GROUP BY pinned ORDER BY pinned DESC");
            $rows = $this->course_db->rows();
            if(!empty($rows)) {
                $thread_id = $rows[0]["max"];
            } else {
                // No thread found, hence no posts found
                return array();
            }
        } 
        $this->course_db->query("SELECT DISTINCT id FROM (posts LEFT JOIN forum_posts_history ON posts.id = forum_posts_history.post_id) AS pfph WHERE pfph.thread_id = ? AND NOT EXISTS(SELECT * FROM viewed_responses v WHERE v.thread_id = ? AND v.user_id = ? AND (v.timestamp >= pfph.timestamp AND (pfph.edit_timestamp IS NULL OR (pfph.edit_timestamp IS NOT NULL AND v.timestamp >= pfph.edit_timestamp))))", array($thread_id, $thread_id, $user_id));
        $rows = $this->course_db->rows();
        if(empty($rows)){
            $rows = array();
        }
        return $rows;
    }

    public function createThread($user, $title, $content, $anon, $prof_pinned, $status, $hasAttachment, $categories_ids){

        $this->course_db->beginTransaction();

        try {
        //insert data
        $this->course_db->query("INSERT INTO threads (title, created_by, pinned, status, deleted, merged_thread_id, merged_post_id, is_visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", array($title, $user, $prof_pinned, $status, 0, -1, -1, true));

        //retrieve generated thread_id
        $this->course_db->query("SELECT MAX(id) as max_id from threads where title=? and created_by=?", array($title, $user));
        } catch(DatabaseException $dbException) {
            $this->course_db->rollback();
        }

        //Max id will be the most recent post
        $id = $this->course_db->rows()[0]["max_id"];
        foreach ($categories_ids as $category_id) {
            $this->course_db->query("INSERT INTO thread_categories (thread_id, category_id) VALUES (?, ?)", array($id, $category_id));
        }

        $post_id = $this->createPost($user, $content, $id, $anon, 0, true, $hasAttachment);

        $this->course_db->commit();

        return array("thread_id" => $id, "post_id" => $post_id);
    }

    public function getThreadsBefore($timestamp, $page) {
        // TODO: Handle request page wise
        $this->course_db->query("SELECT t.id as id, title from threads t JOIN posts p on p.thread_id = t.id and parent_id = -1 WHERE timestamp < ? and t.deleted = false", array($timestamp));
        return $this->course_db->rows();
    }

    public function getThread($thread_id) {
        $this->course_db->query("SELECT * from threads where id = ?", array($thread_id));
        return $this->course_db->rows();
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

    private function findChildren($post_id, $thread_id, &$children, $get_deleted = false){
        $query_delete = $get_deleted?"true":"deleted = false";
        $this->course_db->query("SELECT id from posts where {$query_delete} and parent_id=?", array($post_id));
        $row = $this->course_db->rows();
        for($i = 0; $i < count($row); $i++){
            $child_id = $row[$i]["id"];
            array_push($children, $child_id);
            $this->findChildren($child_id, $thread_id, $children, $get_deleted);
        }
    }

    public function searchThreads($searchQuery){
    	$this->course_db->query("SELECT post_content, p_id, p_author, thread_id, thread_title, author, pin, anonymous, timestamp_post FROM (SELECT t.id as thread_id, t.title as thread_title, p.id as p_id, t.created_by as author, t.pinned as pin, p.timestamp as timestamp_post, p.content as post_content, p.anonymous, p.author_user_id as p_author, to_tsvector(p.content) || to_tsvector(t.title) as document from posts p, threads t JOIN (SELECT thread_id, timestamp from posts where parent_id = -1) p2 ON p2.thread_id = t.id where t.id = p.thread_id and p.deleted=false and t.deleted=false) p_doc where p_doc.document @@ plainto_tsquery(:q) ORDER BY timestamp_post DESC", array(':q' => $searchQuery));
    	return $this->course_db->rows();
    }

    public function threadExists(){
		$this->course_db->query("SELECT id from threads where deleted = false LIMIT 1");
		return count($this->course_db->rows()) == 1;
    }

    public function visitThread($current_user, $thread_id){
        $this->course_db->query("INSERT INTO viewed_responses(thread_id,user_id,timestamp) VALUES(?, ?, current_timestamp) ON CONFLICT (thread_id, user_id) DO UPDATE SET timestamp = current_timestamp", array($thread_id, $current_user));
    }
    /**
     * Set delete status for given post and all descendant
     *
     * If delete status of the first post in a thread is changed, it will also update thread delete status
     *
     * @param integer $post_id
     * @param integer $thread_id
     * @param integer(0/1) $newStatus - 1 implies deletion and 0 as undeletion
     * @return boolean - Is first post of thread
     */
    public function setDeletePostStatus($post_id, $thread_id, $newStatus){
        $this->course_db->query("SELECT parent_id from posts where id=?", array($post_id));
        $parent_id = $this->course_db->rows()[0]["parent_id"];
        $children = array($post_id);
        $get_deleted = ($newStatus?false:true);
        $this->findChildren($post_id, $thread_id, $children, $get_deleted);

        if(!$newStatus) {
            // On undelete, parent post must have deleted = false
            if($parent_id!=-1) {
                if($this->getPost($parent_id)['deleted']) {
                    return null;
                }
            }
        }
        if($parent_id == -1){
            $this->course_db->query("UPDATE threads SET deleted = ? WHERE id = ?", array($newStatus, $thread_id));
            $this->course_db->query("UPDATE posts SET deleted = ? WHERE thread_id = ?", array($newStatus, $thread_id));
            return true;
        } else {
            foreach($children as $post_id){
                $this->course_db->query("UPDATE posts SET deleted = ? WHERE id = ?", array($newStatus, $post_id));
            }
        } return false;
    }

    public function getParentPostId($child_id) {
        $this->course_db->query("SELECT parent_id from posts where id = ?", array($child_id));
        return $this->course_db->rows()[0]['parent_id'];
    }

    public function editPost($original_creator, $user, $post_id, $content, $anon){
        try {
            // Before making any edit to $post_id, forum_posts_history will not have any corresponding entry
            // forum_posts_history will store all history state of the post(if edited at any point of time)
            $this->course_db->beginTransaction();
            // Insert first version of post during first edit
            $this->course_db->query("INSERT INTO forum_posts_history(post_id, edit_author, content, edit_timestamp) SELECT id, author_user_id, content, timestamp FROM posts WHERE id = ? AND NOT EXISTS (SELECT 1 FROM forum_posts_history WHERE post_id = ?)", array($post_id, $post_id));
            // Update current post
            $this->course_db->query("UPDATE posts SET content =  ?, anonymous = ? where id = ?", array($content, $anon, $post_id));
            // Insert latest version of post into forum_posts_history
            $this->course_db->query("INSERT INTO forum_posts_history(post_id, edit_author, content, edit_timestamp) SELECT id, ?, content, current_timestamp FROM posts WHERE id = ?", array($user, $post_id));
            $this->course_db->query("UPDATE notifications SET content = substring(content from '.+?(?=from)') || 'from ' || ? where metadata::json->>1 = ? and metadata::json->>2 = ?", array(Utils::getDisplayNameForum($anon, $this->getDisplayUserInfoFromUserId($original_creator)), $this->getParentPostId($post_id), $post_id));
            $this->course_db->commit();
        } catch(DatabaseException $dbException) {
            $this->course_db->rollback();
            return false;
        } return true;
    }

    public function editThread($thread_id, $thread_title, $categories_ids, $status) {
        try {
            $this->course_db->beginTransaction();
            $this->course_db->query("UPDATE threads SET title = ?, status = ? WHERE id = ?", array($thread_title, $status, $thread_id));
            $this->course_db->query("DELETE FROM thread_categories WHERE thread_id = ?", array($thread_id));
            foreach ($categories_ids as $category_id) {
                $this->course_db->query("INSERT INTO thread_categories (thread_id, category_id) VALUES (?, ?)", array($thread_id, $category_id));
            }
            $this->course_db->commit();
        } catch(DatabaseException $dbException) {
            $this->course_db->rollback();
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


    public function getGradeableVersionHasAutogradingResults($g_id, $version, $user_id, $team_id) {
        $query = "SELECT * FROM electronic_gradeable_data WHERE g_id=? AND g_version=? AND ";
        if($user_id === null) {
            $query .= "team_id=?";
            $params = [$g_id, $version, $team_id];
        }
        else {
            $query .= "user_id=?";
            $params = [$g_id, $version, $user_id];
        }
        $this->course_db->query($query, $params);
        return count($this->course_db->rows()) > 0 && $this->course_db->rows()[0]['autograding_complete'] === true;
    }

    protected function createParamaterList($len) {
        return '(' . implode(',', array_fill(0, $len, '?')) . ')';
    }


    // Moved from class LateDaysCalculation on port from TAGrading server.  May want to incorporate late day information into gradeable object rather than having a separate query
    public function getLateDayUpdates($user_id) {
        if($user_id != null) {
            $query = "SELECT * FROM late_days WHERE user_id";
            if (is_array($user_id)) {
                $query .= ' IN ' . $this->createParamaterList(count($user_id));
                $params = $user_id;
            }
            else {
                $query .= '=?';
                $params = array($user_id);
            }
            $query .= ' ORDER BY since_timestamp';
            $this->course_db->query($query, $params);
        }
        else {
            $this->course_db->query("SELECT * FROM late_days");
        }
        // Parse the date-times
        return array_map(function ($arr)  {
            $arr['since_timestamp'] = DateUtils::parseDateTime($arr['since_timestamp'], $this->core->getConfig()->getTimezone());
            return $arr;
        }, $this->course_db->rows());
    }

    public function getLateDayInformation($user_id) {
        throw new NotImplementedException();
    }

    public function getUsersByRegistrationSections($sections, $orderBy="registration_section") {
        $return = array();
        if (count($sections) > 0) {
        	$orderBy = str_replace("registration_section","SUBSTRING(registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(registration_section, '[0-9]+')::INT, -1), SUBSTRING(registration_section, '[^0-9]*$')",$orderBy);
            $values = $this->createParamaterList(count($sections));
            $this->course_db->query("SELECT * FROM users AS u WHERE registration_section IN {$values} ORDER BY {$orderBy}", $sections);
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
            $where = "WHERE {$section_key} IN " . $this->createParamaterList(count($sections));
            $params = $sections;
        }
        if ($section_key === 'registration_section') {
            $orderby = "SUBSTRING({$section_key}, '^[^0-9]*'), COALESCE(SUBSTRING({$section_key}, '[0-9]+')::INT, -1), SUBSTRING({$section_key}, '[^0-9]*$')";
        }
        else {
            $orderby = $section_key;
        }
        $this->course_db->query("
SELECT count(*) as cnt, {$section_key}
FROM users
{$where}
GROUP BY {$section_key}
ORDER BY {$orderby}", $params);
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
        $params = array($g_id);
        $where = "";
        if (count($sections) > 0) {
            // Expand out where clause
            $sections_keys = array_values($sections);
            $placeholders = $this->createParamaterList(count($sections_keys));
            $where = "WHERE {$section_key} IN {$placeholders}";
            $params = array_merge($params, $sections_keys);
        }
        if ($section_key === 'registration_section') {
            $orderby = "SUBSTRING({$section_key}, '^[^0-9]*'), COALESCE(SUBSTRING({$section_key}, '[0-9]+')::INT, -1), SUBSTRING({$section_key}, '[^0-9]*$')";
        }
        else {
            $orderby = $section_key;
        }
        $this->course_db->query("
SELECT count(*) as cnt, {$section_key}
FROM users
INNER JOIN electronic_gradeable_version
ON
users.user_id = electronic_gradeable_version.user_id
AND users.". $section_key . " IS NOT NULL
AND electronic_gradeable_version.active_version>0
AND electronic_gradeable_version.g_id=?
{$where}
GROUP BY {$section_key}
ORDER BY {$orderby}", $params);

        foreach ($this->course_db->rows() as $row) {
            $return[$row[$section_key]] = intval($row['cnt']);
        }

        return $return;
    }

    public function getTotalSubmittedTeamCountByGradingSections($g_id, $sections, $section_key) {
        $return = array();
        $params = array($g_id);
        $where = "";
        if (count($sections) > 0) {
            // Expand out where clause
            $sections_keys = array_values($sections);
            $placeholders = $this->createParamaterList(count($sections_keys));
            $where = "WHERE {$section_key} IN {$placeholders}";
            $params = array_merge($params, $sections_keys);
        }
        if ($section_key === 'registration_section') {
            $orderby = "SUBSTRING({$section_key}, '^[^0-9]*'), COALESCE(SUBSTRING({$section_key}, '[0-9]+')::INT, -1), SUBSTRING({$section_key}, '[^0-9]*$')";
        }
        else {
            $orderby = $section_key;
        }
        $this->course_db->query("
            SELECT COUNT(*) as cnt, {$section_key}
            FROM gradeable_teams
            INNER JOIN electronic_gradeable_version
                    ON gradeable_teams.team_id = electronic_gradeable_version.team_id
                   AND gradeable_teams.{$section_key} IS NOT NULL
                   AND electronic_gradeable_version.active_version>0
                   AND electronic_gradeable_version.g_id=?
            {$where}
            GROUP BY {$section_key}
            ORDER BY {$orderby}
        ", $params);

        foreach ($this->course_db->rows() as $row) {
            $return[$row[$section_key]] = intval($row['cnt']);
        }

        return $return;
    }

    /**
     * Get an array of Teams for a Gradeable matching the given registration sections
     * @param string $g_id
     * @param array $sections
     * @param string $orderBy
     * @return Team[]
     */
    public function getTeamsByGradeableAndRegistrationSections($g_id, $sections, $orderBy="registration_section") {
        throw new NotImplementedException();
    }

    /**
     * Get an array of Teams for a Gradeable matching the given rotating sections
     * @param string $g_id
     * @param array $sections
     * @param string $orderBy
     * @return Team[]
     */
    public function getTeamsByGradeableAndRotatingSections($g_id, $sections, $orderBy="rotating_section") {
        throw new NotImplementedException();
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
            $where = "WHERE {$section_key} IN " . $this->createParamaterList(count($sections));
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
      SELECT (egd.autograding_non_hidden_non_extra_credit + egd.autograding_non_hidden_extra_credit + egd.autograding_hidden_non_extra_credit + egd.autograding_hidden_extra_credit) AS score
      FROM electronic_gradeable_data AS egd
      INNER JOIN (
          SELECT {$user_or_team_id}, {$section_key} FROM {$users_or_teams}
      ) AS {$u_or_t}
      ON {$u_or_t}.{$user_or_team_id} = egd.{$user_or_team_id}
      INNER JOIN (
          SELECT g_id, {$user_or_team_id}, active_version FROM electronic_gradeable_version AS egv
          WHERE active_version > 0
      ) AS egv
      ON egd.g_id=egv.g_id AND egd.{$user_or_team_id}=egv.{$user_or_team_id} AND egd.g_version=egv.active_version
      WHERE egd.g_id=? AND {$u_or_t}.{$section_key} IS NOT NULL
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

    public function getNumUsersWhoViewedGradeBySections($gradeable, $sections) {
        $table = $gradeable->isTeamAssignment() ? 'gradeable_teams' : 'users';
        $grade_type = $gradeable->isGradeByRegistration() ? 'registration' : 'rotating';
        $type = $gradeable->isTeamAssignment() ? 'team' : 'user';

        $params = array($gradeable->getId());

        $sections_query = "";
        if (count($sections) > 0) {
            $sections_query= "{$grade_type}_section IN " . $this->createParamaterList(count($sections));
            $params = array_merge($sections, $params);
        }

        $this->course_db->query("
            SELECT COUNT(*) as cnt
            FROM gradeable_data AS gd
            INNER JOIN (
                SELECT u.{$type}_id, u.{$grade_type}_section FROM {$table} AS u
                WHERE u.{$sections_query}
            ) AS u
            ON gd.gd_{$type}_id=u.{$type}_id
            WHERE gd.g_id = ? AND gd.gd_user_viewed_date IS NOT NULL
        ", $params);

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
            $where = "WHERE sections_registration_id IN " . $this->createParamaterList(count($sections));
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
ORDER BY SUBSTRING(g.sections_registration_id, '^[^0-9]*'), COALESCE(SUBSTRING(g.sections_registration_id, '[0-9]+')::INT, -1), SUBSTRING(g.sections_registration_id, '[^0-9]*$'), g.user_id", $params);
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
            $where = " AND sections_rotating_id IN " . $this->createParamaterList(count($sections));
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

    public function getRotatingSectionsForGradeableAndUser($g_id, $user_id = null) {
        $params = [$g_id];
        $where = "";
        if ($user_id !== null) {
            $params[] = $user_id;
            $where = " AND user_id=?";
        }
        $this->course_db->query("
            SELECT sections_rotating_id
            FROM grading_rotating
            WHERE g_id=? {$where}", $params);
        $return = array();
        foreach ($this->course_db->rows() as $row) {
            $return[] = $row['sections_rotating_id'];
        }
        return $return;
    }

    public function getUsersByRotatingSections($sections, $orderBy="rotating_section") {
        $return = array();
        if (count($sections) > 0) {
            $placeholders = $this->createParamaterList(count($sections));
            $this->course_db->query("SELECT * FROM users AS u WHERE rotating_section IN {$placeholders} ORDER BY {$orderBy}", $sections);
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
        $this->course_db->query("SELECT * FROM sections_registration ORDER BY SUBSTRING(sections_registration_id, '^[^0-9]*'), COALESCE(SUBSTRING(sections_registration_id, '[0-9]+')::INT, -1), SUBSTRING(sections_registration_id, '[^0-9]*$') ");
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

    /**
     * Gets rotating sections of each grader for a gradeable
     * @param $gradeable_id
     * @return array An array (indexed by user id) of arrays of section numbers
     */
    public function getRotatingSectionsByGrader($gradeable_id) {
        throw new NotImplementedException();
    }

    public function getGradersByUserType() {
        $this->course_db->query(
            "SELECT user_firstname, user_lastname, user_id, user_group FROM users WHERE user_group < 4 ORDER BY user_group, user_id ASC");
        $users = [];

        foreach ($this->course_db->rows() as $row) {
            $users[$row['user_group']][] = [$row['user_id'], $row['user_firstname'], $row['user_lastname']];
        }
        return $users;
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

    /**
     * Get all team ids for all gradeables
     * @return string[][] Map of gradeable_id => [ team ids ]
     */
    public function getTeamIdsAllGradeables() {
        $this->course_db->query("SELECT team_id, g_id FROM gradeable_teams");

        $gradeable_ids = [];
        $rows = $this->course_db->rows();
        foreach ($rows as $row) {
            $g_id = $row['g_id'];
            $team_id = $row['team_id'];
            if (!array_key_exists($g_id, $gradeable_ids)) {
                $gradeable_ids[$g_id] = [];
            }
            $gradeable_ids[$g_id][] = $team_id;
        }
        return $gradeable_ids;
    }

    /**
     * Get all team ids for all gradeables where the teams are in rotating section NULL
     * @return string[][] Map of gradeable_id => [ team ids ]
     */
    public function getTeamIdsWithNullRotating() {
        $this->course_db->query("SELECT team_id, g_id FROM gradeable_teams WHERE rotating_section IS NULL");

        $gradeable_ids = [];
        $rows = $this->course_db->rows();
        foreach ($rows as $row) {
            $g_id = $row['g_id'];
            $team_id = $row['team_id'];
            if (!array_key_exists($g_id, $gradeable_ids)) {
                $gradeable_ids[$g_id] = [];
            }
            $gradeable_ids[$g_id][] = $team_id;
        }
        return $gradeable_ids;
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

    public function setAllTeamsRotatingSectionNull() {
        $this->course_db->query("UPDATE gradeable_teams SET rotating_section=NULL");
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
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $this->submitty_db->query("INSERT INTO courses_registration_sections (semester, course, registration_section_id) VALUES (?,?,?) ON CONFLICT DO NOTHING", array($semester, $course, $section));
        return $this->submitty_db->getrowcount();
    }

    public function deleteRegistrationSection($section) {
       	$semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $this->submitty_db->query("DELETE FROM courses_registration_sections WHERE semester=? AND course=? AND registration_section_id=?", array($semester, $course, $section));
        return $this->submitty_db->getRowCount();
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
        $update_string = $this->createParamaterList(count($users));
        $this->course_db->query("UPDATE users SET rotating_section=? WHERE user_id IN {$update_string}", $update_array);
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


    public function getAllSectionsForGradeable($gradeable) {
         $grade_type = $gradeable->isGradeByRegistration() ? 'registration' : 'rotating';

         if ($gradeable->isGradeByRegistration()) {
             $this->course_db->query("
                 SELECT * FROM sections_registration
                 ORDER BY SUBSTRING(sections_registration_id, '^[^0-9]*'),
                 COALESCE(SUBSTRING(sections_registration_id, '[0-9]+')::INT, -1),
                 SUBSTRING(sections_registration_id, '[^0-9]*$') ");
         } else {
             $this->course_db->query("
                 SELECT * FROM sections_rotating
                 ORDER BY sections_rotating_id ");
         }

         $sections = $this->course_db->rows();
         foreach ($sections as $i => $section)
             $sections[$i] = $section["sections_{$grade_type}_id"];
         return $sections;
    }

    /**
     * Gets the ids of all submitters who received a mark
     * @param Mark $mark
     * @param User $grader
     * @param Gradeable $gradeable
     * @return string[]
     */
    public function getSubmittersWhoGotMarkBySection($mark, $grader, $gradeable) {
         // Switch the column based on gradeable team-ness
         $type = $mark->getComponent()->getGradeable()->isTeamAssignment() ? 'team' : 'user';
         $row_type = $type . "_id";

         $params = array($grader->getId(), $mark->getId());
         $table = $mark->getComponent()->getGradeable()->isTeamAssignment() ? 'gradeable_teams' : 'users';
         $grade_type = $gradeable->isGradeByRegistration() ? 'registration' : 'rotating';

         $this->course_db->query("
             SELECT u.{$type}_id
             FROM {$table} u
                 JOIN (
                     SELECT gr.sections_{$grade_type}_id
                     FROM grading_{$grade_type} AS gr
                     WHERE gr.user_id = ?
                 ) AS gr
                 ON gr.sections_{$grade_type}_id=u.{$grade_type}_section
                 JOIN (
                     SELECT gd.gd_{$type}_id, gcmd.gcm_id
                     FROM gradeable_component_mark_data AS gcmd
                         JOIN gradeable_data gd ON gd.gd_id=gcmd.gd_id
                 ) as gcmd
                 ON gcmd.gd_{$type}_id=u.{$type}_id
             WHERE gcmd.gcm_id = ?", $params);

         // Map the results into a non-associative array of team/user ids
         return array_map(function ($row) use ($row_type) {
             return $row[$row_type];
         }, $this->course_db->rows());
    }

    public function getAllSubmittersWhoGotMark($mark) {
        // Switch the column based on gradeable team-ness
        $type = $mark->getComponent()->getGradeable()->isTeamAssignment() ? 'team' : 'user';
        $row_type = "gd_" . $type . "_id";
        $this->course_db->query("
            SELECT gd.gd_{$type}_id
            FROM gradeable_component_mark_data gcmd
              JOIN gradeable_data gd ON gd.gd_id=gcmd.gd_id
            WHERE gcm_id = ?", [$mark->getId()]);

        // Map the results into a non-associative array of team/user ids
        return array_map(function ($row) use ($row_type) {
            return $row[$row_type];
        }, $this->course_db->rows());
    }

    public function insertGradeableComponentMarkData($gd_id, $gc_id, $gcd_grader_id, GradeableComponentMark $mark) {
        $params = array($gc_id, $gd_id, $gcd_grader_id, $mark->getId());
        $this->course_db->query("
INSERT INTO gradeable_component_mark_data (gc_id, gd_id, gcd_grader_id, gcm_id)
VALUES (?, ?, ?, ?)", $params);
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
     * gets ids of all electronic gradeables excluding assignments that will be bulk
     * uploaded by TA or instructor.
     *
     * @return array
     */
    public function getAllElectronicGradeablesIds() {
        $this->course_db->query("
          SELECT g_id, g_title
          FROM gradeable INNER JOIN electronic_gradeable USING (g_id)
          WHERE eg_scanned_exam=FALSE and eg_has_due_date=TRUE
          ORDER BY eg_submission_due_date ASC
        ");
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
        throw new NotImplementedException();
    }

    /**
     * Return Team object for team which the given user belongs to on the given gradeable
     * @param string $g_id
     * @param string $user_id
     * @return \app\models\Team
     */
    public function getTeamByGradeableAndUser($g_id, $user_id) {
        throw new NotImplementedException();
    }

    /**
     * Return an array of Team objects for all teams on given gradeable
     * @param string $g_id
     * @return \app\models\Team[]
     */
    public function getTeamsByGradeableId($g_id) {
        throw new NotImplementedException();
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
            $sections_query = "{$section_key} IN " . $this->createParamaterList(count($sections)) . " AND";
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
            $placeholders = $this->createParamaterList(count($sections_keys));
            $where = "WHERE {$section_key} IN {$placeholders}";
            $params = array_merge($params, $sections_keys);
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
            $sections_query= "{$section_key} IN " . $this->createParamaterList(count($sections)) . " AND";
            $params = array_merge($sections, $params);
        }
        $orderBy="";
 		if($section_key == "registration_section") {
 			$orderBy = "SUBSTRING(registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(registration_section, '[0-9]+')::INT, -1), SUBSTRING(registration_section, '[^0-9]*$')";
 		}
 		else {
 			$orderBy = $section_key;
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
ORDER BY {$orderBy}", $params);
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
            $sections_query= "{$section_key} IN " . $this->createParamaterList(count($sections)) . " AND";
            $params = array_merge($sections, $params);
        }
        $orderBy="";
 		if($section_key == "registration_section") {
 			$orderBy = "SUBSTRING(registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(registration_section, '[0-9]+')::INT, -1), SUBSTRING(registration_section, '[^0-9]*$')";
 		}
 		else {
 			$orderBy = $section_key;
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
ORDER BY {$orderBy}", $params);
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
            $where = "WHERE {$section_key} IN " . $this->createParamaterList(count($sections));
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
	 * Retrieves all unarchived courses (and details) that are accessible by $user_id
	 *
	 * (u.user_id=? AND c.status=1) checks if a course is active
	 * An active course may be accessed by all users
	 *
	 * @param string $user_id
	 * @param string $submitty_path
	 * @return array - unarchived courses (and their details) accessible by $user_id
	 */
    public function getUnarchivedCoursesById($user_id) {
        $this->submitty_db->query("
SELECT u.semester, u.course
FROM courses_users u
INNER JOIN courses c ON u.course=c.course AND u.semester=c.semester
WHERE u.user_id=? AND c.status=1
ORDER BY u.user_group ASC,
         CASE WHEN SUBSTRING(u.semester, 2, 2) ~ '\\d+' THEN SUBSTRING(u.semester, 2, 2)::INT
              ELSE 0
         END DESC,
         CASE WHEN SUBSTRING(u.semester, 1, 1) = 's' THEN 2
              WHEN SUBSTRING(u.semester, 1, 1) = 'u' THEN 3
              WHEN SUBSTRING(u.semester, 1, 1) = 'f' THEN 4
              ELSE 1
         END DESC,
         u.course ASC", array($user_id));
        $return = array();
        foreach ($this->submitty_db->rows() as $row) {
            $course = new Course($this->core, $row);
            $course->loadDisplayName();
            $return[] = $course;
        }
        return $return;
    }

    /**
     * Retrieves all archived courses (and details) that are accessible by $user_id
     *
     * (u.user_id=? AND u.user_group=1) checks if $user_id is an instructor
     * Instructors may access all of their courses
     * Inactive courses may only be accessed by the instructor
     *
     * @param string $user_id
     * @param string $submitty_path
     * @return array - archived courses (and their details) accessible by $user_id
     */
    public function getArchivedCoursesById($user_id) {
        $this->submitty_db->query("
SELECT u.semester, u.course
FROM courses_users u
INNER JOIN courses c ON u.course=c.course AND u.semester=c.semester
WHERE u.user_id=? AND c.status=2 AND u.user_group=1
ORDER BY u.user_group ASC,
         CASE WHEN SUBSTRING(u.semester, 2, 2) ~ '\\d+' THEN SUBSTRING(u.semester, 2, 2)::INT
              ELSE 0
         END DESC,
         CASE WHEN SUBSTRING(u.semester, 1, 1) = 's' THEN 2
              WHEN SUBSTRING(u.semester, 1, 1) = 'u' THEN 3
              WHEN SUBSTRING(u.semester, 1, 1) = 'f' THEN 4
              ELSE 1
         END DESC,
         u.course ASC", array($user_id));
        $return = array();
        foreach ($this->submitty_db->rows() as $row) {
            $course = new Course($this->core, $row);
            $course->loadDisplayName();
            $return[] = $course;
        }
        return $return;
    }

    public function getCourseStatus($semester, $course) {
        $this->submitty_db->query("SELECT status FROM courses WHERE semester=? AND course=?", array($semester, $course));
        return $this->submitty_db->rows()[0]['status'];
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
        $grader_list = $this->createParamaterList(count($params));
        $params[] = $gradeable_id;
        $this->course_db->query("SELECT COUNT(*) as cnt
FROM gradeable_component_data as gcd
WHERE gcd.gcd_grader_id IN {$grader_list}
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
            $where = "WHERE registration_section IN " . $this->createParamaterList(count($sections));
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
        ORDER BY SUBSTRING(u.registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(u.registration_section, '[0-9]+')::INT, -1), SUBSTRING(u.registration_section, '[^0-9]*$')", $params);

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
            $where = "WHERE rotating_section IN " . $this->createParamaterList(count($sections));
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

    public function existsPost($thread_id, $post_id){
        $this->course_db->query("SELECT 1 FROM posts where thread_id = ? and id = ? and deleted = false", array($thread_id, $post_id));
        $result = $this->course_db->rows();
        return count($result) > 0;
    }

    public function existsAnnouncements($show_deleted = false){
        $query_delete = $show_deleted?"true":"deleted = false";
        $this->course_db->query("SELECT MAX(id) FROM threads where {$query_delete} AND  merged_thread_id = -1 AND pinned = true");
        $result = $this->course_db->rows();
        return empty($result[0]["max"]) ? -1 : $result[0]["max"];
    }

    public function viewedThread($user, $thread_id){
      $this->course_db->query("SELECT * FROM viewed_responses v WHERE thread_id = ? AND user_id = ? AND NOT EXISTS(SELECT thread_id FROM (posts LEFT JOIN forum_posts_history ON posts.id = forum_posts_history.post_id) AS jp WHERE jp.thread_id = ? AND (jp.timestamp > v.timestamp OR (jp.edit_timestamp IS NOT NULL AND jp.edit_timestamp > v.timestamp)))" , array($thread_id, $user, $thread_id));
      return count($this->course_db->rows()) > 0;
    }

    public function getDisplayUserInfoFromUserId($user_id){
      $this->course_db->query("SELECT user_firstname, user_preferred_firstname, user_lastname, user_preferred_lastname, user_email FROM users WHERE user_id = ?", array($user_id));
      $name_rows = $this->course_db->rows()[0];
      $ar = array();
      $ar["first_name"] = (empty($name_rows["user_preferred_firstname"])) ? $name_rows["user_firstname"]      : $name_rows["user_preferred_firstname"];
      $ar["last_name"]  = (empty($name_rows["user_preferred_lastname"]))  ? " " . $name_rows["user_lastname"] : " " . $name_rows["user_preferred_lastname"];
      $ar["user_email"] = $name_rows["user_email"];
      return $ar;
    }

    public function filterCategoryDesc($category_desc) {
        return str_replace("|", " ", $category_desc);
    }

    public function addNewCategory($category) {
        //Can't get "RETURNING category_id" syntax to work
        $this->course_db->query("INSERT INTO categories_list (category_desc) VALUES (?) RETURNING category_id", array($this->filterCategoryDesc($category)));
        $this->course_db->query("SELECT MAX(category_id) as category_id from categories_list");
        return $this->course_db->rows()[0];
    }

    public function deleteCategory($category_id) {
        // TODO, check if no thread is using current category
        $this->course_db->query("SELECT 1 FROM thread_categories WHERE category_id = ?", array($category_id));
        if(count($this->course_db->rows()) == 0) {
            $this->course_db->query("DELETE FROM categories_list WHERE category_id = ?", array($category_id));
            return true;
        } else {
            return false;
        }
    }

    public function editCategory($category_id, $category_desc, $category_color) {
        $this->course_db->beginTransaction();
        if(!is_null($category_desc)) {
            $this->course_db->query("UPDATE categories_list SET category_desc = ? WHERE category_id = ?", array($category_desc, $category_id));
        }
        if(!is_null($category_color)) {
            $this->course_db->query("UPDATE categories_list SET color = ? WHERE category_id = ?", array($category_color, $category_id));
        }
        $this->course_db->commit();
    }

    public function reorderCategories($categories_in_order) {
        $this->course_db->beginTransaction();
        foreach ($categories_in_order as $rank => $id) {
            $this->course_db->query("UPDATE categories_list SET rank = ? WHERE category_id = ?", array($rank, $id));
        }
        $this->course_db->commit();
    }

    public function getCategories(){
        $this->course_db->query("SELECT * from categories_list ORDER BY rank ASC NULLS LAST, category_id");
        return $this->course_db->rows();
    }

    public function getPostsForThread($current_user, $thread_id, $show_deleted = false, $option = "tree", $filterOnUser = NULL){
      $query_delete = $show_deleted?"true":"deleted = false";
      $query_filter_on_user = '';
      $param_list = array();
      if(!empty($filterOnUser)) {
        $query_filter_on_user = ' and author_user_id = ? ';
        $param_list[] = $filterOnUser;
      }
      if($thread_id == -1) {
        $this->course_db->query("SELECT MAX(id) as max from threads WHERE deleted = false and merged_thread_id = -1 GROUP BY pinned ORDER BY pinned DESC");
        $rows = $this->course_db->rows();
        if(!empty($rows)) {
            $thread_id = $rows[0]["max"];
        } else {
            // No thread found, hence no posts found
            return array();
        }
      }
      $param_list[] = $thread_id;
      $history_query = "LEFT JOIN forum_posts_history fph ON (fph.post_id is NULL OR (fph.post_id = posts.id and NOT EXISTS (SELECT 1 from forum_posts_history WHERE post_id = fph.post_id and edit_timestamp > fph.edit_timestamp )))";
      if($option == 'alpha'){
        $this->course_db->query("SELECT posts.*, fph.edit_timestamp, users.user_lastname FROM posts INNER JOIN users ON posts.author_user_id=users.user_id {$history_query} WHERE thread_id=? AND {$query_delete} ORDER BY user_lastname, posts.timestamp;", array($thread_id));
      } else {
        $this->course_db->query("SELECT posts.*, fph.edit_timestamp FROM posts {$history_query} WHERE thread_id=? AND {$query_delete} {$query_filter_on_user} ORDER BY timestamp ASC", array_reverse($param_list));
      }
      $result_rows = $this->course_db->rows();
      return $result_rows;
    }

    public function getRootPostOfNonMergedThread($thread_id, &$title, &$message) {
        $this->course_db->query("SELECT title FROM threads WHERE id = ? and merged_thread_id = -1 and merged_post_id = -1", array($thread_id));
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

    public function mergeThread($parent_thread_id, $child_thread_id, &$message, &$child_root_post){
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
            $this->course_db->query("UPDATE threads SET merged_thread_id = ?, merged_post_id = ? WHERE id = ?", array($parent_thread_id, $child_root_post, $child_thread_id));
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

        $question_marks = $this->createParamaterList(count($params));
        $this->course_db->query("SELECT user_id, anon_id FROM users WHERE user_id IN {$question_marks}", $params);
        $return = array();
        foreach($this->course_db->rows() as $id_map) {
            $return[$id_map['user_id']] = $id_map['anon_id'];
        }
        return $return;
    }

    public function getUserFromAnon($anon_id) {
        $params = is_array($anon_id) ? $anon_id : array($anon_id);

        $question_marks = $this->createParamaterList(count($params));
        $this->course_db->query("SELECT anon_id, user_id FROM users WHERE anon_id IN {$question_marks}", $params);
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
     * Gets the team ids from the provided anonymous ids
     * TODO: This function is in place for when teams get anonymous ids
     * @param array $anon_ids
     * @return array
     */
    public function getTeamIdsFromAnonIds(array $anon_ids) {
        /*
        $placeholders = $this->createParamaterList(count($anon_ids));
        $this->course_db->query("SELECT anon_id, team_id FROM gradeable_teams WHERE anon_id IN {$placeholders}", $anon_ids);

        $team_ids = [];
        foreach ($this->course_db->row() as $row) {
            $team_ids[$row['anon_id']] = $row['team_id'];
        }
        return $team_ids;
        */
        // TODO: team ids are the same as their anonymous ids for now
        return array_combine($anon_ids, $anon_ids);
    }

    public function getTeamIdFromAnonId(string $anon_id) {
        return $this->getTeamIdsFromAnonIds([$anon_id])[$anon_id] ?? null;
    }

    public function getSubmitterIdFromAnonId(string $anon_id) {
        return $this->getUserFromAnon($anon_id)[$anon_id] ??
            $this->getTeamIdFromAnonId($anon_id);
    }

    /**
     * Generate notifcation rows
     *
     * @param Notification $notification
     */
    public function pushNotification($notification){
        $params = array();
        $params[] = $notification->getComponent();
        $params[] = $notification->getNotifyMetadata();
        $params[] = $notification->getNotifyContent();
        $params[] = $notification->getNotifySource();

        $type = $notification->getType();
        $target_users_query = "SELECT user_id FROM users";
        $ignore_self_query = "";
        $not_send_users = array();
        $announcement = $type === 'new_announcement' || $type === 'updated_announcement';

        if(!empty($notification->getNotifyTarget())) {
        	//Notify specific user
        	$not_send_users[] = $notification->getNotifyTarget();
        	if($params[3] !== $not_send_users[0]) {
        		$this->course_db->query("INSERT INTO notifications(component, metadata, content, created_at, from_user_id, to_user_id)
                    VALUES (?, ?, ?, current_timestamp, ?, ?)",
                    array_merge($params, $not_send_users));
        	}
        }

        if($notification->getNotifyNotToSource()){
            $not_send_users[] = $notification->getNotifySource();
        }

        $restrict = count($not_send_users);
        if($restrict > 0) {
        	$ignore_self_query = "WHERE user_id NOT IN (" . implode(',', array_fill(0, $restrict, '?')) . ')';
        }

        $column = '';
        if($type === 'reply') {
        	$post_thread_id = json_decode($params[1], true)[0]['thread_id'];
            $params[] = $post_thread_id;
            $target_users_query = "SELECT n.user_id from notification_settings n, posts p where p.thread_id = ? and p.author_user_id = n.user_id and n.reply_in_post_thread = 'true' ";
            $target_users_query .= "UNION SELECT user_id from notification_settings where all_new_posts = 'true'";
        } else if(!$announcement) {
        	switch ($type) {
	            case 'new_thread':
	                $column = 'all_new_threads';
	                break;
	            case 'merge_thread':
	                $column = 'merge_threads';
	                break;
	            case 'edited':
	            case 'deleted':
	            case 'undeleted':
	                $column = 'all_modifications_forum';
	                break;
    		}
    		$target_users_query = "SELECT user_id FROM notification_settings where {$column} = 'true'";
        }

        //Notify users based on settings
        $this->course_db->query("INSERT INTO notifications(component, metadata, content, created_at, from_user_id, to_user_id)
                    SELECT ?, ?, ?, current_timestamp, ?, user_id as to_user_id FROM ({$target_users_query}) as u {$ignore_self_query}",
                    array_merge($params, $not_send_users));
    }

    /**
     * Returns notifications for a user
     *
     * @param string $user_id
     * @param bool $show_all
     * @return array(Notification)
     */
    public function getUserNotifications($user_id, $show_all){
        if($show_all){
            $seen_status_query = "true";
        } else {
            $seen_status_query = "seen_at is NULL";
        }
        $this->course_db->query("SELECT id, component, metadata, content,
                (case when seen_at is NULL then false else true end) as seen,
                (extract(epoch from current_timestamp) - extract(epoch from created_at)) as elapsed_time, created_at
                FROM notifications WHERE to_user_id = ? and {$seen_status_query} ORDER BY created_at DESC", array($user_id));
        $rows = $this->course_db->rows();
        $results = array();
        foreach ($rows as $row) {
            $results[] = new Notification($this->core, array(
                    'view_only' => true,
                    'id' => $row['id'],
                    'component' => $row['component'],
                    'metadata' => $row['metadata'],
                    'content' => $row['content'],
                    'seen' => $row['seen'],
                    'elapsed_time' => $row['elapsed_time'],
                    'created_at' => $row['created_at']
                ));
        }
        return $results;
    }

    public function getNotificationInfoById($user_id, $notification_id){
        $this->course_db->query("SELECT metadata FROM notifications WHERE to_user_id = ? and id = ?", array($user_id, $notification_id));
        return $this->course_db->row();
    }

    public function getUnreadNotificationsCount($user_id, $component){
        $parameters = array($user_id);
        if(is_null($component)){
            $component_query = "true";
        } else {
            $component_query = "component = ?";
            $parameters[] = $component;
        }
        $this->course_db->query("SELECT count(*) FROM notifications WHERE to_user_id = ? and seen_at is NULL and {$component_query}", $parameters);
        return $this->course_db->row()['count'];
    }

    /**
     * Marks $user_id notifications as seen
     *
     * @param sting $user_id
     * @param int $notification_id  if $notification_id != -1 then marks corresponding as seen else mark all notifications as seen
     */
    public function markNotificationAsSeen($user_id, $notification_id, $thread_id = -1){
        $parameters = array();
        $parameters[] = $user_id;
        if($thread_id != -1) {
        	$id_query = "metadata::json->0->>'thread_id' = ?";
        	$parameters[] = $thread_id;
        } else if($notification_id == -1) {
            $id_query = "true";
        } else {
            $id_query = "id = ?";
            $parameters[] = $notification_id;
        }
        $this->course_db->query("UPDATE notifications SET seen_at = current_timestamp
                WHERE to_user_id = ? and seen_at is NULL and {$id_query}", $parameters);
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

    public function getRegradeRequestStatus($user_id, $gradeable_id){
        $row = $this->course_db->query("SELECT * FROM regrade_requests WHERE user_id = ? AND g_id = ? ", array($user_id, $gradeable_id));
        $result = ($this->course_db->row()) ? $row['status'] : 0;
        return $result;
    }

    public function insertNewRegradeRequest(GradedGradeable $graded_gradeable, User $sender, string $initial_message) {
        $params = array($graded_gradeable->getGradeableId(), $graded_gradeable->getSubmitter()->getId(), RegradeRequest::STATUS_ACTIVE);
        $submitter_col = $graded_gradeable->getSubmitter()->isTeam() ? 'team_id' : 'user_id';
        try {
            $this->course_db->query("INSERT INTO regrade_requests(g_id, timestamp, $submitter_col, status) VALUES (?, current_timestamp, ?, ?)", $params);
            $regrade_id = $this->course_db->getLastInsertId();
            $this->insertNewRegradePost($regrade_id, $sender->getId(), $initial_message);
        } catch (DatabaseException $dbException) {
            if ($this->course_db->inTransaction()) $this->course_db->rollback();
            throw $dbException;
        }
    }
    public function getNumberRegradeRequests($gradeable_id) {
        $this->course_db->query("SELECT COUNT(*) AS cnt FROM regrade_requests WHERE g_id = ? AND status = -1", array($gradeable_id));
        return ($this->course_db->row()['cnt']);
    }
    public function getRegradeDiscussion(RegradeRequest $regrade_request) {
        $this->course_db->query("SELECT * FROM regrade_discussion WHERE regrade_id=? AND deleted=false ORDER BY timestamp ASC", array($regrade_request->getId()));
        $result = array();
        foreach ($this->course_db->rows() as $row => $val) {
            $result[] = $val;
        }
        return $result;
    }

    public function insertNewRegradePost($regrade_id, $user_id, $content){
        $params = array($regrade_id, $user_id, $content);
        $this->course_db->query("INSERT INTO regrade_discussion(regrade_id, timestamp, user_id, content) VALUES (?, current_timestamp, ?, ?)", $params);
    }

    public function saveRegradeRequest(RegradeRequest $regrade_request) {
        $this->course_db->query("UPDATE regrade_requests SET timestamp = current_timestamp, status = ? WHERE id = ?", array($regrade_request->getStatus(), $regrade_request->getId()));
    }

    public function deleteRegradeRequest(RegradeRequest $regrade_request) {
        $regrade_id = $regrade_request->getId();
        $this->course_db->query("DELETE FROM regrade_discussion WHERE regrade_id = ?", $regrade_id);
        $this->course_db->query("DELETE FROM regrade_requests WHERE id = ?", $regrade_id);

    }
    public function deleteGradeable($g_id) {
        $this->course_db->query("DELETE FROM gradeable WHERE g_id=?", array($g_id));
    }

    /**
     * Gets a single Gradeable instance by id
     * @param string $id The gradeable's id
     * @return \app\models\gradeable\Gradeable
     * @throws \InvalidArgumentException If any Gradeable or Component fails to construct
     * @throws ValidationException If any Gradeable or Component fails to construct
     */
    public function getGradeableConfig($id) {
        foreach ($this->getGradeableConfigs([$id]) as $gradeable) {
            return $gradeable;
        }
        throw new \InvalidArgumentException('Gradeable does not exist!');
    }

    /**
     * Gets all Gradeable instances for the given ids (or all if id is null)
     * @param string[]|null $ids ids of the gradeables to retrieve
     * @param string[]|string|null $sort_keys An ordered list of keys to sort by (i.e. `id` or `grade_start_date DESC`)
     * @return \Iterator Iterates across array of Gradeables retrieved
     * @throws \InvalidArgumentException If any Gradeable or Component fails to construct
     * @throws ValidationException If any Gradeable or Component fails to construct
     */
    public function getGradeableConfigs($ids, $sort_keys = ['id']) {
        throw new NotImplementedException();
    }

    /**
     * Gets whether a gradeable has any manual grades yet
     * @param string $g_id id of the gradeable
     * @return bool True if the gradeable has manual grades
     */
    public function getGradeableHasGrades($g_id) {
        $this->course_db->query('SELECT EXISTS (SELECT 1 FROM gradeable_data WHERE g_id=?)', array($g_id));
        return $this->course_db->row()['exists'];
    }

    /**
     * Returns array of User objects for users with given User IDs
     * @param string[] $user_ids
     * @return User[] The user objects, indexed by user id
     */
    public function getUsersById(array $user_ids) {
        throw new NotImplementedException();
    }

    /**
     * Return array of Team objects for teams with given Team IDs
     * @param string[] $team_ids
     * @return Team[] The team objects, indexed by team id
     */
    public function getTeamsById(array $team_ids) {
        throw new NotImplementedException();
    }

    /**
     * Gets a user or team submitter by id
     * @param string $id User or team id
     * @return Submitter|null
     */
    public function getSubmitterById(string $id) {
        $user = $this->core->getQueries()->getUserById($id);
        if ($user !== null) {
            return new Submitter($this->core, $user);
        }
        $team = $this->core->getQueries()->getTeamById($id);
        if ($team !== null) {
            return new Submitter($this->core, $team);
        }
        //TODO: Do we have other types of submitters?
        return null;
    }

    /**
     * Gets user or team submitters by id
     * @param string[] $ids User or team ids
     * @return Submitter[]
     */
    public function getSubmittersById(array $ids) {
        //Get Submitter for each id in ids
        return array_map(function($id) {
            return $this->getSubmitterById($id);
        }, $ids);
    }

    /**
     * Gets a single GradedGradeable associated with the provided gradeable and
     *  user/team.  Note: The user's team for this gradeable will be retrived if provided
     * @param \app\models\gradeable\Gradeable $gradeable
     * @param string|null $user The id of the user to get data for
     * @param string|null $team The id of the team to get data for
     * @return GradedGradeable|null The GradedGradeable or null if none found
     * @throws \InvalidArgumentException If any GradedGradeable or GradedComponent fails to construct
     */
    public function getGradedGradeable(\app\models\gradeable\Gradeable $gradeable, $user, $team = null) {
        foreach ($this->getGradedGradeables([$gradeable], $user, $team) as $gg) {
            return $gg;
        }
        return null;
    }

    /**
     * Gets a single GradedGradeable associated with the provided gradeable and
     *  submitter.  Note: The user's team for this gradeable will be retrived if provided
     * @param \app\models\gradeable\Gradeable $gradeable
     * @param Submitter|null $submitter The submitter to get data for
     * @return GradedGradeable|null The GradedGradeable or null if none found
     * @throws \InvalidArgumentException If any GradedGradeable or GradedComponent fails to construct
     */
    public function getGradedGradeableForSubmitter(\app\models\gradeable\Gradeable $gradeable, Submitter $submitter) {
        //Either user or team is set, the other should be null
        $user_id = $submitter->getUser() ? $submitter->getUser()->getId() : null;
        $team_id = $submitter->getTeam() ? $submitter->getTeam()->getId() : null;
        return $this->getGradedGradeable($gradeable, $user_id, $team_id);
    }

    /**
     * Gets all GradedGradeable's associated with each Gradeable.  If
     *  both $users and $teams are null, then everyone will be retrieved.
     *  Note: The users' teams will be included in the search
     * @param \app\models\gradeable\Gradeable[] The gradeable(s) to retrieve data for
     * @param string[]|string|null $users The id(s) of the user(s) to get data for
     * @param string[]|string|null $teams The id(s) of the team(s) to get data for
     * @param string[]|string|null $sort_keys An ordered list of keys to sort by (i.e. `user_id` or `g_id DESC`)
     * @return \Iterator Iterator to access each GradeableData
     * @throws \InvalidArgumentException If any GradedGradeable or GradedComponent fails to construct
     */
    public function getGradedGradeables(array $gradeables, $users = null, $teams = null, $sort_keys = null) {
        throw new NotImplementedException();
    }

    /**
     * Creates a new Mark in the database
     * @param Mark $mark The mark to insert
     * @param int $component_id The Id of the component this mark belongs to
     */
    private function createMark(Mark $mark, $component_id) {
        $params = [
            $component_id,
            $mark->getPoints(),
            $mark->getTitle(),
            $mark->getOrder(),
            $this->course_db->convertBoolean($mark->isPublish())
        ];
        $this->course_db->query("
            INSERT INTO gradeable_component_mark (
              gc_id,
              gcm_points,
              gcm_note,
              gcm_order,
              gcm_publish)
            VALUES (?, ?, ?, ?, ?)", $params);

        // Setup the mark with its new id
        $mark->setIdFromDatabase($this->course_db->getLastInsertId());
    }

    /**
     * Updates a mark in the database
     * @param Mark $mark The mark to update
     */
    private function updateMark(Mark $mark) {
        $params = [
            $mark->getComponent()->getId(),
            $mark->getPoints(),
            $mark->getTitle(),
            $mark->getOrder(),
            $this->course_db->convertBoolean($mark->isPublish()),
            $mark->getId()
        ];
        $this->course_db->query("
            UPDATE gradeable_component_mark SET
              gc_id=?,
              gcm_points=?,
              gcm_note=?,
              gcm_order=?,
              gcm_publish=?
            WHERE gcm_id=?", $params);
    }

    /**
     * Deletes an array of marks from the database and any
     *  data associated with them
     * @param Mark[] $marks An array of marks to delete
     */
    private function deleteMarks(array $marks) {
        if (count($marks) === 0) {
            return;
        }
        // We only need the ids
        $mark_ids = array_values(array_map(function (Mark $mark) {
            return $mark->getId();
        }, $marks));
        $place_holders = $this->createParamaterList(count($marks));

        $this->course_db->query("DELETE FROM gradeable_component_mark_data WHERE gcm_id IN {$place_holders}", $mark_ids);
        $this->course_db->query("DELETE FROM gradeable_component_mark WHERE gcm_id IN {$place_holders}", $mark_ids);
    }

    /**
     * Creates a new Component in the database
     * @param Component $component The component to insert
     */
    private function createComponent(Component $component) {
        $params = [
            $component->getGradeable()->getId(),
            $component->getTitle(),
            $component->getTaComment(),
            $component->getStudentComment(),
            $component->getLowerClamp(),
            $component->getDefault(),
            $component->getMaxValue(),
            $component->getUpperClamp(),
            $this->course_db->convertBoolean($component->isText()),
            $component->getOrder(),
            $this->course_db->convertBoolean($component->isPeer()),
            $component->getPage()
        ];
        $this->course_db->query("
            INSERT INTO gradeable_component(
              g_id,
              gc_title,
              gc_ta_comment,
              gc_student_comment,
              gc_lower_clamp,
              gc_default,
              gc_max_value,
              gc_upper_clamp,
              gc_is_text,
              gc_order,
              gc_is_peer,
              gc_page)
            VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $params);

        // Setup the component with its new id
        $component->setIdFromDatabase($this->course_db->getLastInsertId());
    }

    /**
     * Iterates through each mark in a component and updates/creates/deletes
     *  it in the database as necessary.  Note: the component must
     *  already exist in the database to add new marks
     * @param Component $component
     */
    private function updateComponentMarks(Component $component) {

        // sort marks by order
        $marks = $component->getMarks();
        usort($marks, function (Mark $a, Mark $b) {
            return $a->getOrder() - $b->getOrder();
        });

        $order = 0;
        foreach ($marks as $mark) {
            // rectify mark order
            if ($mark->getOrder() !== $order) {
                $mark->setOrder($order);
            }
            ++$order;

            // New mark, so add it
            if ($mark->getId() < 1) {
                $this->createMark($mark, $component->getId());
            }
            if ($mark->isModified()) {
                $this->updateMark($mark);
            }
        }

        // Delete any marks not being updated
        $this->deleteMarks($component->getDeletedMarks());
    }

    /**
     * Updates a Component in the database
     * @param Component $component The component to update
     */
    private function updateComponent(Component $component) {
        if ($component->isModified()) {
            $params = [
                $component->getTitle(),
                $component->getTaComment(),
                $component->getStudentComment(),
                $component->getLowerClamp(),
                $component->getDefault(),
                $component->getMaxValue(),
                $component->getUpperClamp(),
                $this->course_db->convertBoolean($component->isText()),
                $component->getOrder(),
                $this->course_db->convertBoolean($component->isPeer()),
                $component->getPage(),
                $component->getId()
            ];
            $this->course_db->query("
                UPDATE gradeable_component SET
                  gc_title=?,
                  gc_ta_comment=?,
                  gc_student_comment=?,
                  gc_lower_clamp=?,
                  gc_default=?,
                  gc_max_value=?,
                  gc_upper_clamp=?,
                  gc_is_text=?,
                  gc_order=?,
                  gc_is_peer=?,
                  gc_page=?
                WHERE gc_id=?", $params);
        }
    }

    /**
     * Deletes an array of components from the database and any
     *  data associated with them
     * @param array $components
     */
    private function deleteComponents(array $components) {
        if (count($components) === 0) {
            return;
        }

        // We only want the ids in our array
        $component_ids = array_values(array_map(function (Component $component) {
            return $component->getId();
        }, $components));
        $place_holders = $this->createParamaterList(count($components));

        $this->course_db->query("DELETE FROM gradeable_component_data WHERE gc_id IN {$place_holders}", $component_ids);
        $this->course_db->query("DELETE FROM gradeable_component WHERE gc_id IN {$place_holders}", $component_ids);
    }

    /**
     * Creates / updates a component and its marks in the database
     * @param Component $component
     */
    public function saveComponent(Component $component) {
        // New component, so add it
        if ($component->getId() < 1) {
            $this->createComponent($component);
        } else {
            $this->updateComponent($component);
        }

        // Then, update/create/delete its marks
        $this->updateComponentMarks($component);
    }

    /**
     * Creates a new gradeable in the database
     * @param \app\models\gradeable\Gradeable $gradeable The gradeable to insert
     */
    public function createGradeable(\app\models\gradeable\Gradeable $gradeable) {
        $params = [
            $gradeable->getId(),
            $gradeable->getTitle(),
            $gradeable->getInstructionsUrl(),
            $gradeable->getTaInstructions(),
            $gradeable->getType(),
            $this->course_db->convertBoolean($gradeable->isGradeByRegistration()),
            DateUtils::dateTimeToString($gradeable->getTaViewStartDate()),
            DateUtils::dateTimeToString($gradeable->getGradeStartDate()),
            DateUtils::dateTimeToString($gradeable->getGradeDueDate()),
            DateUtils::dateTimeToString($gradeable->getGradeReleasedDate()),
            $gradeable->getGradeLockedDate() !== null ?
                DateUtils::dateTimeToString($gradeable->getGradeLockedDate()) : null,
            $gradeable->getMinGradingGroup(),
            $gradeable->getSyllabusBucket()
        ];
        $this->course_db->query("
            INSERT INTO gradeable(
              g_id,
              g_title,
              g_instructions_url,
              g_overall_ta_instructions,
              g_gradeable_type,
              g_grade_by_registration,
              g_ta_view_start_date,
              g_grade_start_date,
              g_grade_due_date,
              g_grade_released_date,
              g_grade_locked_date,
              g_min_grading_group,
              g_syllabus_bucket)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $params);
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            $params = [
                $gradeable->getId(),
                DateUtils::dateTimeToString($gradeable->getSubmissionOpenDate()),
                DateUtils::dateTimeToString($gradeable->getSubmissionDueDate()),
                $this->course_db->convertBoolean($gradeable->isVcs()),
                $gradeable->getVcsSubdirectory(),
                $this->course_db->convertBoolean($gradeable->isTeamAssignment()),
                $gradeable->getTeamSizeMax(),
                DateUtils::dateTimeToString($gradeable->getTeamLockDate()),
                $this->course_db->convertBoolean($gradeable->isTaGrading()),
                $this->course_db->convertBoolean($gradeable->isScannedExam()),
                $this->course_db->convertBoolean($gradeable->isStudentView()),
                $this->course_db->convertBoolean($gradeable->isStudentViewAfterGrades()),
                $this->course_db->convertBoolean($gradeable->isStudentSubmit()),
                $this->course_db->convertBoolean($gradeable->hasDueDate()),
                $gradeable->getAutogradingConfigPath(),
                $gradeable->getLateDays(),
                $this->course_db->convertBoolean($gradeable->isLateSubmissionAllowed()),
                $gradeable->getPrecision(),
                $this->course_db->convertBoolean($gradeable->isPeerGrading()),
                $gradeable->getPeerGradeSet(),
                DateUtils::dateTimeToString($gradeable->getRegradeRequestDate()),
                $this->course_db->convertBoolean($gradeable->isRegradeAllowed()),
                $gradeable->getDiscussionThreadId(),
                $this->course_db->convertBoolean($gradeable->isDiscussionBased())
            ];
            $this->course_db->query("
                INSERT INTO electronic_gradeable(
                  g_id,
                  eg_submission_open_date,
                  eg_submission_due_date,
                  eg_is_repository,
                  eg_subdirectory,
                  eg_team_assignment,
                  eg_max_team_size,
                  eg_team_lock_date,
                  eg_use_ta_grading,
                  eg_scanned_exam,
                  eg_student_view,
                  eg_student_view_after_grades,
                  eg_student_submit,
                  eg_has_due_date,
                  eg_config_path,
                  eg_late_days,
                  eg_allow_late_submission,
                  eg_precision,
                  eg_peer_grading,
                  eg_peer_grade_set,
                  eg_regrade_request_date,
                  eg_regrade_allowed,
                  eg_thread_ids,
                  eg_has_discussion
                  )
                VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $params);
        }

        // Make sure to create the rotating sections
        $this->setupRotatingSections($gradeable->getRotatingGraderSections(), $gradeable->getId());

        // Also make sure to create components
        $this->updateGradeableComponents($gradeable);
    }

    /**
     * Iterates through each component in a gradeable and updates/creates
     *  it in the database as necessary.  It also reloads the marks/components
     *  if any were added
     * @param \app\models\gradeable\Gradeable $gradeable
     */
    private function updateGradeableComponents(\app\models\gradeable\Gradeable $gradeable) {

        // sort components by order
        $components = $gradeable->getComponents();
        usort($components, function(Component $a, Component $b) {
            return $a->getOrder() - $b->getOrder();
        });

        // iterate through components and see if any need updating/creating
        $order = 0;
        foreach ($components as $component) {
            // Rectify component order
            if ($component->getOrder() !== $order) {
                $component->setOrder($order);
            }
            ++$order;

            // Save the component
            $this->saveComponent($component);
        }

        // Delete any components not being updated
        $this->deleteComponents($gradeable->getDeletedComponents());
    }

    /**
     * Updates the gradeable and its components/marks with new properties
     * @param \app\models\gradeable\Gradeable $gradeable The gradeable to update
     */
    public function updateGradeable(\app\models\gradeable\Gradeable $gradeable) {

        // If the gradeable has been modified, then update its properties
        if ($gradeable->isModified()) {
            $params = [
                $gradeable->getTitle(),
                $gradeable->getInstructionsUrl(),
                $gradeable->getTaInstructions(),
                $gradeable->getType(),
                $this->course_db->convertBoolean($gradeable->isGradeByRegistration()),
                DateUtils::dateTimeToString($gradeable->getTaViewStartDate()),
                DateUtils::dateTimeToString($gradeable->getGradeStartDate()),
                DateUtils::dateTimeToString($gradeable->getGradeDueDate()),
                DateUtils::dateTimeToString($gradeable->getGradeReleasedDate()),
                $gradeable->getGradeLockedDate() !== null ?
                    DateUtils::dateTimeToString($gradeable->getGradeLockedDate()) : null,
                $gradeable->getMinGradingGroup(),
                $gradeable->getSyllabusBucket(),
                $gradeable->getId()
            ];
            $this->course_db->query("
                UPDATE gradeable SET
                  g_title=?,
                  g_instructions_url=?,
                  g_overall_ta_instructions=?,
                  g_gradeable_type=?,
                  g_grade_by_registration=?,
                  g_ta_view_start_date=?,
                  g_grade_start_date=?,
                  g_grade_due_date=?,
                  g_grade_released_date=?,
                  g_grade_locked_date=?,
                  g_min_grading_group=?,
                  g_syllabus_bucket=?
                WHERE g_id=?", $params);
            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                $params = [
                    DateUtils::dateTimeToString($gradeable->getSubmissionOpenDate()),
                    DateUtils::dateTimeToString($gradeable->getSubmissionDueDate()),
                    $this->course_db->convertBoolean($gradeable->isVcs()),
                    $gradeable->getVcsSubdirectory(),
                    $this->course_db->convertBoolean($gradeable->isTeamAssignment()),
                    $gradeable->getTeamSizeMax(),
                    DateUtils::dateTimeToString($gradeable->getTeamLockDate()),
                    $this->course_db->convertBoolean($gradeable->isTaGrading()),
                    $this->course_db->convertBoolean($gradeable->isScannedExam()),
                    $this->course_db->convertBoolean($gradeable->isStudentView()),
                    $this->course_db->convertBoolean($gradeable->isStudentViewAfterGrades()),
                    $this->course_db->convertBoolean($gradeable->isStudentSubmit()),
                    $this->course_db->convertBoolean($gradeable->hasDueDate()),
                    $gradeable->getAutogradingConfigPath(),
                    $gradeable->getLateDays(),
                    $this->course_db->convertBoolean($gradeable->isLateSubmissionAllowed()),
                    $gradeable->getPrecision(),
                    $this->course_db->convertBoolean($gradeable->isPeerGrading()),
                    $gradeable->getPeerGradeSet(),
                    DateUtils::dateTimeToString($gradeable->getRegradeRequestDate()),
                    $this->course_db->convertBoolean($gradeable->isRegradeAllowed()),
                    $gradeable->getDiscussionThreadId(),
                    $this->course_db->convertBoolean($gradeable->isDiscussionBased()),
                    $gradeable->getId()
                ];
                $this->course_db->query("
                    UPDATE electronic_gradeable SET
                      eg_submission_open_date=?,
                      eg_submission_due_date=?,
                      eg_is_repository=?,
                      eg_subdirectory=?,
                      eg_team_assignment=?,
                      eg_max_team_size=?,
                      eg_team_lock_date=?,
                      eg_use_ta_grading=?,
                      eg_scanned_exam=?,
                      eg_student_view=?,
                      eg_student_view_after_grades=?,
                      eg_student_submit=?,
                      eg_has_due_date=?,
                      eg_config_path=?,
                      eg_late_days=?,
                      eg_allow_late_submission=?,
                      eg_precision=?,
                      eg_peer_grading=?,
                      eg_peer_grade_set=?,
                      eg_regrade_request_date=?,
                      eg_regrade_allowed=?,
                      eg_thread_ids=?,
                      eg_has_discussion=?
                    WHERE g_id=?", $params);
            }
        }

        // Save the rotating sections
        if ($gradeable->isRotatingGraderSectionsModified()) {
            $this->setupRotatingSections($gradeable->getRotatingGraderSections(), $gradeable->getId());
        }

        // Also make sure to update components
        $this->updateGradeableComponents($gradeable);
    }


    /**
     * Removes the provided mark ids from the marks assigned to a graded component
     * @param GradedComponent $graded_component
     * @param int[] $mark_ids
     */
    private function deleteGradedComponentMarks(GradedComponent $graded_component, $mark_ids) {
        if ($mark_ids === null || count($mark_ids) === 0) {
            return;
        }

        $param = array_merge([
            $graded_component->getTaGradedGradeable()->getId(),
            $graded_component->getComponentId(),
            $graded_component->getGraderId(),
        ], $mark_ids);
        $place_holders = $this->createParamaterList(count($mark_ids));
        $this->course_db->query("
            DELETE FROM gradeable_component_mark_data
            WHERE gd_id=? AND gc_id=? AND gcd_grader_id=? AND gcm_id IN {$place_holders}",
            $param);
    }

    /**
     * Adds the provided mark ids as marks assigned to a graded component
     * @param GradedComponent $graded_component
     * @param int[] $mark_ids
     */
    private function createGradedComponentMarks(GradedComponent $graded_component, $mark_ids) {
        if (count($mark_ids) === 0) {
            return;
        }

        $param = [
            $graded_component->getTaGradedGradeable()->getId(),
            $graded_component->getComponentId(),
            $graded_component->getGraderId(),
            -1  // This value gets set on each loop iteration
        ];
        $query = "
            INSERT INTO gradeable_component_mark_data(
              gd_id,
              gc_id,
              gcd_grader_id,
              gcm_id)
            VALUES (?, ?, ?, ?)";

        foreach ($mark_ids as $mark_id) {
            $param[3] = $mark_id;
            $this->course_db->query($query, $param);
        }
    }

    /**
     * Creates a new graded component in the database
     * @param GradedComponent $graded_component
     */
    private function createGradedComponent(GradedComponent $graded_component) {
        $param = [
            $graded_component->getComponentId(),
            $graded_component->getTaGradedGradeable()->getId(),
            $graded_component->getScore(),
            $graded_component->getComment(),
            $graded_component->getGraderId(),
            $graded_component->getGradedVersion(),
            DateUtils::dateTimeToString($graded_component->getGradeTime()),
            $graded_component->getVerifierId() !== '' ? $graded_component->getVerifierId() : null,
            !is_null($graded_component->getVerifyTime()) ? DateUtils::dateTimeToString($graded_component->getVerifyTime()) : null
        ];
        $query = "
            INSERT INTO gradeable_component_data(
              gc_id,
              gd_id,
              gcd_score,
              gcd_component_comment,
              gcd_grader_id,
              gcd_graded_version,
              gcd_grade_time,
              gcd_verifier_id,
              gcd_verify_time)
            VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->course_db->query($query, $param);
    }

    /**
     * Updates an existing graded component in the database
     * @param GradedComponent $graded_component
     */
    private function updateGradedComponent(GradedComponent $graded_component) {
        if ($graded_component->isModified()) {
            if(!$graded_component->getComponent()->isPeer()) {
                $params = [
                    $graded_component->getScore(),
                    $graded_component->getComment(),
                    $graded_component->getGradedVersion(),
                    DateUtils::dateTimeToString($graded_component->getGradeTime()),
                    $graded_component->getGraderId(),
                    $graded_component->getVerifierId() !== '' ? $graded_component->getVerifierId() : null,
                    !is_null($graded_component->getVerifyTime()) ? DateUtils::dateTimeToString($graded_component->getVerifyTime()) : null,
                    $graded_component->getTaGradedGradeable()->getId(),
                    $graded_component->getComponentId()
                ];
                $query = "
                    UPDATE gradeable_component_data SET
                      gcd_score=?,
                      gcd_component_comment=?,
                      gcd_graded_version=?,
                      gcd_grade_time=?,
                      gcd_grader_id=?,
                      gcd_verifier_id=?,
                      gcd_verify_time = ?
                    WHERE gd_id=? AND gc_id=?";
            }
            else {
                $params = [
                  $graded_component->getScore(),
                  $graded_component->getComment(),
                  $graded_component->getGradedVersion(),
                  DateUtils::dateTimeToString($graded_component->getGradeTime()),
                  $graded_component->getTaGradedGradeable()->getId(),
                  $graded_component->getComponentId(),
                  $graded_component->getGraderId()
                ];
                $query = "
                    UPDATE gradeable_component_data SET
                      gcd_score=?,
                      gcd_component_comment=?,
                      gcd_graded_version=?,
                      gcd_grade_time=?,
                    WHERE gd_id=? AND gc_id=? AND gcd_grader_id=?";
            }
            $this->course_db->query($query, $params);
        }
    }

    /**
     * Deletes a GradedComponent from the database
     * @param GradedComponent $graded_component
     */
    private function deleteGradedComponent(GradedComponent $graded_component) {
        // Only the db marks need to be deleted since the others haven't been applied to the database
        $this->deleteGradedComponentMarks($graded_component, $graded_component->getDbMarkIds());

        $params = [
            $graded_component->getTaGradedGradeable()->getId(),
            $graded_component->getComponentId(),
            $graded_component->getGrader()->getId()
        ];
        $query = "DELETE FROM gradeable_component_data WHERE gd_id=? AND gc_id=? AND gcd_grader_id=?";
        $this->course_db->query($query, $params);
    }

    /**
     * Update/create the components/marks for a gradeable.
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    private function updateGradedComponents(TaGradedGradeable $ta_graded_gradeable) {
        // iterate through graded components and see if any need updating/creating
        foreach ($ta_graded_gradeable->getGradedComponentContainers() as $container) {
            foreach ($container->getGradedComponents() as $component_grade) {
                if ($component_grade->isNew()) {
                    $this->createGradedComponent($component_grade);
                } else {
                    $this->updateGradedComponent($component_grade);
                }

                // If the marks have been modified, this means we need to update the entries
                if ($component_grade->isMarksModified()) {
                    $new_marks = array_diff($component_grade->getMarkIds(), $component_grade->getDbMarkIds() ?? []);
                    $deleted_marks = array_diff($component_grade->getDbMarkIds() ?? [], $component_grade->getMarkIds());
                    $this->deleteGradedComponentMarks($component_grade, $deleted_marks);
                    $this->createGradedComponentMarks($component_grade, $new_marks);
                }
            }
        }

        // Iterate through deleted graded components and see if anything should be deleted
        foreach ($ta_graded_gradeable->getDeletedGradedComponents() as $component_grade) {
            $this->deleteGradedComponent($component_grade);
        }
        $ta_graded_gradeable->clearDeletedGradedComponents();
    }

    /**
     * Creates a new Ta Grade in the database along with its graded components/marks
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    private function createTaGradedGradeable(TaGradedGradeable $ta_graded_gradeable) {
        $submitter_id = $ta_graded_gradeable->getGradedGradeable()->getSubmitter()->getId();
        $is_team = $ta_graded_gradeable->getGradedGradeable()->getSubmitter()->isTeam();
        $params = [
            $ta_graded_gradeable->getGradedGradeable()->getGradeable()->getId(),
            $is_team ? null : $submitter_id,
            $is_team ? $submitter_id : null,
            $ta_graded_gradeable->getOverallComment(),
            $ta_graded_gradeable->getUserViewedDate() !== null ?
                DateUtils::dateTimeToString($ta_graded_gradeable->getUserViewedDate()) : null,
        ];
        $query = "
            INSERT INTO gradeable_data (
                g_id,
                gd_user_id,
                gd_team_id,
                gd_overall_comment,
                gd_user_viewed_date)
            VALUES(?, ?, ?, ?, ?)";
        $this->course_db->query($query, $params);

        // Setup the graded gradeable with its new id
        $ta_graded_gradeable->setIdFromDatabase($this->course_db->getLastInsertId());

        // Also be sure to save the components
        $this->updateGradedComponents($ta_graded_gradeable);
    }

    /**
     * Updates an existing Ta Grade in the database along with its graded components/marks
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    private function updateTaGradedGradeable(TaGradedGradeable $ta_graded_gradeable) {
        // If the grade has been modified, then update its properties
        if ($ta_graded_gradeable->isModified()) {
            $params = [
                $ta_graded_gradeable->getOverallComment(),
                $ta_graded_gradeable->getUserViewedDate() !== null ?
                    DateUtils::dateTimeToString($ta_graded_gradeable->getUserViewedDate()) : null,
                $ta_graded_gradeable->getId()
            ];
            $query = "
                UPDATE gradeable_data SET
                  gd_overall_comment=?,
                  gd_user_viewed_date=?
                WHERE gd_id=?";
            $this->course_db->query($query, $params);
        }

        // Also be sure to save the components
        $this->updateGradedComponents($ta_graded_gradeable);
    }

    /**
     * Creates a Ta Grade in the database if it doesn't exist, otherwise it just updates it
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    public function saveTaGradedGradeable(TaGradedGradeable $ta_graded_gradeable) {
        // Ta Grades are initialized to have an id of 0 if not loaded from the db, so use that to check
        if($ta_graded_gradeable->getId() < 1) {
            $this->createTaGradedGradeable($ta_graded_gradeable);
        } else {
            $this->updateTaGradedGradeable($ta_graded_gradeable);
        }
    }

    /**
     * Deletes an entry from the gradeable_data table
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    public function deleteTaGradedGradeable(TaGradedGradeable $ta_graded_gradeable) {
        $this->course_db->query("DELETE FROM gradeable_data WHERE gd_id=?", [$ta_graded_gradeable->getId()]);
    }

    /**
     * Deletes an entry from the gradeable_data table with the provided gradeable id and user/team id
     * @param string $gradeable_id
     * @param int $submitter_id User or Team id
     */
    public function deleteTaGradedGradeableByIds($gradeable_id, $submitter_id) {
        $this->course_db->query('DELETE FROM gradeable_data WHERE g_id=? AND (gd_user_id=? OR gd_team_id=?)',
            [$gradeable_id, $submitter_id, $submitter_id]);
    }

    /**
     * Gets if the provied submitter has a submission for a particular gradeable
     * @param \app\models\gradeable\Gradeable $gradeable
     * @param Submitter $submitter
     * @return bool
     */
    public function getHasSubmission(gradeable\Gradeable $gradeable, Submitter $submitter) {
        $this->course_db->query('SELECT EXISTS (SELECT g_id FROM electronic_gradeable_data WHERE g_id=? AND (user_id=? OR team_id=?))',
            [$gradeable->getId(), $submitter->getId(), $submitter->getId()]);
        return $this->course_db->row()['exists'] ?? false;
    }

    /**
     * Get the active version for all given submitter ids. If they do not have an active version,
     * their version will be zero.
     * @param \app\models\gradeable\Gradeable $gradeable
     * @param string[] $submitter_ids
     * @return bool[] Map of id=>version
     */
    public function getActiveVersions(gradeable\Gradeable $gradeable, array $submitter_ids) {
        throw new NotImplementedException();
    }

    /**
     * Gets a list of emails for all active particpants in a course
     */
    public function getClassEmailList(){
        $parameters = array();
        $this->course_db->query('SELECT user_email FROM users WHERE user_group != 4 OR registration_section IS NOT null', $parameters);

        return $this->course_db->rows();
    }

    /**
    * Gets a list of emails with user ids for all active particpants in a course
    */

    public function getClassEmailListWithIds() {
      $parameters = array();
      $this->course_db->query('SELECT user_id, user_email FROM users WHERE registration_section IS NOT null', $parameters);

      return $this->course_db->rows();
    }

    /**
     * Queues an email to be sent by email job
     * @param array $email_data
     * @param Email $recipient
     */
    public function createEmail($email){
        $parameters = array($email->getRecipient(), $email->getSubject(), $email->getBody());

        $this->submitty_db->query("
            INSERT INTO emails(
              recipient,
              subject,
              body,
              created)
            VALUES(?, ?, ?, NOW())", $parameters);
    }
}
