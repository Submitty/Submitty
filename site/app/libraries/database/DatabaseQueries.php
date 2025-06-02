<?php

namespace app\libraries\database;

use app\controllers\admin\ConfigurationController;
use app\exceptions\DatabaseException;
use app\exceptions\ValidationException;
use app\exceptions\NotImplementedException;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\GradeableType;
use app\models\gradeable\Component;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedComponent;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Mark;
use app\models\gradeable\GradeInquiry;
use app\models\gradeable\Submitter;
use app\models\gradeable\TaGradedGradeable;
use app\models\User;
use app\models\Notification;
use app\models\SimpleLateUser;
use app\models\SimpleGradeOverriddenUser;
use app\models\Team;
use app\models\Course;
use app\models\SimpleStat;
use app\libraries\CascadingIterator;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\GradedComponentContainer;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\LateDayInfo;

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
    /**
     * @var Core
     */
    protected $core;

    /**
     * @var AbstractDatabase
     */
    protected $submitty_db;

    /**
     * @var AbstractDatabase
     */
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
     */
    public function getSubmittyUser(string $user_id): ?User {
        $this->submitty_db->query("SELECT * FROM users WHERE user_id=?", [$user_id]);
        return ($this->submitty_db->getRowCount() > 0) ? new User($this->core, $this->submitty_db->row()) : null;
    }

    /**
     * Gets all users from the submitty database, except nulls out password
     *
     * @return User[]
     */
    public function getAllSubmittyUsers() {
        $this->submitty_db->query("SELECT * FROM users");

        $users = [];
        foreach ($this->submitty_db->rows() as $user) {
            $user['user_password'] = null;
            $users[$user['user_id']] = new User($this->core, $user);
        }
        return $users;
    }

    /**
     * Gets some user's api key from the submitty database given a user_id.
     */
    public function getSubmittyUserApiKey(string $user_id): ?string {
        $this->submitty_db->query("SELECT api_key FROM users WHERE user_id=?", [$user_id]);
        return ($this->submitty_db->getRowCount() > 0) ? $this->submitty_db->row()['api_key'] : null;
    }

    /**
     * Refreshes some user's api key from the submitty database given a user_id.
     */
    public function refreshUserApiKey(string $user_id): void {
        $this->submitty_db->query("UPDATE users SET api_key=encode(gen_random_bytes(16), 'hex') WHERE user_id=?", [$user_id]);
    }

    /**
     * Gets a user from their api key.
     *
     * @param string $api_key
     *
     * @return string | null
     */
    public function getSubmittyUserByApiKey(string $api_key): ?string {
        $this->submitty_db->query("SELECT user_id FROM users WHERE api_key=?", [$api_key]);
        return ($this->submitty_db->getRowCount() > 0) ? $this->submitty_db->row()['user_id'] : null;
    }

    /**
     * Update a user's time zone string in the master database.
     *
     * @param User $user The user object for the user who should have their time zone modified
     * @param string $time_zone A time zone string which may found in DateUtils::getAvailableTimeZones()
     * @return int 1 if the update was successful, 0 if the operation failed
     */
    public function updateSubmittyUserTimeZone(User $user, string $time_zone) {
        $this->submitty_db->query("update users set time_zone = ? where user_id = ?", [$time_zone, $user->getId()]);
        return $this->submitty_db->getRowCount();
    }

    /**
     * Update a user's preferred locale in the master database.
     *
     * @param User $user The user object to modify
     * @param string|null $locale The locale string to set it to, must be one of Core::getSupportedLocales()
     */
    public function updateSubmittyUserPreferredLocale(User $user, string|null $locale): void {
        $this->submitty_db->query("UPDATE users SET user_preferred_locale=? WHERE user_id=?", [$locale, $user->getId()]);
    }

    /**
     * Gets a user from the database given a user_id.
     *
     * @param string $user_id
     * @return User|null
     */
    public function getUserById(string $user_id): ?User {
        return $this->getUser($user_id);
    }

    /**
     * Gets a user from the database given a numeric user_id.
     *
     * @param int $numeric_id
     * @return User|null
     */
    public function getUserByNumericId(int $numeric_id): ?User {
        return $this->getUser($numeric_id, true);
    }

    /**
     * Gets a list of given and family names from the database given an array of IDs.
     *
     * @param array $user_id_list
     * @return array|null
     */
    public function getUsersByIds(array $user_id_list): ?array {
        return $this->getUsers($user_id_list);
    }

    /**
     * Retrieves all students from a course and their virtual attendance
     * information such as logs for the course materials page, and logs
     * for the discussion forum page.
     */
    public function getAttendanceInfo(): array {
        $this->course_db->query("
        WITH
        A AS
        (SELECT registration_section, user_id, COALESCE(user_preferred_givenname, user_givenname) as user_givenname, COALESCE(user_preferred_familyname, user_familyname) as user_familyname
        FROM users
        ORDER BY registration_section, user_familyname, user_givenname, user_id),
        B AS
        (SELECT distinct on (user_id) user_id, timestamp
        FROM gradeable_access where user_id is not NULL
        ORDER BY user_id, timestamp desc),
        C AS
        (SELECT distinct on (user_id) user_id,submission_time
        FROM electronic_gradeable_data
        ORDER BY user_id, submission_time desc),
        D AS
        (SELECT distinct on (user_id) user_id, timestamp
        FROM viewed_responses
        ORDER BY user_id, timestamp desc),
        E AS
        (SELECT distinct on (author_user_id) author_user_id, timestamp
        FROM posts
        ORDER BY author_user_id, timestamp desc),
        F AS
        (SELECT student_id, count (DISTINCT poll_id)
        FROM poll_responses
        GROUP BY student_id),
        G AS
        (SELECT distinct on (user_id) user_id, time_in
        FROM queue
        ORDER BY user_id, time_in desc),
        H AS
        (SELECT distinct on (user_id) user_id, timestamp
        FROM course_materials_access
        ORDER BY user_id, timestamp desc)
        SELECT
        A.registration_section, A.user_id, A.user_givenname, user_familyname,
        B.timestamp as gradeable_access,
        C.submission_time as gradeable_submission,
        D.timestamp as forum_view,
        E.timestamp as forum_post,
        F.count as num_poll_responses,
        G.time_in as office_hours_queue,
        H.timestamp as course_materials_access
        FROM
        A
        left join B on A.user_id=B.user_id
        left join C on A.user_id=C.user_id
        left join D on A.user_id=D.user_id
        left join E on A.user_id=E.author_user_id
        left join F on A.user_id=F.student_id
        left join G on A.user_id=G.user_id
        left join H on A.user_id=H.user_id
        ORDER BY length(A.registration_section), A.registration_section, A.user_familyname, A.user_givenname, A.user_id;
        ");
        return $this->course_db->rows();
    }

    /**
     * Returns an array of the most recent activity a student has done in the course.
     * The order is: gradeable access, gradeable submission, forum view, forum post,
     *   queue join, and course material access.
     * @param string $user_id Name of user.
     * @return array<string|null> The timestamps of activity in this course.
     */
    public function getAttendanceInfoOneStudent(string $user_id): array {
        $this->course_db->query("
            WITH
                Input (user_id) AS (VALUES (?)),
                Gradeable_Access AS
                    (SELECT timestamp, user_id
                    FROM gradeable_access where user_id = (table Input)
                    ORDER BY timestamp desc
                    LIMIT 1),
                Gradeable_Submission AS
                    (SELECT submission_time, user_id
                    FROM electronic_gradeable_data where user_id = (table Input)
                    ORDER BY submission_time desc
                    LIMIT 1),
                Forum_View AS 
                    (SELECT timestamp, user_id
                    FROM viewed_responses where user_id = (table Input)
                    ORDER BY timestamp desc
                    LIMIT 1),
                Forum_Post AS
                    (SELECT timestamp, author_user_id
                    FROM posts where author_user_id = (table Input)
                    ORDER BY timestamp desc
                    LIMIT 1),
                Queue_Join AS
                    (SELECT time_in, user_id
                    FROM queue where user_id = (table Input)
                    ORDER BY time_in desc
                    LIMIT 1),
                Course_Materials_Access AS
                    (SELECT timestamp, user_id
                    FROM course_materials_access where user_id = (table Input)
                    ORDER BY timestamp desc
                    LIMIT 1)
            SELECT
                Gradeable_Access.timestamp as gradeable_access,
                Gradeable_Submission.submission_time as gradeable_submission,
                Forum_View.timestamp as forum_view,
                Forum_Post.timestamp as forum_post,
                Queue_Join.time_in as queue_join,
                Course_Materials_Access.timestamp as course_materials_access
            FROM
                Input
            LEFT JOIN Gradeable_Access on Input.user_id = Gradeable_Access.user_id
            LEFT JOIN Gradeable_Submission on Input.user_id = Gradeable_Submission.user_id
            LEFT JOIN Forum_View on Input.user_id = Forum_View.user_id
            LEFT JOIN Forum_Post on Input.user_id = Forum_Post.author_user_id
            LEFT JOIN Queue_Join on Input.user_id = Queue_Join.user_id
            LEFT JOIN Course_Materials_Access on Input.user_id = Course_Materials_Access.user_id;
        ", [$user_id]);

        $response = $this->course_db->row();

        return array_map(
            function ($element) {
                if (is_null($element)) {
                    return null;
                }
                return DateUtils::convertTimeStamp($this->core->getUser(), $element, 'Y-m-d H:i:s');
            },
            $response
        );
    }

    /**
     * given a string with missing digits, get all similar numeric ids
     * should be given as '1_234_567' where '_' are positions to fill in
     *
     * @param string $id_string
     */
    public function getSimilarNumericIdMatches(string $id_string): array {
        $this->course_db->query("
            SELECT user_numeric_id from users where
            cast(user_numeric_id as text)
            like ?
        ", [$id_string]);

        $ret = [];
        foreach ($this->course_db->rows() as $result) {
            $ret[] = $result['user_numeric_id'];
        }

        return $ret;
    }

    public function getGradingSectionsByUserId($user_id) {
        $this->course_db->query("
SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
FROM grading_registration
WHERE user_id=?
GROUP BY user_id", [$user_id]);
        return $this->course_db->row();
    }

    /**
     * Fetches all students from the users table, ordering by course section than user_id.
     *
     * @param  string $section_key
     * @return User[]
     */
    public function getAllUsers($section_key = "registration_section") {
        $keys = ["registration_section", "rotating_section"];
        $section_key = (in_array($section_key, $keys)) ? $section_key : "registration_section";
        $orderBy = "";
        if ($section_key == "registration_section") {
            $orderBy = "SUBSTRING(u.registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(u.registration_section, '[0-9]+')::INT, -1), SUBSTRING(u.registration_section, '[^0-9]*$'), u.user_id";
        }
        else {
            $orderBy = "u.{$section_key}, u.user_id";
        }

        $this->course_db->query(
            "
SELECT u.*, sr.grading_registration_sections
FROM users u
LEFT JOIN (
	SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
	FROM grading_registration
	GROUP BY user_id
) as sr ON u.user_id=sr.user_id
ORDER BY {$orderBy}"
        );
        $return = [];
        foreach ($this->course_db->rows() as $user) {
            if (isset($user['grading_registration_sections'])) {
                $user['grading_registration_sections'] = $this->course_db->fromDatabaseToPHPArray($user['grading_registration_sections']);
            }
            $return[] = new User($this->core, $user);
        }
        return $return;
    }

    /**
     * Gets an indexed array of all the user_ids for all users who are members of the current course.
     *
     * @return array An array of all the users
     */
    public function getListOfCourseUsers(): array {
        $sql = 'SELECT user_id FROM users';
        $this->course_db->query($sql);
        return array_map(function ($row) {
            return $row['user_id'];
        }, $this->course_db->rows());
    }

    /**
     * Update master and course database user's display_image_state to a new state
     *
     * @param string $user_id
     * @param string $state
     * @return bool
     */
    public function updateUserDisplayImageState(string $user_id, string $state): bool {
        $sql = 'UPDATE users SET display_image_state = ? WHERE user_id = ?';
        $this->submitty_db->query($sql, [$state, $user_id]);
        return $this->submitty_db->getRowCount() === 1;
    }

    /**
     * @return User[]
     */
    public function getAllGraders() {
        $this->course_db->query(
            "
SELECT u.*, sr.grading_registration_sections
FROM users u
LEFT JOIN (
	SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
	FROM grading_registration
	GROUP BY user_id
) as sr ON u.user_id=sr.user_id
WHERE u.user_group < 4
ORDER BY SUBSTRING(u.registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(u.registration_section, '[0-9]+')::INT, -1), SUBSTRING(u.registration_section, '[^0-9]*$'), u.user_id"
        );
        $return = [];
        foreach ($this->course_db->rows() as $user) {
            if (isset($user['grading_registration_sections'])) {
                $user['grading_registration_sections'] = $this->course_db->fromDatabaseToPHPArray($user['grading_registration_sections']);
            }
            $return[] = new User($this->core, $user);
        }
        return $return;
    }

    /**
     * @return User[]
     */
    public function getAllFaculty() {
        $this->submitty_db->query(
            "
SELECT *
FROM users
WHERE user_access_level <= ?
ORDER BY user_id",
            [User::LEVEL_FACULTY]
        );
        $return = [];
        foreach ($this->submitty_db->rows() as $user) {
            $return[] = new User($this->core, $user);
        }
        return $return;
    }

    /**
     * @return string[]
     */
    public function getAllUnarchivedSemester() {
        $this->submitty_db->query(
            "
SELECT DISTINCT term
FROM courses
WHERE status = 1"
        );
        $return = [];
        foreach ($this->submitty_db->rows() as $row) {
            $return[] = $row['term'];
        }
        return $return;
    }

    /**
     * @return \Iterator<Course>
     */
    public function getAllUnarchivedCourses(): \Iterator {
        $sql = <<<SQL
SELECT t.name AS term_name, c.term, c.course
FROM courses AS c
INNER JOIN terms AS t ON c.term=t.term_id
WHERE c.status = 1
ORDER BY t.start_date DESC, c.course ASC
SQL;
        return $this->submitty_db->queryIterator($sql, [], function ($row) {
            return new Course($this->core, $row);
        });
    }

    /*
     * @return string[]
     */
    public function getAllTerms() {
        $this->submitty_db->query(
            "SELECT term_id FROM terms ORDER BY start_date DESC"
        );
        $return = [];
        foreach ($this->submitty_db->rows() as $row) {
            $return[] = $row['term_id'];
        }
        return $return;
    }

    /**
     * Returns the provided term's start date in the given user's timezone.
     * @param string $term Id of term we are checking.
     * @param User $user whose timezone we get the date in.
     * @return string The start date of the term.
     */
    public function getTermStartDate(string $term, User $user): string {
        $this->submitty_db->query("
            SELECT start_date
            FROM terms
            WHERE term_id=?
        ", [$term]);
        $timestamp = $this->submitty_db->rows()[0]['start_date'];
        return DateUtils::convertTimeStamp($user, $timestamp, 'Y-m-d H:i:s');
    }

    /**
     * @param string $term_id
     * @param string $term_name
     * @param \DateTime $start_date
     * @param \DateTime $end_date
     */
    public function createNewTerm($term_id, $term_name, $start_date, $end_date) {
        $this->submitty_db->query(
            "INSERT INTO terms (term_id, name, start_date, end_date) VALUES (?, ?, ?, ?)",
            [$term_id, $term_name, $start_date, $end_date]
        );
    }

    /**
     * @param User $user
     */
    public function insertSubmittyUser(User $user) {
        $array = [$user->getId(), $user->getPassword(), $user->getNumericId(),
                       $user->getLegalGivenName(), $user->getPreferredGivenName(),
                       $user->getLegalFamilyName(), $user->getPreferredFamilyName(), $user->getEmail(),
                       $this->submitty_db->convertBoolean($user->isUserUpdated()),
                       $this->submitty_db->convertBoolean($user->isInstructorUpdated())];

        $this->submitty_db->query(
            "INSERT INTO users (user_id, user_password, user_numeric_id, user_givenname, user_preferred_givenname, user_familyname, user_preferred_familyname, user_email, user_updated, instructor_updated)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            $array
        );
    }

    public function getCategoriesIdForThread($thread_id) {
        $this->course_db->query("SELECT category_id from thread_categories t where t.thread_id = ?", [$thread_id]);
        $categories_list = [];
        foreach ($this->course_db->rows() as $row) {
            $categories_list[] = (int) $row["category_id"];
        }
        return $categories_list;
    }

    /**
     * toggles a like from upduck to off or off to upduck
     *
     * @param int $post_id The ID of the post.
     * @param string $current_user The ID of the current user.
     * @return array{ status: string, likesCount: int, likesFromStaff: int}
     * 'status' indicating 'like' or 'unlike',
     * 'likesCount' indicating the total number of likes,
     * 'likesFromStaff' indicating the number of likes from staff members.
     */
    public function toggleLikes(int $post_id, string $current_user): array {
        try {
            $this->course_db->query("SELECT * FROM forum_upducks WHERE post_id = ? AND user_id = ?", [$post_id, $current_user]);
            $inDatabase = isset($this->course_db->rows()[0]);

            $sqlFilteredCount = "SELECT COUNT(*) AS filtered_likes_count
                             FROM forum_upducks f
                             JOIN users u ON f.user_id = u.user_id
                             WHERE f.post_id = ?
                             AND u.user_group <= 3";
            if ($inDatabase) {
                $this->course_db->query("DELETE FROM forum_upducks WHERE post_id = ? AND user_id = ?", [$post_id, $current_user]);
                $action = "unlike";
            }
            else {
                $this->course_db->query("INSERT INTO forum_upducks (post_id, user_id) VALUES (?, ?)", [$post_id, $current_user]);
                $action = "like";
            }
            $this->course_db->query("SELECT COUNT(*) AS likes_count FROM forum_upducks WHERE post_id = ?", [$post_id]);
            $likesCount = intval($this->course_db->row()['likes_count']);
            $this->course_db->query($sqlFilteredCount, [$post_id]);
            $filteredLikesCount = intval($this->course_db->row()['filtered_likes_count']);
            return [
                'status' => $action, // 'like' or 'unlike'
                'likesCount' => $likesCount, // Total likes count
                'likesFromStaff' => $filteredLikesCount // Likes from staff
            ];
        }
        catch (DatabaseException $dbException) {
            if ($this->course_db->inTransaction()) {
                $this->course_db->rollback();
            }
            return ['status' => "false", 'likesCount' => 0, 'likesFromStaff' => 0];
        }
    }

    public function splitPost($post_id, $title, $categories_ids) {
        $old_thread_id = -1;
        $thread_id = -1;
        $post = $this->core->getQueries()->getPost($post_id);
        // Safety measure in case the database is bad for some reason
        $counted_posts = [];
        if (count($post) > 0) {
            if ($post["parent_id"] != -1) {
                $old_thread_id = $post["thread_id"];
                $this->course_db->query("SELECT id from threads where merged_post_id = ?", [$post_id]);
                $thread_id = $this->course_db->rows();
                if (count($thread_id) > 0) {
                    $thread_id = $thread_id[0]["id"];
                    $this->course_db->query("UPDATE threads set merged_thread_id=-1, merged_post_id=-1 where id=?", [$thread_id]);
                    $this->course_db->query("DELETE FROM thread_categories where thread_id=?", [$thread_id]);
                }
                else {
                    //TODO: Update AbstractDatabase.php to work with returning syntax
                    $this->course_db->query("INSERT INTO threads (title, created_by, is_visible, lock_thread_date) VALUES (?, ?, ?, ?)", [$title, $post["author_user_id"], true, null]);
                    $this->course_db->query("SELECT MAX(id) as max_id from threads where title=? and created_by=?", [$title, $post["author_user_id"]]);
                    $thread_id = $this->course_db->row()["max_id"];
                }
                $str = "";
                $arr = [];
                foreach ($categories_ids as $id) {
                    if (!empty($str)) {
                        $str .= ", ";
                    }
                    $str .= "({$thread_id}, ?)";
                    array_push($arr, $id);
                }
                $this->course_db->query("INSERT INTO thread_categories (thread_id, category_id) VALUES {$str}", $arr);
                $posts = [$post];
                while (count($posts) > 0) {
                    $check_posts = [];
                    $str = "";
                    foreach ($posts as $check_post) {
                        if (!in_array($check_post["id"], $counted_posts)) {
                            $check_posts[] = $check_post["id"];
                            $str .= "?, ";
                            $counted_posts[] = $check_post["id"];
                            $this->course_db->query("UPDATE posts set thread_id = ? where parent_id = ?", [$thread_id, $check_post["id"]]);
                        }
                    }
                    if (strlen($str) > 0) {
                        $str = substr($str, 0, -2);
                    }
                    $this->course_db->query("SELECT id from posts where parent_id in (" . $str . ")", $check_posts);
                    $posts = $this->course_db->rows();
                }
                $this->course_db->query("UPDATE posts set thread_id=?, parent_id=? where id=?", [$thread_id, -1, $post_id]);
            }
        }
        return [$old_thread_id, $thread_id, $counted_posts];
    }

    /**
     * @param string[] $attachment_name
     */
    public function createPost($user, $content, $thread_id, $anonymous, $type, $first, $hasAttachment, $markdown, array $attachment_name, $parent_post = -1) {
        if (!$first && $parent_post == 0) {
            $this->course_db->query("SELECT MIN(id) as id FROM posts where thread_id = ?", [$thread_id]);
            $parent_post = $this->course_db->row()["id"];
        }

        if (!$markdown) {
            $markdown = 0;
        }

        try {
            $this->course_db->query("INSERT INTO posts (thread_id, parent_id, author_user_id, content, timestamp, anonymous, deleted, endorsed_by, type, has_attachment, render_markdown, version_id) VALUES (?, ?, ?, ?, current_timestamp, ?, ?, ?, ?, ?, ?, 1)", [$thread_id, $parent_post, $user, $content, $anonymous, 0, null, $type, $hasAttachment, $markdown]);
            $this->course_db->query("SELECT MAX(id) as max_id from posts where thread_id=? and author_user_id=?", [$thread_id, $user]);
            $id = $this->course_db->row()["max_id"];
            $this->visitThread($user, $thread_id);
            if ($hasAttachment) {
                $rows = [];
                $params = [];
                foreach ($attachment_name as $img) {
                    $params[] = $this->createParameterList(4);
                    $rows = array_merge($rows, [$id, $img, 1, 0]);
                }
                $placeholders = implode(", ", $params);
                $this->course_db->query("INSERT INTO forum_attachments (post_id, file_name, version_added, version_deleted) VALUES {$placeholders}", $rows);
            }
            return $id;
        }
        catch (DatabaseException $dbException) {
            if ($this->course_db->inTransaction()) {
                $this->course_db->rollback();
            }
            return false;
        }
    }

    public function findParentPost($thread_id) {
        $this->course_db->query("SELECT * from posts where thread_id = ? and parent_id = -1", [$thread_id]);
        return $this->course_db->row();
    }
    public function findThread($thread_id) {
        $this->course_db->query("SELECT * from threads where id = ?", [$thread_id]);
        return $this->course_db->row();
    }

    public function updateResolveState($thread_id, $state) {
        if (in_array($state, [-1, 0, 1])) {
            $this->course_db->query("UPDATE threads set status = ? where id = ?", [$state, $thread_id]);
            return true;
        }
        return false;
    }

    public function updateNotificationSettings($results) {
        $values = implode(', ', array_fill(0, count($results) + 1, '?'));
        $keys = implode(', ', array_keys($results));
        $updates = '';

        foreach ($results as $key => $value) {
            if ($value != 'false') {
                $results[$key] = 'true';
            }
            $this->core->getUser()->updateUserNotificationSettings($key, $results[$key] == 'true');
            $updates .= $key . ' = ?,';
        }

        $updates = substr($updates, 0, -1);
        $test = array_merge(array_merge([$this->core->getUser()->getId()], array_values($results)), array_values($results));
        $this->course_db->query(
            "INSERT INTO notification_settings (user_id, $keys)
                                    VALUES
                                     (
                                        $values
                                     )
                                    ON CONFLICT (user_id)
                                    DO
                                     UPDATE
                                        SET $updates",
            $test
        );
    }

    public function getAuthorOfThread($thread_id) {
        $this->course_db->query("SELECT created_by from threads where id = ?", [$thread_id]);
        return $this->course_db->row()['created_by'];
    }

    public function getPosts() {
        $this->course_db->query("SELECT * FROM posts where deleted = false ORDER BY timestamp ASC");
        return $this->course_db->rows();
    }

    /**
     * Retrieves the number of upducks per user.
     *
     * @return array<array<string, string>>
     */
    public function getUpDucks(): array {
        $this->course_db->query("
            SELECT 
                f.user_id,
                COUNT(*) AS upducks 
            FROM 
                forum_upducks f
            JOIN 
                posts p
            ON 
                f.post_id = p.id
            WHERE 
                p.deleted = FALSE 
            GROUP BY 
                f.user_id
            ORDER BY 
                upducks DESC
        ");
        return $this->course_db->rows();
    }

    public function getPostsInThreads($thread_ids) {
        if (count($thread_ids) === 0) {
            return [];
        }
        $placeholders = $this->createParameterList(count($thread_ids));
        $this->course_db->query("SELECT * FROM posts where deleted = false and thread_id in {$placeholders} ORDER BY timestamp ASC", $thread_ids);
        return $this->course_db->rows();
    }

        /**
         * Gets the list of users who liked a given post.
         *
         * @param int $post_id
         * @return string[] Array of user ids who liked this post.
         */
    public function getUsersWhoLikedPost(int $post_id): array {
        $this->course_db->query("
            SELECT u.user_id 
            FROM forum_upducks f
            JOIN users u ON f.user_id = u.user_id
            WHERE f.post_id = ?
        ", [$post_id]);

        $rows = $this->course_db->rows();
        // return user_id/formatted name
        return array_map(fn($row) => $row['user_id'], $rows);
    }

    public function getPostOldThread($post_id) {
        $this->course_db->query("SELECT id, merged_thread_id, title FROM threads WHERE merged_thread_id <> -1 AND merged_post_id = ?", [$post_id]);
        $rows = $this->course_db->rows();
        if (count($rows) > 0) {
            return $rows[0];
        }
        else {
            $rows = [];
            $rows["merged_thread_id"] = -1;
            return $rows;
        }
    }

    public function getDeletedPostsByUser($user) {
        $this->course_db->query("SELECT * FROM posts where deleted = true AND author_user_id = ?", [$user]);
        return $this->course_db->rows();
    }

    public function getPost($post_id) {
        $this->course_db->query("SELECT * FROM posts where id = ?", [$post_id]);
        return $this->course_db->row();
    }

    public function removeNotificationsPost($post_id) {
        //Deletes all children notifications i.e. this posts replies
        $this->course_db->query("DELETE FROM notifications where metadata::json->>'thread_id' = ?", [$post_id]);
        //Deletes parent notification i.e. this post is a reply
        $this->course_db->query("DELETE FROM notifications where metadata::json->>'post_id' = ?", [$post_id]);
    }

    public function isStaffPost($author_id) {
        $this->course_db->query("SELECT user_group FROM users WHERE user_id=?", [$author_id]);
        return intval($this->course_db->row()['user_group']) <= 3;
    }

    public function getAuthorUserGroups($author_ids) {
        if (count($author_ids) === 0) {
            return [];
        }
        $placeholders = $this->createParameterList(count($author_ids));
        $this->course_db->query("SELECT user_id, user_group FROM users WHERE user_id IN {$placeholders}", $author_ids);
        return $this->course_db->rows();
    }

    /**
     * @param string[] $attachment_name
     */
    public function createThread($markdown, $user, $title, $content, $anon, $prof_pinned, $status, $hasAttachment, $attachment_name, $categories_ids, $lock_thread_date, $expiration, $announcement) {
        $this->course_db->beginTransaction();

        $now = $announcement ? $this->core->getDateTimeNow() : null;

        try {
            //insert data
            $this->course_db->query("INSERT INTO threads (title, created_by, pinned, status, deleted, merged_thread_id, merged_post_id, is_visible, lock_thread_date, pinned_expiration, announced) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [$title, $user, $prof_pinned, $status, 0, -1, -1, true, $lock_thread_date, $expiration, $now]);
            //retrieve generated thread_id
            $this->course_db->query("SELECT MAX(id) as max_id from threads where title=? and created_by=?", [$title, $user]);
        }
        catch (DatabaseException $dbException) {
            $this->course_db->rollback();
        }

        //Max id will be the most recent post
        $id = $this->course_db->row()["max_id"];

        foreach ($categories_ids as $category_id) {
            $this->course_db->query("INSERT INTO thread_categories (thread_id, category_id) VALUES (?, ?)", [$id, $category_id]);
        }

        $post_id = $this->createPost($user, $content, $id, $anon, 0, true, $hasAttachment, $markdown, $attachment_name);

        $this->course_db->commit();

        $this->visitThread($user, $id);

        return ["thread_id" => $id, "post_id" => $post_id];
    }

    public function setAnnounced($thread_id) {
        $now = $this->core->getDateTimeNow();
        $this->course_db->query("UPDATE threads SET announced = ? WHERE id = ?", [$now, $thread_id]);
    }

    public function getThread(int $thread_id) {
        $this->course_db->query("SELECT * from threads where id = ?", [$thread_id]);
        return $this->course_db->row();
    }

    public function getThreadTitle(int $thread_id) {
        $this->course_db->query("SELECT title FROM threads where id=?", [$thread_id]);
        return $this->course_db->row()['title'];
    }

    public function setAnnouncement(int $thread_id, bool $onOff) {
        $expireTime = $onOff ? date("Y-m-d H:i:s", strtotime('+7 days')) : '1900-01-01 00:00:00';
        $this->course_db->query("UPDATE threads SET pinned_expiration = ? WHERE id = ?", [$expireTime, $thread_id]);
    }

    public function addBookmarkedThread(string $user_id, int $thread_id, bool $added) {
        if ($added) {
            $this->course_db->query("INSERT INTO student_favorites(user_id, thread_id) VALUES (?,?) ON CONFLICT DO NOTHING", [$user_id, $thread_id]);
        }
        else {
            $this->course_db->query("DELETE FROM student_favorites where user_id=? and thread_id=?", [$user_id, $thread_id]);
        }
    }

    private function findChildren($post_id, $thread_id, &$children, $get_deleted = false) {
        $query_delete = $get_deleted ? "true" : "deleted = false";
        $this->course_db->query("SELECT id from posts where {$query_delete} and parent_id=?", [$post_id]);
        $row = $this->course_db->rows();
        for ($i = 0; $i < count($row); $i++) {
            $child_id = $row[$i]["id"];
            array_push($children, $child_id);
            $this->findChildren($child_id, $thread_id, $children, $get_deleted);
        }
    }

    public function searchThreads($searchQuery) {
        $this->course_db->query(
            "SELECT post_content, p_id, p_author, thread_id, thread_title, author, pin, anonymous, timestamp_post
            FROM (SELECT t.id as thread_id, t.title as thread_title, p.id as p_id,
                t.created_by as author, t.pinned_expiration as pin, p.timestamp as timestamp_post,
                p.content as post_content, p.anonymous, p.author_user_id as p_author,
                to_tsvector('english', replace(replace(replace(p.content, '.', ' '), '-', ' '), '/', ' '))
                || to_tsvector('english', replace(replace(replace(t.title, '.', ' '), '-', ' '), '/', ' '))
                as document FROM posts p, threads t
                JOIN (SELECT thread_id, timestamp FROM posts WHERE parent_id = -1) p2
                ON p2.thread_id = t.id
                WHERE t.id = p.thread_id and p.deleted=false and t.deleted=false) p_doc
            WHERE p_doc.document @@ plainto_tsquery('english', replace(:q, '.', ' '))
            ORDER BY timestamp_post DESC",
            [':q' => $searchQuery]
        );
        return $this->course_db->rows();
    }

    public function threadExists() {
        $this->course_db->query("SELECT id from threads where deleted = false LIMIT 1");
        return count($this->course_db->rows()) == 1;
    }

    public function visitThread($current_user, $thread_id, string $last_viewed_post_time = null) {
        // If the user has already visited the thread, set timestamp to current time
        if ($last_viewed_post_time === null) {
            $this->course_db->query("
                INSERT INTO viewed_responses(thread_id, user_id, timestamp)
                VALUES(?, ?, current_timestamp)
                ON CONFLICT (thread_id, user_id)
                DO UPDATE SET timestamp = current_timestamp
            ", [$thread_id, $current_user]);
        }
        else {
            // Else set timestamp to the last view timestamp which is the post date of the last post viewed by user
            $this->course_db->query("
                INSERT INTO viewed_responses(thread_id, user_id, timestamp)
                VALUES(?, ?, ?)
                ON CONFLICT (thread_id, user_id)
                DO UPDATE SET timestamp = ?
            ", [$thread_id, $current_user, $last_viewed_post_time, $last_viewed_post_time]);
        }
    }

    /**
     * @param string $current_user
     * @param int $thread_id
     * @return null
     */
    public function unreadThread(string $current_user, int $thread_id) {
        $this->course_db->query("DELETE FROM viewed_responses where thread_id = ? and user_id = ?", [$thread_id, $current_user]);
        return null;
    }

    public function getParentPostId($child_id) {
        $this->course_db->query("SELECT parent_id from posts where id = ?", [$child_id]);
        return $this->course_db->row()['parent_id'];
    }

    /**
     * @param int[] $post_ids
     * @return array<int, array<int, string[]>>
     *
     */
    public function getForumAttachments(array $post_ids, bool $all_vers = false): array {
        if (count($post_ids) === 0) {
            return [];
        }
        $return = [];
        // Default version is current version
        $placeholders = $this->createParameterList(count($post_ids));
        if ($all_vers === false) {
            // Initialize return values
            foreach ($post_ids as $post_id) {
                $return[$post_id][0] = [];
            }
            $this->course_db->query("SELECT * FROM forum_attachments WHERE post_id IN {$placeholders} AND version_deleted = 0", $post_ids);
            foreach ($this->course_db->rows() as $row) {
                $return[$row['post_id']][0][] = $row['file_name'];
            }
        }
        else {
            // Get current version of each post
            $this->course_db->query("SELECT id, version_id FROM posts WHERE id IN {$placeholders}", $post_ids);
            $current_versions = [];
            foreach ($post_ids as $post_id) {
                $current_versions[$post_id] = 1;
            }
            foreach ($this->course_db->rows() as $row) {
                $current_versions[$row['id']] = $row['version_id'];
            }
            // Initialize return values
            foreach ($post_ids as $post_id) {
                for ($i = 1; $i <= $current_versions[$post_id]; $i++) {
                    $return[$post_id][$i] = [];
                }
            }
            $this->course_db->query("SELECT * FROM forum_attachments WHERE post_id IN {$placeholders}", $post_ids);
            foreach ($this->course_db->rows() as $row) {
                $version_max = ($row['version_deleted'] === 0) ? $current_versions[$row['post_id']] : $row['version_deleted'] - 1;
                foreach (range($row['version_added'], $version_max) as $version) {
                    $return[$row['post_id']][$version][] = $row['file_name'];
                }
            }
        }
        return $return;
    }

    /**
     * @param User   $user
     * @param string $semester
     * @param string $course
     */
    public function insertCourseUser(User $user, $semester, $course) {
        $params = [$semester, $course, $user->getId(), $user->getGroup(), $user->getRegistrationSection(),
                        $this->submitty_db->convertBoolean($user->isManualRegistration())];
        $this->submitty_db->query(
            "
INSERT INTO courses_users (term, course, user_id, user_group, registration_section, manual_registration)
VALUES (?,?,?,?,?,?)",
            $params
        );

        $params = [$user->getRotatingSection(), $user->getRegistrationSubsection(), $user->getRegistrationType(), $user->getId()];
        $this->course_db->query("UPDATE users SET rotating_section=?, registration_subsection=?, registration_type=? WHERE user_id=?", $params);
        $this->updateGradingRegistration($user->getId(), $user->getGroup(), $user->getGradingRegistrationSections());
    }

    /**
     * @param User $user
     * @param string|null $semester
     * @param string|null $course
     */
    public function updateUser(User $user, $semester = null, $course = null) {
        $params = [$user->getNumericId(), $user->getPronouns(), $user->getDisplayPronouns(), $user->getLegalGivenName(), $user->getPreferredGivenName(),
                       $user->getLegalFamilyName(), $user->getPreferredFamilyName(), $user->getLastInitialFormat(), $user->getDisplayNameOrder(), $user->getEmail(),
                       $user->getSecondaryEmail(), $this->submitty_db->convertBoolean($user->getEmailBoth()),
                       $this->submitty_db->convertBoolean($user->isUserUpdated()),
                       $this->submitty_db->convertBoolean($user->isInstructorUpdated())];
        $extra = "";
        if (!empty($user->getPassword())) {
            $params[] = $user->getPassword();
            $extra = ", user_password=?";
        }
        $params[] = $user->getId();

        // User preferred name tracking: Master DB cannot tell who is logged
        // into Submitty, so the AUTH token and $logged_in var embedded as a SQL
        // comment will be noted in Postgresql's logs as who has issued a change
        // in user's preferred name.
        $logged_in = $this->core->getUser()->getId();

        $this->submitty_db->query(
            "
UPDATE users
SET
  user_numeric_id=?, user_pronouns=?, display_pronouns=?, user_givenname=?, user_preferred_givenname=?,
  user_familyname=?, user_preferred_familyname=?, user_last_initial_format=?, display_name_order=?,
  user_email=?, user_email_secondary=?, user_email_secondary_notify=?,
  user_updated=?, instructor_updated=?{$extra}
WHERE user_id=? /* AUTH: \"{$logged_in}\" */",
            $params
        );

        if (!empty($semester) && !empty($course)) {
            $params = [$user->getGroup(), $user->getRegistrationSection(),
                            $this->submitty_db->convertBoolean($user->isManualRegistration()),
                            $user->getRegistrationType(), $semester, $course,
                            $user->getId()];
            $this->submitty_db->query(
                "
UPDATE courses_users SET user_group=?, registration_section=?, manual_registration=?, registration_type=?
WHERE term=? AND course=? AND user_id=?",
                $params
            );

            $params = [$user->getRotatingSection(), $user->getRegistrationSubsection(), $user->getId()];
            $this->course_db->query("UPDATE users SET rotating_section=?, registration_subsection=? WHERE user_id=?", $params);
            $this->updateGradingRegistration($user->getId(), $user->getGroup(), $user->getGradingRegistrationSections());
        }
    }

    /**
     * @param string    $user_id
     * @param integer   $user_group
     * @param integer[] $sections
     */
    public function updateGradingRegistration($user_id, $user_group, $sections) {
        $this->course_db->query("DELETE FROM grading_registration WHERE user_id=?", [$user_id]);
        if ($user_group < 4) {
            foreach ($sections as $section) {
                $this->course_db->query(
                    "
    INSERT INTO grading_registration (user_id, sections_registration_id) VALUES(?, ?)",
                    [$user_id, $section]
                );
            }
        }
    }

    /**
     * Gets the group that the user is in for a given class (used on homepage)
     *
     * Classes are distinct for each semester *and* course
     *
     * @param  string $semester    - class's working semester
     * @param  string $course_name - class's course name
     * @param  string $user_id     - user id to be searched for
     * @return integer - group number of user in the given class
     */
    public function getGroupForUserInClass($semester, $course_name, $user_id) {
        $this->submitty_db->query("SELECT user_group FROM courses_users WHERE user_id = ? AND course = ? AND term = ?", [$user_id, $course_name, $semester]);
        return intval($this->submitty_db->row()['user_group']);
    }

    /**
     * Gets an unique array of active users in courses_users, active users are users enrolled in a course in the current semester
     * Allows the filtering the group of users returned by this function
     *
     * @param bool $instructor To include instructors or not
     * @param bool $fullAccess To include full access graders or not
     * @param bool $limitedAccess To include limited access graders or not
     * @param bool $student To include students or not
     * @param bool $faculty to include faculty level users or not
     * @return array - array of userids active in the specified semester
     */
    public function getActiveUserIds(bool $instructor, bool $fullAccess, bool $limitedAccess, bool $student, bool $faculty, ?string $term = null, ?string $course = null): array {
        $args = ['-1'];
        $extra_join = '';
        $extra_where = '';
        if ($instructor) {
            $args[] = '1';
        }
        if ($fullAccess) {
            $args[] = '2';
        }
        if ($limitedAccess) {
            $args[] = '3';
        }
        if ($student) {
            $args[] = '4';
        }

        if ($faculty) {
            $extra_join = ' INNER JOIN users ON courses_users.user_id = users.user_id ';
            $extra_where = ' OR users.user_access_level <= 2';
        }
        $on_phrase = ' ON courses_users.term = courses.term AND courses_users.course = courses.course ';
        $parameters = $args;
        if ($term !== null && $course !== null) {
            $parameters = [$term, $course];
            $parameters = array_merge($parameters, $args);
            $on_phrase = ' ON courses_users.term = ? AND courses_users.course = ? ';
        }
        $result_rows = [];
        $this->submitty_db->query(
            "SELECT DISTINCT courses_users.user_id as user_id
                FROM courses_users INNER JOIN courses
                " . $on_phrase . $extra_join . "
                WHERE courses.status = 1 AND user_group IN " . $this->createParameterList(count($args)) . $extra_where,
            $parameters
        );
        return array_map(
            function ($row) {
                return $row['user_id'];
            },
            $this->submitty_db->rows()
        );
    }

    /**
     * Count number of each group of faculty, instructors in active course, full access graders in active course, limited access graders in active course, students in active course
     * @return array
     */
    public function countActiveUsersByGroup(): array {
        $this->submitty_db->query(
            "WITH A as (SELECT DISTINCT ON(user_id) user_id, user_group FROM courses_users JOIN courses
            on courses.course = courses_users.course
            and courses.term = courses_users.term
            WHERE courses.status = 1
            ORDER BY user_id, courses_users.user_group ASC)
            SELECT A.user_group, COUNT(A.user_group) FROM A GROUP BY A.user_group"
        );
        $result = [];
        foreach ($this->submitty_db->rows() as $row) {
            if ($row['user_group'] == 1) {
                $result['instructor'] = $row['count'];
            }
            elseif ($row['user_group'] == 2) {
                $result['full_access'] = $row['count'];
            }
            elseif ($row['user_group'] == 3) {
                $result['limited_access'] = $row['count'];
            }
            else {
                $result['student'] = $row['count'];
            }
        }
        $this->submitty_db->query(
            "SELECT COUNT(user_access_level) FROM users WHERE user_access_level <= 2"
        );
        $result['faculty'] = $this->submitty_db->row()['count'];
        return $result;
    }

    /**
     * Gets whether a gradeable exists already
     *
     * @param string $g_id the gradeable id to check for
     *
     * @return bool
     */
    public function existsGradeable($g_id) {
        $this->course_db->query('SELECT EXISTS (SELECT g_id FROM gradeable WHERE g_id= ?)', [$g_id]);
        return $this->course_db->row()['exists'] ?? false; // This shouldn't happen, but let's assume false
    }

    public function getGradeableVersionHasAutogradingResults($g_id, $version, $user_id, $team_id) {
        $query = "SELECT * FROM electronic_gradeable_data WHERE g_id=? AND g_version=? AND ";
        if ($user_id === null) {
            $query .= "team_id=?";
            $params = [$g_id, $version, $team_id];
        }
        else {
            $query .= "user_id=?";
            $params = [$g_id, $version, $user_id];
        }
        $this->course_db->query($query, $params);
        return count($this->course_db->rows()) > 0 && $this->course_db->row()['autograding_complete'] === true;
    }

    /**
     * Create a string that consists placeholder parameters (?) enclosed in parentheses.
     * These placeholders will then be used for parameter binding when executing the SQL query.
     * When $len is zero, this function will return '(NULL)' so that it won't break SQL syntax.
     *
     * @param integer $len the number of placeholder parameters.
     */
    protected function createParameterList(int $len): string {
        if ($len < 1) {
            return '(NULL)';
        }
        return '(' . implode(',', array_fill(0, $len, '?')) . ')';
    }

    public function componentItempoolInfo($g_id, $component_id) {
        $this->course_db->query(
            "SELECT gc_is_itempool_linked as is_linked, gc_itempool as name FROM gradeable_component WHERE g_id = ? AND gc_id = ?",
            [$g_id, $component_id]
        );
        return $this->course_db->row();
    }

    public function addSolutionForComponentId($g_id, $component_id, $itempool_item, $solution_text, $author_id) {
        $this->course_db->query(
            "INSERT INTO solution_ta_notes (g_id, component_id, itempool_item, solution_notes, author, edited_at) VALUES (?, ?, ?, ?, ?, current_timestamp)",
            [$g_id, $component_id, $itempool_item, $solution_text, $author_id]
        );
    }

    public function getSolutionForComponentItempoolItem($g_id, $component_id, $itempool_item) {
        $this->course_db->query(
            "SELECT * FROM solution_ta_notes WHERE g_id = ? AND component_id = ? AND itempool_item = ? ORDER BY edited_at DESC LIMIT 1",
            [$g_id, $component_id, $itempool_item]
        );
        return $this->course_db->row();
    }

    public function getSolutionForComponentId($g_id, $component_id) {
        // check if the itempool is linked
        $itempool = $this->componentItempoolInfo($g_id, $component_id);
        $itempool_items = [
            ["itempool_item" => ""],
        ];
        $result_rows = [];

        if ($itempool['is_linked']) {
            $this->course_db->query(
                "SELECT DISTINCT itempool_item FROM solution_ta_notes WHERE g_id = ? AND component_id = ?",
                [$g_id, $component_id]
            );
            $itempool_items = $this->course_db->rows();
        }

        foreach ($itempool_items as $itempool_item) {
            $values = $this->getSolutionForComponentItempoolItem($g_id, $component_id, $itempool_item['itempool_item']);
            $result_rows[] = array_merge(['itempool_name' => $itempool['name']], $values);
        }

        return $result_rows;
    }

    public function getSolutionForAllComponentIds($g_id) {
        $solution_array = [];
        $this->course_db->query("SELECT DISTINCT component_id FROM solution_ta_notes WHERE g_id=?", [$g_id]);
        $component_ids = $this->course_db->rows();
        foreach ($component_ids as $row) {
            $solution_array[$row['component_id']] = $this->getSolutionForComponentId($g_id, $row['component_id']);
        }
        return $solution_array;
    }

    // Moved from class LateDaysCalculation on port from TAGrading server.  May want to incorporate late day information into gradeable object rather than having a separate query
    public function getLateDayUpdates($user_id) {
        if ($user_id != null) {
            $query = "SELECT * FROM late_days WHERE user_id";
            if (is_array($user_id)) {
                $query .= ' IN ' . $this->createParameterList(count($user_id));
                $params = $user_id;
            }
            else {
                $query .= '=?';
                $params = [$user_id];
            }
            $query .= ' ORDER BY since_timestamp';
            $this->course_db->query($query, $params);
        }
        else {
            $this->course_db->query("SELECT * FROM late_days");
        }
        // Parse the date-times
        return array_map(
            function ($arr) {
                $arr['since_timestamp'] = DateUtils::parseDateTime($arr['since_timestamp'], $this->core->getConfig()->getTimezone());
                return $arr;
            },
            $this->course_db->rows()
        );
    }

    public function getLateDayInformation($user_id) {
        $params = [300];
        $query = "SELECT
                      submissions.*
                      , coalesce(late_day_exceptions, 0) extensions
                      , greatest(0, ceil((extract(EPOCH FROM(coalesce(submission_time, eg_submission_due_date) - eg_submission_due_date)) - (?*60))/86400):: integer) as days_late
                    FROM
                      (
                        SELECT
                        base.g_id
                        , g_title
                        , base.assignment_allowed
                        , base.user_id
                        , eg_submission_due_date
                        , coalesce(active_version, -1) as active_version
                        , submission_time
                      FROM
                      (
                        --Begin BASE--
                        SELECT
                          g.g_id,
                          u.user_id,
                          g.g_title,
                          eg.eg_submission_due_date,
                          eg.eg_late_days AS assignment_allowed
                        FROM
                          users u
                          , gradeable g
                          , electronic_gradeable eg
                        WHERE
                          g.g_id = eg.g_id
                        --End Base--
                      ) as base
                    LEFT JOIN
                    (
                        --Begin Details--
                        SELECT
                          egv.g_id
                          , egv.user_id
                          , active_version
                          , g_version
                          , submission_time
                        FROM
                          electronic_gradeable_version egv INNER JOIN electronic_gradeable_data egd
                        ON
                          egv.active_version = egd.g_version
                          AND egv.g_id = egd.g_id
                          AND egv.user_id = egd.user_id
                        GROUP BY  egv.g_id,egv.user_id, active_version, g_version, submission_time
                        --End Details--
                    ) as details
                    ON
                      base.user_id = details.user_id
                      AND base.g_id = details.g_id
                    )
                      AS submissions
                      FULL OUTER JOIN
                        late_day_exceptions AS lde
                      ON submissions.g_id = lde.g_id
                      AND submissions.user_id = lde.user_id";
        if ($user_id !== null) {
            if (is_array($user_id)) {
                $query .= " WHERE submissions.user_id IN (" . implode(", ", array_fill(0, count($user_id), '?')) . ")";
                $params = array_merge($params, $user_id);
            }
            else {
                $query .= " WHERE submissions.user_id=?";
                $params[] = $user_id;
            }
        }
        $this->course_db->query($query, $params);
        return $this->course_db->rows();
    }

    /**
     * Get all of the late day cache ordered by date and g_id
     * @return array $return = [
     *  $user_id' => [
     *      $event_title => [
     *          $cache_row => [
     *              'g_id' => string,
     *              'user_id' => string,
     *              'team_id' => string,
     *              'late_days_allowed' => int,
     *              'late_day_date' => DateTime,
     *              'submission_days_late' => int,
     *              'late_day_exceptions' => int,
     *              'reason_for_exception' => string,
     *              'late_days_remaining' => int,
     *              'late_day_status' => int,
     *              'late_days_change' => int
     *          ]
     *      ]
     *  ]
     */
    public function getLateDayCache(): array {
        $query = "SELECT * FROM
                    late_day_cache AS ldc
                    LEFT JOIN gradeable g ON g.g_id=ldc.g_id
                  ORDER BY late_day_date NULLS LAST, g.g_id NULLS FIRST";
        $this->course_db->query($query);
        $return = [];

        // key: user, value: array of all cache available
        foreach ($this->course_db->rows() as $row) {
            $user_id = $row['user_id'] ?? $row['team_id'];
            // title = g_title or event date
            $title = $row['g_title'] !== null ? $row['g_title'] : explode(" ", $row['late_day_date'])[0];
            $return[$user_id][$title] = $row;
        }
        return $return;
    }

    /**
     * Generate and update the late day cache for all of the students in the course
     */
    public function generateLateDayCacheForUsers(): void {
        $default_late_days = $this->core->getConfig()->getDefaultStudentLateDays();
        $params = [$default_late_days];
        $query = "INSERT INTO late_day_cache 
                    (SELECT (cache_row).*
                         FROM 
                        (SELECT
                            public.calculate_remaining_cache_for_user(user_id::text, ?) as cache_row
                        FROM users
                        ) calculated_cache
                    )
                    ON CONFLICT DO NOTHING";
        $this->course_db->query($query, $params);
    }

    public function addLateDayCacheForGradeable(string $user_id, LateDayInfo $late_day_info): void {
        $params = [$user_id];
        $params[] = $late_day_info->getId();
        $params[] = $late_day_info->getEventTitle();
        $params[] = $late_day_info->getLateDayEventTime();
        $params[] = $late_day_info->getAssignmentAllowedLateDays();
        $params[] = $late_day_info->getDaysLate();
        $params[] = $late_day_info->getLateDayException();
        $params[] = $late_day_info->getLateDaysRemaining();
        $params[] = $late_day_info->getStatus();
        $params[] = $late_day_info->getLateDaysChange();
        $params[] = $late_day_info->getReasonForException();

        $user_or_team = $late_day_info->getGradedGradeable()->getGradeable()->isTeamAssignment() ? 'team_id' : 'user_id';
        $query = "INSERT INTO late_day_cache
                    (" . $user_or_team . ", g_id, late_day_date, late_days_allowed, submission_days_late, 
                    late_day_exceptions, late_days_remaining, late_day_status, late_days_change, reason_for_exception) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT DO NOTHING";
        $this->course_db->query($query, $params);
    }

    public function addLateDayCacheForLateDayUpdate(string $user_id, LateDayInfo $late_day_info): void {
        $params = [$user_id];
        $params[] = $late_day_info->getLateDayEventTime();
        $params[] = $late_day_info->getLateDaysRemaining();
        $params[] = $late_day_info->getLateDaysChange();

        $query = "INSERT INTO late_day_cache
                    (user_id, late_day_date, late_days_remaining, late_days_change) 
                    VALUES (?, ?, ?, ?)
                    ON CONFLICT DO NOTHING";
        $this->course_db->query($query, $params);
    }

    public function addLateDayCacheForUser(User $user, LateDayInfo $late_day_info): void {
        if ($late_day_info->isLateDayUpdate()) {
            $this->addLateDayCacheForLateDayUpdate($user->getId(), $late_day_info);
        }
        else {
            $this->addLateDayCacheForGradeable($user->getId(), $late_day_info);
        }
    }

    /**
     * Get the late day cache for a specific user
     * @param string $user_id
     * @return array $return = [
     *      $id => [
     *          'g_id' => string,
     *          'user_id' => string,
     *          'team_id' => string,
     *          'late_days_allowed' => int,
     *          'late_day_date' => DateTime,
     *          'submission_days_late' => int,
     *          'late_day_exceptions' => int,
     *          'reason_for_exception' => string,
     *          'late_days_remaining' => int,
     *          'late_day_status' => int,
     *          'late_days_change' => int
     *      ]
     * ]
     */
    public function getLateDayCacheForUser(string $user_id): array {
        $params = [$user_id];
        $query = "SELECT * FROM late_day_cache
                    WHERE user_id=?
                    ORDER BY late_day_date NULLS LAST, g_id NULLS FIRST";
        $this->course_db->query($query, $params);

        $index = 1;
        $late_day_events = [];
        foreach ($this->course_db->rows() as $row) {
            // Change string date to DateTime object
            $row['late_day_date'] = new \DateTime($row['late_day_date']);

            if (isset($row['g_id'])) { // Gradeable late day event
                $late_day_events[$row['g_id']] = $row;
            }
            else { // Late day update event
                $late_day_events[$index] = $row;
                $index++;
            }
        }
        return $late_day_events;
    }

    /**
     * Get the late day information for a specific user (graded gradeable information)
     * @param string $user_id
     * @param string $g_id
     * @return null|array $return = [
     *      'g_id' => string,
     *      'user_id' => string,
     *      'team_id' => string,
     *      'late_days_allowed' => int,
     *      'late_day_date' => DateTime,
     *      'submission_days_late' => int,
     *      'late_day_exceptions' => int,
     *      'late_days_remaining' => int,
     *      'late_day_status' => int,
     *      'late_days_change' => int,
     *      'reason_for_exception' => string
     * ]
     */
    public function getLateDayCacheForUserGradeable(string $user_id, string $g_id): ?array {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $full_query = "
                SELECT * FROM late_day_cache WHERE g_id=?
            ";
            $this->course_db->query($full_query, [$g_id]);
            $res = $this->course_db->rows();
            foreach ($res as $row) {
                $cache[$row["user_id"]] = $row;
            }
        }

        $row = null;
        // If cache doesn't exist, generate it and query again
        if (!array_key_exists($user_id, $cache) || empty($cache[$user_id])) {
            $params = [$user_id, $g_id];
            $query = "SELECT * FROM late_day_cache
                    WHERE user_id=?
                    AND g_id=?";
            $this->generateLateDayCacheForUser($user_id);
            $this->course_db->query($query, $params);
            $row = $this->course_db->row();
            $cache[$user_id] = $row;
        }
        $row = $cache[$user_id];
        // If cache still doesn't exist, the gradeable is not associated with
        // LateDays OR there has been a computation error
        if (empty($row)) {
            return null;
        }

        return $row;
    }

    /**
     * Get the Late Day Info for each user associated with a user and gradeable
     * @param User $user
     * @param GradedGradeable $graded_gradeable
     * @return LateDayInfo|null
     */
    public function getLateDayInfoForUserGradeable($user, $graded_gradeable) {

        if ($user == null) {
            return null;
        }

        $cache = $this->getLateDayCacheForUserGradeable($user->getId(), $graded_gradeable->getGradeableId());
        $cache['graded_gradeable'] = $graded_gradeable;
        $ldi = null;

        if ($cache !== null) {
            $ldi = new LateDayInfo($this->core, $user, $cache);
        }

        return $ldi;
    }

    /**
     * Get the Late Day Info for each user associated with a submitter and gradeable
     * @param Submitter $submitter
     * @param GradedGradeable $graded_gradeable
     * @return LateDayInfo|array<string,LateDayInfo>|null
     */
    public function getLateDayInfoForSubmitterGradeable($submitter, $graded_gradeable) {
        // Collect Late Day Info for each user associated with the submitter
        if ($submitter->isTeam()) {
            $late_day_info = [];
            foreach ($submitter->getTeam()->getMemberUsers() as $member) {
                if ($member === null) {
                    return null;
                }
                $late_day_info[$member->getId()] = $this->getLateDayInfoForUserGradeable($member, $graded_gradeable);
            }
            return $late_day_info;
        }
        else {
            return $this->getLateDayInfoForUserGradeable($submitter->getUser(), $graded_gradeable);
        }
    }

    /**
     * Generate and update the late day cache for a student
     * @param string $user_id
     */
    public function generateLateDayCacheForUser(string $user_id): void {
        $default_late_days = $this->core->getConfig()->getDefaultStudentLateDays();
        $params = [$user_id, $default_late_days];

        $query = "INSERT INTO late_day_cache
                    SELECT * FROM calculate_remaining_cache_for_user(?::text, ?)
                    ON CONFLICT DO NOTHING";

        $this->course_db->query($query, $params);
    }

    /**
     * Remove all of the late day information cached
     */
    public function flushAllLateDayCache() {
        $query = "DELETE FROM late_day_cache";
        $this->course_db->query($query);
    }

    public function getLateDayUpdateTimestamps() {
        $query = "SELECT DISTINCT since_timestamp FROM late_days ORDER BY since_timestamp";
        $this->course_db->query($query);
        $return = [];
        foreach ($this->course_db->rows() as $row) {
            $return[] = new \DateTime($row['since_timestamp']);
        }
        return $return;
    }

    public function getLastLateDayUpdatesForUsers() {
        $query = "SELECT user_id, max(since_timestamp) FROM late_days GROUP BY user_id";
        $this->course_db->query($query);
        $return = [];
    }

    /**
     * Get the latest valid versrion for a gradeable (good or late status)
     * @param GradedGradeable $gg
     * @param string $submitter_id
     */
    public function getLatestValidGradeableVersion(GradedGradeable $gg, string $submitter_id, int $late_days_remaining): int {
        $params = [$gg->getGradeableId(), $submitter_id, $late_days_remaining];
        $query = "SELECT
                    egd.g_version
                FROM electronic_gradeable eg
                JOIN electronic_gradeable_data egd
                ON eg.g_id=egd.g_id
				LEFT JOIN teams t
					ON t.team_id=egd.team_id
				LEFT JOIN users u
					ON u.user_id=t.user_id
					OR u.user_id=egd.user_id
                LEFT JOIN late_day_exceptions lde
                ON lde.g_id=eg.g_id AND lde.user_id=u.user_id
                WHERE eg.g_id=?
                AND (CASE WHEN eg.eg_team_assignment IS TRUE THEN egd.team_id
                    ELSE egd.user_id
                    END)=?
                AND (
                    SELECT late_day_status
                    FROM get_late_day_info_from_previous(
                        calculate_submission_days_late(egd.submission_time, eg.eg_submission_due_date),
                        eg.eg_late_days,
                        COALESCE(lde.late_day_exceptions, 0),
                        ?
                    )
                ) != 3";
        $this->course_db->query($query, $params);


        $version = 0;
// Get gradeable version where all submitters have a good status
        if ($gg->getGradeable()->isTeamAssignment()) {
            // Check if all members have a valid instance
            if ($this->course_db->getRowCount() === count($gg->getSubmitter()->getTeam()->getMemberUsers())) {
                foreach ($this->course_db->rows() as $row) {
                    // minimum between the current $version and the current row's g_version, ensuring that $version will hold the minimum value after the loop is completed.
                    $version = ($version === 0) ? $row['g_version'] : min($version, $row['g_version']);
                }
            }
        }
        else {
            $version = $this->course_db->row()['g_version'] ?? 0;
        }

        return $version;
    }

    /**
     * Fetches all students from the given registration sections, $sections.
     *
     * @param  string[] $sections
     * @param  string $orderBy
     * @return User[]
     */
    public function getUsersByRegistrationSections($sections, $orderBy = "registration_section") {
        $return = [];
        if (count($sections) > 0) {
            $orderBy = str_replace(
                "registration_section",
                "SUBSTRING(registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(registration_section, '[0-9]+')::INT, -1), SUBSTRING(registration_section, '[^0-9]*$')",
                $orderBy
            );
            $values = $this->createParameterList(count($sections));
            $this->course_db->query("SELECT * FROM users AS u WHERE registration_section IN {$values} ORDER BY {$orderBy}", $sections);
            foreach ($this->course_db->rows() as $row) {
                $return[] = new User($this->core, $row);
            }
        }
        return $return;
    }

    public function getUsersInNullSection($orderBy = "user_id") {
        $return = [];
        $this->course_db->query("SELECT * FROM users AS u WHERE registration_section IS NULL ORDER BY {$orderBy}");
        foreach ($this->course_db->rows() as $row) {
            $return[] = new User($this->core, $row);
        }
        return $return;
    }

    public function getTotalUserCountByGradingSections($sections, $section_key) {
        $return = [];
        $params = [];
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE ({$section_key} IN " . $this->createParameterList(count($sections)) . ") IS NOT FALSE";
            $params = $sections;
        }
        if ($section_key === 'registration_section') {
            $orderby = "SUBSTRING({$section_key}, '^[^0-9]*'), COALESCE(SUBSTRING({$section_key}, '[0-9]+')::INT, -1), SUBSTRING({$section_key}, '[^0-9]*$')";
        }
        else {
            $orderby = $section_key;
        }
        $this->course_db->query(
            "
SELECT count(*) as cnt, {$section_key}
FROM users
{$where}
GROUP BY {$section_key}
ORDER BY {$orderby}",
            $params
        );
        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
            $return[$row[$section_key]] = intval($row['cnt']);
        }
        return $return;
    }

    /**
     * Gets the number of bad (late) user submissions associated with this gradeable.
     *
     * @param  string $g_id gradeable id we are looking up
     * @param  array<int> $sections an array holding sections of the given gradeable
     * @param  string $section_key key we are basing grading sections off of
     * @return array<int,int> with a key representing a section and value representing the number of bad submissions
     */
    public function getBadUserSubmissionsByGradingSection($g_id, $sections, $section_key) {
        $return = [];
        $params = [$g_id];
        $where = "";
        if (count($sections) > 0) {
            // Expand out where clause
            $sections_keys = array_values($sections);
            $placeholders = $this->createParameterList(count($sections_keys));
            $where = "WHERE ({$section_key} IN {$placeholders}) IS NOT FALSE";
            $params = array_merge($params, $sections_keys);
        }
        if ($section_key === 'registration_section') {
            $orderby = "SUBSTRING({$section_key}, '^[^0-9]*'), COALESCE(SUBSTRING({$section_key}, '[0-9]+')::INT, -1), SUBSTRING({$section_key}, '[^0-9]*$')";
        }
        else {
            $orderby = $section_key;
        }
        $this->course_db->query(
            "
            SELECT count(*) as cnt, {$section_key}
            FROM users
            INNER JOIN electronic_gradeable_version
            ON
            users.user_id = electronic_gradeable_version.user_id
            AND electronic_gradeable_version.active_version>0
            AND electronic_gradeable_version.g_id=?
            INNER JOIN electronic_gradeable
            ON 
            electronic_gradeable.g_id=electronic_gradeable_version.g_id
            AND electronic_gradeable.eg_submission_due_date IS NOT NULL
            INNER JOIN late_day_cache AS ldc
            ON ldc.g_id=electronic_gradeable.g_id
            AND ldc.user_id=users.user_id
            AND ldc.submission_days_late>ldc.late_day_exceptions 
            And ldc.late_days_change =0
            {$where}
            GROUP BY {$section_key}
            ORDER BY {$orderby}",
            $params
        );
        //electronic_gradeable.eg_submission_due_date - electronic_gradeable_data.submission_time > '1 SECOND'
        //var_dump($this->course_db->rows());
        foreach ($sections as $val) {
            $return[$val] = 0;
        }

        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
            $return[$row[$section_key]] = intval($row['cnt']);
        }

        return $return;
    }

    public function getTotalSubmittedUserCountByGradingSections($g_id, $sections, $section_key) {
        $return = [];
        $params = [$g_id];
        $where = "";
        if (count($sections) > 0) {
            // Expand out where clause
            $sections_keys = array_values($sections);
            $placeholders = $this->createParameterList(count($sections_keys));
            $where = "WHERE ({$section_key} IN {$placeholders}) IS NOT FALSE";
            $params = array_merge($params, $sections_keys);
        }
        if ($section_key === 'registration_section') {
            $orderby = "SUBSTRING({$section_key}, '^[^0-9]*'), COALESCE(SUBSTRING({$section_key}, '[0-9]+')::INT, -1), SUBSTRING({$section_key}, '[^0-9]*$')";
        }
        else {
            $orderby = $section_key;
        }
        $this->course_db->query(
            "
SELECT count(*) as cnt, {$section_key}
FROM users
INNER JOIN electronic_gradeable_version
ON
users.user_id = electronic_gradeable_version.user_id
AND electronic_gradeable_version.active_version>0
AND electronic_gradeable_version.g_id=?
{$where}
GROUP BY {$section_key}
ORDER BY {$orderby}",
            $params
        );

        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
            $return[$row[$section_key]] = intval($row['cnt']);
        }

        return $return;
    }

    public function getTotalSubmittedTeamCountByGradingSections($g_id, $sections, $section_key) {
        $return = [];
        $params = [$g_id];
        $where = "";
        if (count($sections) > 0) {
            // Expand out where clause
            $sections_keys = array_values($sections);
            $placeholders = $this->createParameterList(count($sections_keys));
            $where = "WHERE ({$section_key} IN {$placeholders}) IS NOT FALSE";
            $params = array_merge($params, $sections_keys);
        }
        if ($section_key === 'registration_section') {
            $orderby = "SUBSTRING({$section_key}, '^[^0-9]*'), COALESCE(SUBSTRING({$section_key}, '[0-9]+')::INT, -1), SUBSTRING({$section_key}, '[^0-9]*$')";
        }
        else {
            $orderby = $section_key;
        }
        $this->course_db->query(
            "
            SELECT COUNT(*) as cnt, {$section_key}
            FROM gradeable_teams
            INNER JOIN electronic_gradeable_version
                    ON gradeable_teams.team_id = electronic_gradeable_version.team_id
                   AND electronic_gradeable_version.active_version>0
                   AND electronic_gradeable_version.g_id=?
            {$where}
            GROUP BY {$section_key}
            ORDER BY {$orderby}
        ",
            $params
        );

        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }

            $return[$row[$section_key]] = intval($row['cnt']);
        }

        return $return;
    }

    /**
     * Get an array of Teams for a Gradeable matching the given registration sections
     *
     * @param  string $g_id
     * @param  array  $sections
     * @param  string $orderBy
     * @return Team[]
     */
    public function getTeamsByGradeableAndRegistrationSections($g_id, $sections, $orderBy = "registration_section") {
        $return = [];
        if (count($sections) > 0) {
            $orderBy = str_replace("gt.registration_section", "SUBSTRING(gt.registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(gt.registration_section, '[0-9]+')::INT, -1), SUBSTRING(gt.registration_section, '[^0-9]*$')", $orderBy);
            $placeholders = implode(",", array_fill(0, count($sections), "?"));
            $params = [$g_id];
            $params = array_merge($params, $sections);

            $this->course_db->query(
                "
                SELECT gt.team_id, gt.registration_section, gt.rotating_section, gt.team_name, COALESCE(NULLIF(jsonb_agg(u)::text, '[null]'), '[]')::jsonb AS users
                FROM gradeable_teams gt
                  LEFT JOIN
                    (SELECT t.team_id, t.state, u.*
                     FROM teams t
                       JOIN users u ON t.user_id = u.user_id
                    ) AS u ON gt.team_id = u.team_id
                WHERE gt.g_id = ?
                  AND gt.registration_section IN ($placeholders)
                GROUP BY gt.team_id
                ORDER BY {$orderBy}
            ",
                $params
            );
            foreach ($this->course_db->rows() as $row) {
                $row["users"] = json_decode($row["users"], true);
                $return[] = new Team($this->core, $row);
            }
        }
        return $return;
    }

    /**
     * Get an array of Teams for a Gradeable matching the given rotating sections
     *
     * @param  string $g_id
     * @param  array  $sections
     * @param  string $orderBy
     * @return Team[]
     */
    public function getTeamsByGradeableAndRotatingSections($g_id, $sections, $orderBy = "rotating_section") {
        $return = [];
        if (count($sections) > 0) {
            $placeholders = implode(",", array_fill(0, count($sections), "?"));
            $params = [$g_id];
            $params = array_merge($params, $sections);

            $this->course_db->query(
                "
                SELECT gt.team_id, gt.registration_section, gt.rotating_section, gt.team_name, COALESCE(NULLIF(jsonb_agg(u)::text, '[null]'), '[]')::jsonb AS users
                FROM gradeable_teams gt
                  LEFT JOIN
                    (SELECT t.team_id, t.state, u.*
                     FROM teams t
                       JOIN users u ON t.user_id = u.user_id
                    ) AS u ON gt.team_id = u.team_id
                WHERE gt.g_id = ?
                  AND gt.rotating_section IN ($placeholders)
                GROUP BY gt.team_id
                ORDER BY {$orderBy}
            ",
                $params
            );
            foreach ($this->course_db->rows() as $row) {
                $row["users"] = json_decode($row["users"], true);
                $return[] = new Team($this->core, $row);
            }
        }
        return $return;
    }

    public function getTotalComponentCount($g_id) {
        $this->course_db->query("SELECT count(*) AS cnt FROM gradeable_component WHERE g_id=?", [$g_id]);
        return intval($this->course_db->row()['cnt']);
    }

    /**
     * Get the number of ta components
     *
     * @param  string $g_id gradeable id we are looking up
     * @return int the number of ta components
     */
    public function getTaComponentCount(string $g_id): int {
        $this->course_db->query("SELECT count(*) AS cnt FROM gradeable_component WHERE g_id=? AND gc_is_peer=?", [$g_id, $this->course_db->convertBoolean(false)]);
        return intval($this->course_db->row()['cnt']);
    }

    /**
     * First half of query will count all user / team submissions that have been graded
     * Second half of query will count all user submissions that have been overriden
     * These counts are added and returned.
     */
    public function getGradedComponentsCountByGradingSections($g_id, $sections, $section_key, $is_team) {
        $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        if ($is_team) {
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
        }
        $return = [];
        $params = [$g_id];
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE active_version > 0 AND ({$section_key} IN " . $this->createParameterList(count($sections)) . ") IS NOT FALSE";
            $params = array_merge($params, $sections);
        }
        // Because go.team_id does not exist right now, only perform override calculations for non-team assignments
        $go_create = "";
        $go_check = "";
        $go_select = "";
        if (!$is_team) {
            $go_create = "LEFT JOIN grade_override AS go ON gd.g_id = go.g_id AND gd.gd_{$user_or_team_id} = go.{$user_or_team_id}";
            $go_check = "AND go.g_id IS NULL AND go.user_id IS NULL";
            $go_select = "UNION ALL
                        SELECT {$users_or_teams}.{$section_key}, count({$users_or_teams}.*) * component_count.num AS cnt
                        FROM grade_override AS go
                                INNER JOIN {$users_or_teams} ON go.{$user_or_team_id} = {$users_or_teams}.{$user_or_team_id} AND go.g_id=?
                                INNER JOIN (
                                        SELECT gc.g_id, count(*) AS num
                                        FROM gradeable_component AS gc
                                        GROUP BY gc.g_id
                                    ) AS component_count ON go.g_id = component_count.g_id
                        GROUP BY {$users_or_teams}.{$section_key}, component_count.num";
            array_push($params, $g_id);
        }
        $this->course_db->query(
            "
SELECT merged_data.{$section_key}, SUM(cnt) as cnt
FROM (
    SELECT {$u_or_t}.{$section_key}, count({$u_or_t}.*) as cnt
    FROM {$users_or_teams} AS {$u_or_t}
        INNER JOIN (
                SELECT *
                FROM gradeable_data AS gd
                    INNER JOIN (SELECT g_id, $user_or_team_id, max(active_version) AS active_version
                                FROM electronic_gradeable_version
                                GROUP BY g_id, $user_or_team_id) AS egd
                                ON egd.g_id = gd.g_id AND egd.{$user_or_team_id} = gd.gd_{$user_or_team_id}
                    {$go_create}
                    LEFT JOIN (
                        gradeable_component_data AS gcd
                        INNER JOIN gradeable_component AS gc
                        ON gc.gc_id = gcd.gc_id AND gc.gc_is_peer = {$this->course_db->convertBoolean(false)}
                    ) AS gcd ON gcd.gd_id = gd.gd_id
                WHERE gcd.g_id=? {$go_check}
        ) AS gd ON {$u_or_t}.{$user_or_team_id} = gd.gd_{$user_or_team_id}
    {$where}
    GROUP BY {$u_or_t}.{$section_key}
    {$go_select}
    ) AS merged_data
GROUP BY merged_data.{$section_key}
ORDER BY merged_data.{$section_key}
    ",
            $params
        );
        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
            $return[$row[$section_key]] = intval($row['cnt']);
        }

        if (!array_key_exists('NULL', $return)) {
            $return['NULL'] = 0;
        }
        return $return;
    }


    /**
     * Returns an array of Verified components for each Section
     *
     * @param  array<int>  $sections
     * @return array<int|string,int>
     */
    public function getVerifiedComponentsCountByGradingSections(string $g_id, array $sections, string $section_key, bool $is_team): array {
        $unit = "users";
        $id = "user_id";
        if ($is_team) {
            $unit = "gradeable_teams";
            $id = "team_id";
        }

        if (! in_array($section_key, ["registration_section", "rotating_section"], true)) {
            return [];
        }

        $this->course_db->query(
            "
            SELECT 
                $unit.$section_key, COUNT($unit.$id) as verified_components_count
            FROM 
                gradeable_data as gd
                JOIN gradeable_component_data as gcd ON (gd.gd_id = gcd.gd_id)
                JOIN $unit ON (gd.gd_$id = $unit.$id)
            WHERE
                gd.g_id = ?
                AND CAST ($unit.$section_key AS TEXT) IN " . $this->createParameterList(count($sections))  . "
                AND gcd.gcd_verifier_id IS NOT NULL
            GROUP BY
                $unit.$section_key
            ;
            ",
            array_merge([$g_id], $sections)
        );
        $return = [];

        foreach ($this->course_db->rows() as $row) {
            $return[$row[$section_key]] = $row['verified_components_count'];
        }

        return $return;
    }

    /**
     * Gets the number of bad (late) graded components associated with this gradeable.
     *
     * @param  string $g_id gradeable id we are looking up
     * @param  array<int> $sections an array holding sections of the given gradeable
     * @param  string $section_key key we are basing grading sections off of
     * @param  boolean $is_team true if the gradeable is a team assignment
     * @return array<int,int> with a key representing a section and value representing the number of bad submissions
     */
    public function getBadGradedComponentsCountByGradingSections($g_id, $sections, $section_key, $is_team) {
        //getBadTeamSubmissionsByGradingSection
        //getBadUserSubmissionsByGradingSection
         $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        if ($is_team) {
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
        }
        $return = [];
        $params = [$g_id,$g_id,$g_id];
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE active_version > 0 AND ({$section_key} IN " . $this->createParameterList(count($sections)) . ") IS NOT FALSE";
            $params = array_merge($params, $sections);
        }
        $this->course_db->query(
            "
            SELECT {$u_or_t}.{$section_key}, count({$u_or_t}.*) as cnt
            FROM {$users_or_teams} AS {$u_or_t}
            INNER JOIN (
              SELECT *, gd.g_id FROM gradeable_data AS gd
              LEFT JOIN (
              gradeable_component_data AS gcd
              INNER JOIN gradeable_component AS gc ON gc.gc_id = gcd.gc_id AND gc.gc_is_peer = {$this->course_db->convertBoolean(false)}
              )AS gcd ON gcd.gd_id = gd.gd_id WHERE gcd.g_id=?
            ) AS gd ON {$u_or_t}.{$user_or_team_id} = gd.gd_{$user_or_team_id}
            INNER JOIN electronic_gradeable_version AS egv
            ON
            {$u_or_t}.{$user_or_team_id} = egv.{$user_or_team_id}
            AND egv.active_version>0
            AND egv.g_id=?
            INNER JOIN electronic_gradeable AS eg
            ON 
            eg.g_id=egv.g_id
            AND eg.eg_submission_due_date IS NOT NULL
            INNER JOIN (SELECT DISTINCT ldc.{$user_or_team_id}
            FROM late_day_cache AS ldc
            WHERE ldc.g_id=? 
            AND ldc.submission_days_late>ldc.late_day_exceptions 
            And ldc.late_days_change =0) AS ldc ON ldc.{$user_or_team_id}={$u_or_t}.{$user_or_team_id}
            {$where}
            GROUP BY {$u_or_t}.{$section_key}
            ORDER BY {$u_or_t}.{$section_key}",
            $params
        );
        foreach ($sections as $val) {
            $return[$val] = 0;
        }

        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
            $return[$row[$section_key]] = intval($row['cnt']);
        }

        if (!array_key_exists('NULL', $return)) {
            $return['NULL'] = 0;
        }

        return $return;
    }
    /**
     * Gets the number of bad (late) graded submissions associated with this gradeable.
     *
     * @param  string $g_id gradeable id we are looking up
     * @param  string $section_key key we are basing grading sections off of
     * @param  boolean $is_team true if the gradeable is a team assignment
     * @param  string $null_section to check if we look in the null section or not
     * @return int the number of bad submissions
     */
    private function getBadSubmissionsCount(string $g_id, string $section_key, bool $is_team, string $null_section): int {
        $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        $null_section_condition = "";
        if ($is_team) {
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
        }

        if ($null_section !== 'include') {
            $null_section_condition = "AND {$u_or_t}.{$section_key} IS NOT NULL";
        }
        $this->course_db->query(
            "SELECT COUNT(*) as cnt
            FROM gradeable_component AS gc, {$users_or_teams} AS {$u_or_t}
            WHERE gc.g_id=? {$null_section_condition}
            AND EXISTS (
                SELECT 1
                FROM late_day_cache AS ldc
                WHERE ldc.{$user_or_team_id} = {$u_or_t}.{$user_or_team_id}
                AND ldc.g_id=gc.g_id
                AND ldc.submission_days_late > ldc.late_day_exceptions
                AND ldc.late_days_change = 0
            )",
            [$g_id]
        );
        return $this->course_db->row()['cnt'];
    }

    public function getAverageComponentScores(string $g_id, string $section_key, bool $is_team, string $bad_submissions, string $null_section) {
        $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        $null_section_condition = "";
        $bad_submissions_condition = "";
        $params = [$g_id, $g_id, $g_id, $g_id];
        if ($is_team) {
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
        }

        // Check if we want to include null sections within the average
        if ($null_section !== 'include') {
            $null_section_condition = "AND {$u_or_t}.{$section_key} IS NOT NULL";
        }
        // Check if we want to include late (bad) submissions into the average
        if ($bad_submissions !== 'include' && $this->getBadSubmissionsCount($g_id, $section_key, $is_team, $null_section) > 0) {
            $bad_submissions_condition = "INNER JOIN(
                SELECT DISTINCT ldc.{$user_or_team_id}
                FROM late_day_cache AS ldc
                WHERE ldc.g_id=? AND ( submission_days_late = 0 OR ldc.late_days_change != 0 
              ) )AS ldc ON ldc.{$user_or_team_id}={$u_or_t}.{$user_or_team_id}";
            $params[] = $g_id;
        }

        $return = [];
        $this->course_db->query("
SELECT comp.gc_id, gc_title, gc_max_value, gc_is_peer, gc_order, round(AVG(comp_score),2) AS avg_comp_score, round(stddev_pop(comp_score),2) AS std_dev, COUNT(*), rr.active_grade_inquiry_count FROM(
  SELECT gc_id, gc_title, gc_max_value, gc_is_peer, gc_order,
  CASE WHEN (gc_default + sum_points + gcd_score) > gc_upper_clamp THEN gc_upper_clamp
  WHEN (gc_default + sum_points + gcd_score) < gc_lower_clamp THEN gc_lower_clamp
  ELSE (gc_default + sum_points + gcd_score) END AS comp_score FROM(
    SELECT gcd.gc_id, gc_title, gc_max_value, gc_is_peer, gc_order, gc_lower_clamp, gc_default, gc_upper_clamp,
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
      WHERE {$u_or_t}.{$user_or_team_id} IS NOT NULL
    ) AS {$u_or_t} ON gd.gd_{$user_or_team_id}={$u_or_t}.{$user_or_team_id}
    INNER JOIN(
      SELECT egv.{$user_or_team_id}, egv.active_version
      FROM electronic_gradeable_version AS egv
      WHERE egv.g_id=? AND egv.active_version>0
    ) AS egv ON egv.{$user_or_team_id}={$u_or_t}.{$user_or_team_id}
    {$bad_submissions_condition}
    WHERE g_id=? {$null_section_condition}
  )AS parts_of_comp
)AS comp
LEFT JOIN (
	SELECT COUNT(*) AS active_grade_inquiry_count, rr.gc_id
	FROM grade_inquiries AS rr
	WHERE rr.g_id=? AND rr.status=-1
	GROUP BY rr.gc_id
) AS rr ON rr.gc_id=comp.gc_id
GROUP BY comp.gc_id, gc_title, gc_max_value, gc_is_peer, gc_order, rr.active_grade_inquiry_count
ORDER BY gc_order
        ", $params);
        foreach ($this->course_db->rows() as $row) {
            $info = ['g_id' => $g_id, 'section_key' => $section_key, 'team' => $is_team];
            $return[] = new SimpleStat($this->core, array_merge($row, $info));
        }
        return $return;
    }

    public function getAverageGraderScores(string $g_id, int $gc_id, string $section_key, bool $is_team, string $bad_submissions, string $null_section) {
        $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        $null_section_condition = "";
        $bad_submissions_condition = "";
        $params = [$gc_id, $g_id, $g_id, $g_id];
        if ($is_team) {
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
        }
        if ($null_section !== 'include') {
            $null_section_condition = "AND {$u_or_t}.{$section_key} IS NOT NULL";
        }
        // Check if we want to include late (bad) submissions into the average
        if ($bad_submissions !== 'include' && $this->getBadSubmissionsCount($g_id, $section_key, $is_team, $null_section) > 0) {
            $bad_submissions_condition = "INNER JOIN(
                SELECT DISTINCT ldc.{$user_or_team_id}
                FROM late_day_cache AS ldc
                WHERE ldc.g_id=? AND ( submission_days_late = 0 OR ldc.late_days_change != 0 
              ) )AS ldc ON ldc.{$user_or_team_id}={$u_or_t}.{$user_or_team_id}";
            $params[] = $g_id;
        }
        $return = [];
        $this->course_db->query("
SELECT gcd_grader_id, gc_order, round(AVG(comp_score),2) AS avg_comp_score, round(stddev_pop(comp_score),2) AS std_dev, COUNT(*) FROM(
  SELECT gcd_grader_id, gc_order,
  CASE WHEN (gc_default + sum_points + gcd_score) > gc_upper_clamp THEN gc_upper_clamp
  WHEN (gc_default + sum_points + gcd_score) < gc_lower_clamp THEN gc_lower_clamp
  ELSE (gc_default + sum_points + gcd_score) END AS comp_score FROM(
    SELECT gcd_grader_id, gc_order, gc_lower_clamp, gc_default, gc_upper_clamp,
    CASE WHEN sum_points IS NULL THEN 0 ELSE sum_points END AS sum_points, gcd_score
    FROM gradeable_component_data AS gcd
    LEFT JOIN gradeable_component AS gc ON gcd.gc_id=? AND gcd.gc_id=gc.gc_id
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
      WHERE {$u_or_t}.{$user_or_team_id} IS NOT NULL
    ) AS {$u_or_t} ON gd.gd_{$user_or_team_id}={$u_or_t}.{$user_or_team_id}
    INNER JOIN(
      SELECT egv.{$user_or_team_id}, egv.active_version
      FROM electronic_gradeable_version AS egv
      WHERE egv.g_id=? AND egv.active_version>0
    ) AS egv ON egv.{$user_or_team_id}={$u_or_t}.{$user_or_team_id}
    {$bad_submissions_condition}
    WHERE g_id=? {$null_section_condition}
  )AS parts_of_comp
)AS comp
GROUP BY gcd_grader_id, gc_order
ORDER BY gc_order
        ", $params);

        foreach ($this->course_db->rows() as $row) {
            // add grader average
            $return = array_merge($return, [$row['gcd_grader_id'] => ['avg' => $row['avg_comp_score'], 'count' => $row['count'], 'std_dev' => $row['std_dev']]]);
        }
        //var_dump($return);
        return $return;
    }

    public function getAverageAutogradedScores(string $g_id, string $section_key, bool $is_team, string $bad_submissions, string $null_section) {

        $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        $bad_submissions_condition = '';
        $null_section_condition = '';
        $params = [$g_id];
        if ($is_team) {
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
        }
        if ($null_section !== 'include') {
            $null_section_condition = "AND {$u_or_t}.{$section_key} IS NOT NULL";
        }
        if ($bad_submissions !== 'include' && $this->getBadSubmissionsCount($g_id, $section_key, $is_team, $null_section) > 0) {
            $bad_submissions_condition = "INNER JOIN(
                SELECT DISTINCT ldc.{$user_or_team_id}
                FROM late_day_cache AS ldc
                WHERE ldc.g_id=? AND ( submission_days_late = 0 OR ldc.late_days_change != 0 
              ) )AS ldc ON ldc.{$user_or_team_id}={$u_or_t}.{$user_or_team_id}";
            $params[] = $g_id;
        }

        $this->course_db->query("
SELECT round((AVG(score)),2) AS avg_score, round(stddev_pop(score), 2) AS std_dev, 0 AS max, COUNT(*) FROM(
   SELECT * FROM (
      SELECT (egd.autograding_non_hidden_non_extra_credit + egd.autograding_non_hidden_extra_credit + egd.autograding_hidden_non_extra_credit + egd.autograding_hidden_extra_credit) AS score
      FROM electronic_gradeable_data AS egd
      INNER JOIN {$users_or_teams} AS {$u_or_t}
      ON {$u_or_t}.{$user_or_team_id} = egd.{$user_or_team_id}
      INNER JOIN (
         SELECT g_id, {$user_or_team_id}, active_version FROM electronic_gradeable_version AS egv
         WHERE active_version > 0
      ) AS egv
      ON egd.g_id=egv.g_id AND egd.{$user_or_team_id}=egv.{$user_or_team_id}
      {$bad_submissions_condition}
      WHERE egd.g_version=egv.active_version AND egd.g_id=? {$null_section_condition}
   )g
) as individual;
          ", $params);
        return ($this->course_db->getRowCount() > 0) ? new SimpleStat($this->core, $this->course_db->rows()[0]) : null;
    }

    public function getScoresForGradeable($g_id, $section_key, $is_team) {
        $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        if ($is_team) {
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
        }
        $this->course_db->query(
            "
SELECT COUNT(*) from gradeable_component where g_id=?
          ",
            [$g_id]
        );
        $count = $this->course_db->row()[0];
        $this->course_db->query(
            "
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
          )g
      ",
            [$g_id]
        );
        return new SimpleStat($this->core, $this->course_db->rows()[0]);
    }

    public function getAverageForGradeable(string $g_id, string $section_key, bool $is_team, string $override, string $bad_submissions, string $null_section) {

        $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        $exclude = '';
        $include = '';
        $null_section_condition = '';
        $bad_submissions_condition = '';
        if ($is_team) {
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
        }
        // Get count
        $this->course_db->query(
            "SELECT COUNT(*) as cnt from gradeable_component where g_id=?",
            [$g_id]
        );
        $count = $this->course_db->row()['cnt'];
        $params = [$g_id, $count];

        // Check if we want to exclude grade overridden gradeables
        if (!$is_team && $override === 'include') {
            $exclude = "AND NOT EXISTS (SELECT * FROM grade_override
                        WHERE u.user_id = grade_override.user_id
                        AND grade_override.g_id=gc.g_id)";
        }

        // Check if we want to include null sections within the average
        if ($null_section !== 'include') {
            $null_section_condition = "AND {$u_or_t}.{$section_key} IS NOT NULL";
        }

        // Check if we want to include late (bad) submissions into the average
        if ($bad_submissions !== 'include' && $this->getBadSubmissionsCount($g_id, $section_key, $is_team, $null_section) > 0) {
            $bad_submissions_condition = "INNER JOIN(
                SELECT DISTINCT ldc.{$user_or_team_id}
                FROM late_day_cache AS ldc
                WHERE ldc.g_id=? AND ( submission_days_late = 0 OR ldc.late_days_change != 0 
              ) )AS ldc ON ldc.{$user_or_team_id}={$u_or_t}.{$user_or_team_id}";
            $params = [$g_id,$g_id, $count];
        }

        // Check if we want to combine grade overridden marks within averages
        if (!$is_team && $override === 'include') {
            $include = " UNION SELECT gd.gd_id, marks::numeric AS g_score, marks::numeric AS max, COUNT(*) as count, 0 as autograding
                FROM grade_override
                INNER JOIN users as u ON u.user_id = grade_override.user_id
                AND u.user_id IS NOT NULL
                LEFT JOIN gradeable_data as gd ON u.user_id = gd.gd_user_id
                AND grade_override.g_id = gd.g_id
                WHERE grade_override.g_id=?
                GROUP BY gd.gd_id, marks";
            $params[] = $g_id;
        }

        $this->course_db->query(
            "
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
        {$bad_submissions_condition}
        WHERE gc.g_id=? {$null_section_condition}
        " . $exclude . "
      )AS parts_of_comp
    )AS comp
    GROUP BY gd_id, autograding
  )g WHERE count=?" . $include . ")AS individual",
            $params
        );
        if (count($this->course_db->rows()) == 0) {
            return;
        }
        return new SimpleStat($this->core, $this->course_db->rows()[0]);
    }

    public function getNumUsersWhoViewedGradeBySections($gradeable, $sections, string $null_section) {
        $table = $gradeable->isTeamAssignment() ? 'gradeable_teams' : 'users';
        $grade_type = $gradeable->isGradeByRegistration() ? 'registration' : 'rotating';
        $type = $gradeable->isTeamAssignment() ? 'team' : 'user';
        $null_section_condition = "";
        $params = [$gradeable->getId()];

        $sections_query = "";
        if (count($sections) > 0) {
            $sections_query = "{$grade_type}_section IN " . $this->createParameterList(count($sections));
            $params = array_merge($sections, $params);
        }
        if ($null_section === 'include') {
            $null_section_condition = " IS NOT FALSE";
        }

        $this->course_db->query(
            "
            SELECT COUNT(*) as cnt
            FROM gradeable_data AS gd
            INNER JOIN (
                SELECT u.{$type}_id, u.{$grade_type}_section FROM {$table} AS u
                WHERE u.{$sections_query} {$null_section_condition}
            ) AS u
            ON gd.gd_{$type}_id=u.{$type}_id
            WHERE gd.g_id = ? AND gd.gd_user_viewed_date IS NOT NULL
        ",
            $params
        );

        return intval($this->course_db->row()['cnt']);
    }

    /**
     * Finds the number of users who has a non NULL last_viewed_time for team assignments
     * NULL times represent unviewed, non-null represent the user has viewed the latest version already
     *
     * @param  Gradeable $gradeable
     * @param  array $sections
     * @return integer
     */
    public function getNumUsersWhoViewedTeamAssignmentBySection($gradeable, $sections) {
        $grade_type = $gradeable->isGradeByRegistration() ? 'registration' : 'rotating';

        $params = [$gradeable->getId()];

        $sections_query = "";
        if (count($sections) > 0) {
            $sections_query = "{$grade_type}_section IN " . $this->createParameterList(count($sections));
            $params = array_merge($sections, $params);
        }

        $this->course_db->query(
            "
            SELECT COUNT(*) as cnt
            FROM teams AS tm
            INNER JOIN (
                SELECT u.team_id, u.{$grade_type}_section FROM gradeable_teams AS u
                WHERE u.{$sections_query} and u.g_id = ?
            ) AS u
            ON tm.team_id=u.team_id
            WHERE tm.last_viewed_time IS NOT NULL
        ",
            $params
        );

        return intval($this->course_db->row()['cnt']);
    }

    /**
     * @param  string $gradeable_id
     * @param  string $team_id
     * @return integer
     */
    public function getActiveVersionForTeam($gradeable_id, $team_id) {
        $params = [$gradeable_id,$team_id];
        $this->course_db->query("SELECT active_version FROM electronic_gradeable_version WHERE g_id = ? and team_id = ?", $params);
        $query_result = $this->course_db->row();
        return array_key_exists('active_version', $query_result) ? $query_result['active_version'] : 0;
    }

    public function getNumUsersGraded($g_id) {
        $this->course_db->query(
            "
SELECT COUNT(*) as cnt FROM gradeable_data
WHERE g_id = ?",
            [$g_id]
        );

        return intval($this->course_db->row()['cnt']);
    }

    //gets ids of students with non null registration section and null rotating section
    public function getRegisteredUsersWithNoRotatingSection() {
        $this->course_db->query(
            "
SELECT user_id
FROM users AS u
WHERE registration_section IS NOT NULL
AND rotating_section IS NULL;"
        );

        return $this->course_db->rows();
    }

    //gets ids of students with non null rotating section and null registration section
    public function getUnregisteredStudentsWithRotatingSection() {
        $this->course_db->query(
            "
SELECT user_id
FROM users AS u
WHERE registration_section IS NULL
AND rotating_section IS NOT NULL;"
        );

        return $this->course_db->rows();
    }

    public function getGradersForRegistrationSections($sections) {
        $return = [];
        $params = [];
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE sections_registration_id IN " . $this->createParameterList(count($sections));
            $params = $sections;
        }
        $this->course_db->query(
            "
SELECT g.*, u.*
FROM grading_registration AS g
LEFT JOIN (
  SELECT *
  FROM users
) AS u ON u.user_id = g.user_id
{$where}
ORDER BY SUBSTRING(g.sections_registration_id, '^[^0-9]*'), COALESCE(SUBSTRING(g.sections_registration_id, '[0-9]+')::INT, -1), SUBSTRING(g.sections_registration_id, '[^0-9]*$'), g.user_id",
            $params
        );
        $user_store = [];
        foreach ($this->course_db->rows() as $row) {
            if ($row['sections_registration_id'] === null) {
                $row['sections_registration_id'] = "NULL";
            }

            if (!isset($return[$row['sections_registration_id']])) {
                $return[$row['sections_registration_id']] = [];
            }

            if (!isset($user_store[$row['user_id']])) {
                $user_store[$row['user_id']] = new User($this->core, $row);
            }
            $return[$row['sections_registration_id']][] = $user_store[$row['user_id']];
        }
        return $return;
    }

    public function getGradersForRotatingSections($g_id, $sections) {
        $return = [];
        $params = [$g_id];
        $where = "";
        if (count($sections) > 0) {
            $where = " AND sections_rotating_id IN " . $this->createParameterList(count($sections));
            $params = array_merge($params, $sections);
        }
        $this->course_db->query(
            "
SELECT g.*, u.*
FROM grading_rotating AS g
LEFT JOIN (
  SELECT *
  FROM users
) AS u ON u.user_id = g.user_id
WHERE g.g_id=? {$where}
ORDER BY g.sections_rotating_id, g.user_id",
            $params
        );
        $user_store = [];
        foreach ($this->course_db->rows() as $row) {
            if ($row['sections_rotating_id'] === null) {
                $row['sections_rotating_id'] = "NULL";
            }
            if (!isset($return[$row['sections_rotating_id']])) {
                $return[$row['sections_rotating_id']] = [];
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
        $this->course_db->query(
            "
            SELECT sections_rotating_id
            FROM grading_rotating
            WHERE g_id=? {$where}",
            $params
        );
        $return = [];
        foreach ($this->course_db->rows() as $row) {
            $return[] = $row['sections_rotating_id'];
        }
        return $return;
    }

    public function getUsersByRotatingSections($sections, $orderBy = "rotating_section") {
        $return = [];
        if (count($sections) > 0) {
            $placeholders = $this->createParameterList(count($sections));
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
        $this->course_db->query("SELECT * FROM sections_registration ORDER BY SUBSTRING(sections_registration_id, '^[^0-9]*'), COALESCE(SUBSTRING(sections_registration_id, '[0-9]+')::NUMERIC, -1), SUBSTRING(sections_registration_id, '[^0-9]*$') ");
        return $this->course_db->rows();
    }

    /**
     * Gets the default registration section for a given course and term from the courses table
     */
    public function getDefaultRegistrationSection(string $term, string $course): string|null {
        $this->submitty_db->query("SELECT default_section_id FROM courses where term=? and course=?", [$term, $course]);
        return $this->submitty_db->row()['default_section_id'] ?? null;
    }

    /**
     * Updates the default registration section for self register courses.
     */
    public function setDefaultRegistrationSection(string $term, string $course, string $section_id): void {
        $this->submitty_db->query("UPDATE courses set default_section_id=? where term=? and course=?", [$section_id, $term, $course]);
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
        $this->course_db->query("SELECT g_id FROM gradeable WHERE g_grader_assignment_method = {0} ORDER BY g_grade_start_date ASC");
        return $this->course_db->rows();
    }

    /**
     * Gets gradeables for all graders with the sections they were assigned to grade
     * Only includes gradeables that are set to be graded by rotating section or all access, and were in the past
     * With the exception of $gradeable_id, which will always be included
     *
     * @return array
     */
    public function getGradeablesRotatingGraderHistory($gradeable_id) {
        $params = [$gradeable_id];
        $this->course_db->query(
            "
            SELECT
            gu.g_id, gu.user_id, gu.user_group, gr.sections_rotating_id, g_grade_start_date
            FROM (
            SELECT g.g_id, u.user_id, u.user_group, g_grade_start_date
            FROM (SELECT user_id, user_group FROM users WHERE user_group BETWEEN 1 AND 3) AS u
            CROSS JOIN (
              SELECT
                DISTINCT g.g_id,
                g_grade_start_date
              FROM gradeable AS g
              LEFT JOIN
                grading_rotating AS gr ON g.g_id = gr.g_id
              WHERE g_grader_assignment_method = 0 OR g.g_id = ?
            ) AS g
            ) as gu
            LEFT JOIN (
            SELECT
              g_id, user_id, json_agg(sections_rotating_id) as sections_rotating_id
            FROM
              grading_rotating
            GROUP BY g_id, user_id
            ) AS gr ON gu.user_id=gr.user_id AND gu.g_id=gr.g_id
            ORDER BY user_group, user_id, g_grade_start_date",
            $params
        );
        $rows = $this->course_db->rows();
        $modified_rows = [];
        foreach ($rows as $row) {
            $row['sections_rotating_id'] = json_decode($row['sections_rotating_id'] ?? '');
            $modified_rows[] = $row;
        }
        return $modified_rows;
    }

    /**
     * Returns the count of all users in rotating sections that are in a non-null registration section. These are
     * generally students who have late added a course and have been automatically added to the course, but this
     * was done after rotating sections had already been set-up.
     *
     * @return array
     */
    public function getUsersCountByRotatingSections() {
        $this->course_db->query(
            "
            SELECT rotating_section, count(*) as count
            FROM users
            WHERE registration_section IS NOT NULL
            GROUP BY rotating_section
            ORDER BY rotating_section"
        );
        return $this->course_db->rows();
    }

    /**
     * Returns the count of all users in course that are in a non-null registration section.
     *
     * @return integer
     */
    public function getTotalRegisteredUsersCount() {
        $this->course_db->query(
            "
            SELECT count(*) as count
            FROM users
            WHERE registration_section IS NOT NULL"
        );
        return $this->course_db->row()['count'];
    }

    /**
     * Gets rotating sections of each grader for a gradeable
     *
     * @param  string $gradeable_id
     * @return array An array (indexed by user id) of arrays of section numbers
     */
    public function getRotatingSectionsByGrader($gradeable_id) {
        $this->course_db->query(
            "
    SELECT
        u.user_id, u.user_group, json_agg(sections_rotating_id ORDER BY sections_rotating_id ASC) AS sections
    FROM
        users AS u INNER JOIN grading_rotating AS gr ON u.user_id = gr.user_id
    WHERE
        g_id=?
    AND
        u.user_group BETWEEN 1 AND 3
    GROUP BY
        u.user_id
    ORDER BY
        u.user_group ASC
    ",
            [$gradeable_id]
        );

        // Split arrays into php arrays
        $rows = $this->course_db->rows();
        $sections_row = [];
        foreach ($rows as $row) {
            $sections_row[$row['user_id']] = json_decode($row['sections']);
        }
        return $sections_row;
    }

    public function getGradersByUserType() {
        $this->course_db->query(
            "SELECT
                COALESCE(user_preferred_givenname, user_givenname) AS user_givenname,
                COALESCE(user_preferred_familyname, user_familyname) AS user_familyname,
                user_id,
                user_group
            FROM
                users
            WHERE
                user_group < 4
            ORDER BY
                user_group, user_id ASC"
        );
        $users = [];

        foreach ($this->course_db->rows() as $row) {
            $users[$row['user_group']][] = [$row['user_id'], $row['user_givenname'], $row['user_familyname']];
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
        $this->course_db->query(
            "
SELECT rotating_section, count(*) as count
FROM users
WHERE registration_section IS NULL
GROUP BY rotating_section
ORDER BY rotating_section"
        );
        return $this->course_db->rows();
    }

    /**
     * Get newly registered students (registration section != NULL) that have not yet been
     * assigned to a rotating section.
     *
     * @return string[] user id's
     */
    public function getRegisteredUserIdsWithNullRotating() {
        $this->course_db->query(
            "
            SELECT user_id
            FROM users
            WHERE rotating_section IS NULL AND registration_section IS NOT NULL
            ORDER BY user_id ASC"
        );
        return array_map(
            function ($elem) {
                return $elem['user_id'];
            },
            $this->course_db->rows()
        );
    }

     /**
      * Return an array of user id's for users that have been assigned a registration section
      *
      * @return string[] user id's
      */
    public function getRegisteredUserIds() {
        $this->course_db->query(
            "
SELECT user_id
FROM users
WHERE registration_section IS NOT NULL
ORDER BY user_id ASC"
        );
        return array_map(
            function ($elem) {
                return $elem['user_id'];
            },
            $this->course_db->rows()
        );
    }

    /**
     * Get all team ids for all gradeables
     *
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

    public function setAllUsersRotatingSectionNull() {
        $this->course_db->query("UPDATE users SET rotating_section=NULL");
    }

    /**
     * Remove unregistered students (registration section = NULL) from rotating sections
     *
     */
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

    /**
     * Adds given rotating section to the database
     *
     * @param integer $section
     */
    public function insertNewRotatingSection($section) {
        $this->course_db->query("INSERT INTO sections_rotating (sections_rotating_id) VALUES(?)", [$section]);
    }

    public function insertNewRegistrationSection($section) {
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        $this->submitty_db->query("INSERT INTO courses_registration_sections (term, course, registration_section_id) VALUES (?,?,?) ON CONFLICT DO NOTHING", [$semester, $course, $section]);
        return $this->submitty_db->getrowcount();
    }

    public function deleteRegistrationSection($section) {
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        $this->submitty_db->query("DELETE FROM courses_registration_sections WHERE term=? AND course=? AND registration_section_id=?", [$semester, $course, $section]);
        return $this->submitty_db->getRowCount();
    }

    public function setupRotatingSections($graders, $gradeable_id) {
        $this->course_db->query("DELETE FROM grading_rotating WHERE g_id=?", [$gradeable_id]);
        foreach ($graders as $grader => $sections) {
            foreach ($sections as $i => $section) {
                $this->course_db->query("INSERT INTO grading_rotating(g_id, user_id, sections_rotating_id) VALUES(?,?,?)", [$gradeable_id ,$grader, $section]);
            }
        }
    }

    /**
     * Updates all given users in $users array to have rotating section $section
     *
     * @param integer $section
     * @param array<string> $users An array of user IDs
     */
    public function updateUsersRotatingSection(int $section, array $users) {
        $update_array = array_merge([$section], $users);
        $placeholders = $this->createParameterList(count($users));
        $this->course_db->query("UPDATE users SET rotating_section=? WHERE user_id IN {$placeholders}", $update_array);
    }

    /**
     * Gets all user_ids that are on a team for a given gradeable
     *
     * @param   Gradeable $gradeable
     * @returns string[]
     */
    public function getUsersOnTeamsForGradeable($gradeable) {
        $params = [$gradeable->getId()];
        $this->course_db->query(
            "SELECT user_id FROM teams WHERE
                team_id = ANY(SELECT team_id FROM gradeable_teams WHERE g_id = ?)",
            $params
        );

        $users = [];
        foreach ($this->course_db->rows() as $row) {
            $users[] = $row['user_id'];
        }
        return $users;
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
     * @param string $g_id
     * @param string|null $user_id
     * @param string|null $team_id
     * @param int    $version
     * @param string $timestamp
     */
    public function insertVersionDetails($g_id, $user_id, $team_id, $version, $timestamp) {
        $this->course_db->query(
            "
INSERT INTO electronic_gradeable_data
(g_id, user_id, team_id, g_version, autograding_non_hidden_non_extra_credit, autograding_non_hidden_extra_credit,
autograding_hidden_non_extra_credit, autograding_hidden_extra_credit, submission_time)

VALUES(?, ?, ?, ?, 0, 0, 0, 0, ?)",
            [$g_id, $user_id, $team_id, $version, $timestamp]
        );
        if ($user_id === null) {
            $this->course_db->query(
                "SELECT * FROM electronic_gradeable_version WHERE g_id=? AND team_id=?",
                [$g_id, $team_id]
            );
        }
        else {
            $this->course_db->query(
                "SELECT * FROM electronic_gradeable_version WHERE g_id=? AND user_id=?",
                [$g_id, $user_id]
            );
        }
        $row = $this->course_db->row();
        if (!empty($row)) {
            $this->updateActiveVersion($g_id, $user_id, $team_id, $version);
        }
        else {
            $this->course_db->query(
                "INSERT INTO electronic_gradeable_version (g_id, user_id, team_id, active_version) VALUES(?, ?, ?, ?)",
                [$g_id, $user_id, $team_id, $version]
            );
        }
    }

    /**
     * Updates the row in electronic_gradeable_version table for a given gradeable and student. This function should
     * only be run directly if we know that the row exists (so when changing the active version for example) as
     * otherwise it'll throw an exception as it does not do error checking on if the row exists.
     *
     * @param string      $g_id
     * @param string|null $user_id
     * @param string|null $team_id
     * @param int         $version
     */
    public function updateActiveVersion($g_id, $user_id, $team_id, $version) {
        if ($user_id === null) {
            $this->course_db->query(
                "UPDATE electronic_gradeable_version SET active_version=? WHERE g_id=? AND team_id=?",
                [$version, $g_id, $team_id]
            );
        }
        else {
            $this->course_db->query(
                "UPDATE electronic_gradeable_version SET active_version=? WHERE g_id=? AND user_id=?",
                [$version, $g_id, $user_id]
            );
        }
    }


    public function getAllSectionsForGradeable($gradeable) {
        $grade_type = $gradeable->isGradeByRegistration() ? 'registration' : 'rotating';

        if ($gradeable->isGradeByRegistration()) {
            $this->course_db->query(
                "
                SELECT * FROM sections_registration
                ORDER BY SUBSTRING(sections_registration_id, '^[^0-9]*'),
                COALESCE(SUBSTRING(sections_registration_id, '[0-9]+')::INT, -1),
                SUBSTRING(sections_registration_id, '[^0-9]*$')"
            );
        }
        else {
            $this->course_db->query(
                "
                SELECT * FROM sections_rotating
                ORDER BY sections_rotating_id"
            );
        }

        $sections = $this->course_db->rows();
        foreach ($sections as $i => $section) {
            $sections[$i] = $section["sections_{$grade_type}_id"];
        }
        return $sections;
    }

    /**
     * Gets the ids of all submitters who received a mark
     *
     * @param  Mark      $mark
     * @param  User      $grader
     * @param  Gradeable $gradeable
     * @param  string      $anon
     * @return string[]
     */
    public function getSubmittersWhoGotMarkBySection($mark, $grader, $gradeable, $anon = 'unblind') {
        // Switch the column based on gradeable team-ness
        $type = $mark->getComponent()->getGradeable()->isTeamAssignment() ? 'team' : 'user';
        // TODO: anon teams?
        $user_type = ($type == 'user' && $anon != 'unblind') ? 'anon' : $type;
        $row_type = $user_type . "_id";

        $params = [$grader->getId(), $mark->getId()];
        $table = $mark->getComponent()->getGradeable()->isTeamAssignment() ? 'gradeable_teams' : 'users';
        $grade_type = $gradeable->isGradeByRegistration() ? 'registration' : 'rotating';
        $table_to_refer = 'u';
        $gradeable_anon_inject = '';
        if ($type == 'user' && $user_type == 'anon') {
            array_unshift($params, $mark->getComponent()->getGradeable()->getId());
            $table_to_refer = 'ga';
            $gradeable_anon_inject = 'JOIN gradeable_anon ga ON ga.user_id=u.user_id AND ga.g_id = ?';
        }
        $this->course_db->query(
            "
            SELECT {$table_to_refer}.{$user_type}_id
            FROM {$table} u
                {$gradeable_anon_inject}
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
            WHERE gcmd.gcm_id = ?",
            $params
        );

        // Map the results into a non-associative array of team/user ids
        return array_map(
            function ($row) use ($row_type) {
                    return $row[$row_type];
            },
            $this->course_db->rows()
        );
    }

    public function getAllSubmittersWhoGotMark($mark, $anon = 'unblind') {
        // Switch the column based on gradeable team-ness
        $type = $mark->getComponent()->getGradeable()->isTeamAssignment() ? 'team' : 'user';
        $row_type = ($anon != 'unblind' && $type != 'team') ? 'anon_id' : "gd_" . $type . "_id";
        //TODO: anon teams?
        if ($anon != 'unblind' && $type != 'team') {
            $table = $mark->getComponent()->getGradeable()->isTeamAssignment() ? 'gradeable_teams' : 'gradeable_anon';
            $this->course_db->query(
                "
                SELECT u.anon_id
                FROM {$table} u
                    JOIN (
                        SELECT gd.gd_{$type}_id, gcmd.gcm_id
                        FROM gradeable_component_mark_data AS gcmd
                            JOIN gradeable_data AS gd ON gd.gd_id=gcmd.gd_id
                    ) as gcmd
                    ON gcmd.gd_{$type}_id=u.{$type}_id
                WHERE gcmd.gcm_id = ?",
                [$mark->getId()]
            );
        }
        else {
            $this->course_db->query(
                "
                SELECT gd.gd_{$type}_id
                FROM gradeable_component_mark_data gcmd
                  JOIN gradeable_data gd ON gd.gd_id=gcmd.gd_id
                WHERE gcm_id = ?",
                [$mark->getId()]
            );
        }


        // Map the results into a non-associative array of team/user ids
        return array_map(
            function ($row) use ($row_type) {
                return $row[$row_type];
            },
            $this->course_db->rows()
        );
    }

    /**
     * Finds the viewed time for a specific user on a team.
     * Assumes team_ids are unique (cannot be used for 2 different gradeables)
     *
     * @param string $team_id
     * @param string $user_id
     */
    public function getTeamViewedTime($team_id, $user_id) {
        $this->course_db->query("SELECT last_viewed_time FROM teams WHERE team_id = ? and user_id=?", [$team_id,$user_id]);
        return $this->course_db->row()['last_viewed_time'];
    }

    /**
     * Updates the viewed time to now for a specific user on a team.
     * Assumes team_ids are unique (cannot be used for 2 different gradeables)
     *
     * @param string $team_id
     * @param string $user_id
     */
    public function updateTeamViewedTime($team_id, $user_id) {
        $this->course_db->query(
            "UPDATE teams SET last_viewed_time = NOW() WHERE team_id=? and user_id=?",
            [$team_id,$user_id]
        );
    }

    /**
     * Updates the viewed time to NULL for all users on a team.
     * Assumes team_ids are unique (cannot be used for 2 different gradeables)
     *
     * @param string $team_id
     */
    public function clearTeamViewedTime($team_id) {
        $this->course_db->query(
            "UPDATE teams SET last_viewed_time = NULL WHERE team_id=?",
            [$team_id]
        );
    }

    /**
     * Finds all teams for a gradeable and creates a map for each with key => user_id ; value => last_viewed_tim
     * Assumes team_ids are unique (cannot be used for 2 different gradeables)
     *
     * @param  Gradeable $gradeable
     * @return array
     */
    public function getAllTeamViewedTimesForGradeable($gradeable) {
        $params = [$gradeable->getId()];
        $this->course_db->query(
            "SELECT team_id,user_id,last_viewed_time FROM teams WHERE
                team_id = ANY(SELECT team_id FROM gradeable_teams WHERE g_id = ?)",
            $params
        );

        $user_viewed_info = [];
        foreach ($this->course_db->rows() as $row) {
            $team = $row['team_id'];
            $user = $row['user_id'];
            $time = $row['last_viewed_time'];

            if (!array_key_exists($team, $user_viewed_info)) {
                $user_viewed_info[$team] = [];
            }
            $user_viewed_info[$team][$user] = $time;
        }
        return $user_viewed_info;
    }

    /**
     * Update the boolean determining whether the user can have only one active session at a time
     * @param string $user_id
     * @param bool $to_set
     *
     */
    public function updateSingleSessionSetting(string $user_id, bool $to_set = false): void {
        $this->submitty_db->query("UPDATE users SET enforce_single_session=? WHERE user_id=?", [$to_set, $user_id]);
    }

    /**
     * Get the enforce_single_session boolean of a user
     * @param string $user_id
     *
     * @return bool
     */
    public function getSingleSessionSetting(string $user_id): bool {
        $this->submitty_db->query("SELECT enforce_single_session FROM users WHERE user_id=?", [$user_id]);
        return $this->submitty_db->row()['enforce_single_session'];
    }

    public function getAllGradeablesIdsAndTitles() {
        $this->course_db->query("SELECT g_id, g_title FROM gradeable ORDER BY g_title ASC");
        return $this->course_db->rows();
    }

    /**
     * Gets the date for a specified gradeable
     *
     * @param string $id
     * @return \DateTime
     */
    public function getDueDateForGradeableById(string $id): ?\DateTime {
        $this->course_db->query("SELECT eg_submission_due_date FROM electronic_gradeable WHERE g_id=? AND eg_has_due_date", [$id]);
        if (count($this->course_db->rows()) > 0) { // the gradeable has a due date
            return new \DateTime($this->course_db->row()['eg_submission_due_date']);
        }
        else {
            return null;
        }
    }

    public function getAllGradeablesIds() {
        $this->course_db->query("SELECT g_id FROM gradeable ORDER BY g_id");
        return $this->course_db->rows();
    }

    public function getGradeableIdsForFullAccessLimitedGraders() {
        $this->course_db->query("SELECT g_id FROM gradeable WHERE g_min_grading_group = 3 AND g_grader_assignment_method = 2");
        return $this->course_db->rows();
    }

    /**
     * returns array of all rotating sections in course
     *
     * @return array
     */
    public function getAllRotatingSections() {

        $this->course_db->query("SELECT sections_rotating_id FROM sections_rotating ORDER BY sections_rotating_id");

        $tmp = $this->course_db->rows();
        $sections = [];
        foreach ($tmp as $row) {
            $sections[] = $row['sections_rotating_id'];
        }
        return $sections;
    }

     /**
      * returns 2d array of new graders after rotating sections set up
      * for all access grading and limited access graders gradeables,
      * top level is all graders' ids and second level is all rotating sections
      *
      * @return array
      */
    public function getNewGraders() {
        $new_graders = [];
        $all_sections = $this->core->getQueries()->getAllRotatingSections();
        $this->course_db->query("SELECT user_id FROM users WHERE user_group < 4");
        $tmp = $this->course_db->rows();
        foreach ($tmp as $row) {
            $new_graders[$row['user_id']] = $all_sections;
        }

        return $new_graders;
    }

    /**
     * gets ids of all electronic gradeables excluding assignments that will be bulk
     * uploaded by TA or instructor.
     *
     * @return array
     */
    public function getAllElectronicGradeablesIds() {
        $this->course_db->query(
            "
            SELECT gradeable.g_id, g_title, eg_submission_due_date
            FROM gradeable INNER JOIN electronic_gradeable
                ON gradeable.g_id = electronic_gradeable.g_id
            WHERE g_gradeable_type=0 and not (eg_student_view=TRUE and eg_student_view_after_grades=TRUE) and eg_has_due_date=TRUE
            ORDER BY g_grade_released_date DESC
        "
        );
        return $this->course_db->rows();
    }

    /**
     * Gets id's and titles of the electronic gradeables that have non-inherited teams
     *
     * @return string
     */
    // public function getAllElectronicGradeablesWithBaseTeams() {
    //     $this->course_db->query('SELECT g_id, g_title FROM gradeable WHERE g_id=ANY(SELECT g_id FROM electronic_gradeable WHERE eg_team_assignment IS TRUE AND (eg_inherit_teams_from=\'\') IS NOT FALSE) ORDER BY g_title ASC');
    //     return $this->course_db->rows();
    // }

    /**
     * Create a new team id and team in gradeable_teams for given gradeable, add $user_id as a member
     *
     * @param  string  $g_id
     * @param  string  $user_id
     * @param  string $registration_section
     * @param  integer $rotating_section
     * @return string $team_id
     */
    public function createTeam($g_id, $user_id, $registration_section, $rotating_section, $team_name) {
        $this->course_db->query("SELECT COUNT(*) AS cnt FROM gradeable_teams");
        $team_id_prefix = strval($this->course_db->row()['cnt']);
        if (strlen($team_id_prefix) < 5) {
            $team_id_prefix = str_repeat("0", 5 - strlen($team_id_prefix)) . $team_id_prefix;
        }
        $team_id = "{$team_id_prefix}_{$user_id}";

        $params = [$team_id, $g_id, $registration_section, $rotating_section, $team_name];
        $this->course_db->query("INSERT INTO gradeable_teams (team_id, g_id, registration_section, rotating_section, team_name) VALUES(?,?,?,?,?)", $params);
        $this->course_db->query("INSERT INTO teams (team_id, user_id, state) VALUES(?,?,1)", [$team_id, $user_id]);
        $this->core->getQueries()->getTeamById($team_id)->getAnonId();
        return $team_id;
    }

    /**
     * Set team $team_id's registration/rotating section to $section
     *
     * @param string $team_id
     * @param int    $section
     */
    public function updateTeamRegistrationSection($team_id, $section) {
        $this->course_db->query("UPDATE gradeable_teams SET registration_section=? WHERE team_id=?", [$section, $team_id]);
    }

    /**
     * Set team $team_id's anon_id
     *
     * @param string $team_id
     * @param string $anon_id
     */
    public function updateTeamAnonId($team_id, $anon_id) {
        $this->course_db->query("UPDATE gradeable_teams SET anon_id=? WHERE team_id=?", [$anon_id, $team_id]);
    }

    /**
     * Set a team's ($team_id) rotating section ($section)
     *
     * @param string $team_id
     * @param int    $section
     */
    public function updateTeamRotatingSection($team_id, $section) {
        $this->course_db->query("UPDATE gradeable_teams SET rotating_section=? WHERE team_id=?", [$section, $team_id]);
    }

    /**
     * Set teams' (array $team_ids) rotating section ($section) for given gradeable id ($g_id)
     *
     * @param string[] $team_ids
     * @param int      $section
     * @param string   $g_id
     */
    public function updateTeamsRotatingSection($team_ids, $section, $g_id) {
        $update_array = array_merge([$section, $g_id], $team_ids);
        $placeholders = $this->createParameterList(count($team_ids));
        $this->course_db->query("UPDATE gradeable_teams SET rotating_section=? WHERE g_id=? AND team_id IN {$placeholders}", $update_array);
    }

    /**
     * Set team $team_id's team_name
     *
     * @param string $team_id
     * @param string $team_name
     */
    public function updateTeamName($team_id, $team_name) {
        $this->course_db->query("UPDATE gradeable_teams SET team_name=? WHERE team_id=?", [$team_name, $team_id]);
    }

    /**
     * Remove a user from their current team
     *
     * @param string $team_id
     * @param string $user_id
     */
    public function leaveTeam($team_id, $user_id) {
        $this->course_db->query(
            "DELETE FROM teams AS t
          WHERE team_id=? AND user_id=? AND state=1",
            [$team_id, $user_id]
        );
        $this->course_db->query("SELECT * FROM teams WHERE team_id=? AND state=1", [$team_id]);
        if (count($this->course_db->rows()) == 0) {
            //If this happens, then remove all invitations
            $this->course_db->query(
                "DELETE FROM teams AS t
              WHERE team_id=?",
                [$team_id]
            );
        }
    }

    /**
     * Add user $user_id to team $team_id as an invited user
     *
     * @param string $team_id
     * @param string $user_id
     */
    public function sendTeamInvitation($team_id, $user_id) {
        $this->course_db->query("INSERT INTO teams (team_id, user_id, state) VALUES(?,?,0)", [$team_id, $user_id]);
    }

    /**
     * Add user $user_id to team $team_id as a team member
     *
     * @param string $team_id
     * @param string $user_id
     */
    public function acceptTeamInvitation($team_id, $user_id) {
        $this->course_db->query("INSERT INTO teams (team_id, user_id, state) VALUES(?,?,1)", [$team_id, $user_id]);
    }

    /**
     * Cancel a pending team invitation
     *
     * @param string $team_id
     * @param string $user_id
     */
    public function cancelTeamInvitation($team_id, $user_id) {
        $this->course_db->query("DELETE FROM teams WHERE team_id=? AND user_id=? AND state=0", [$team_id, $user_id]);
    }

    /**
     * Decline all pending team invitiations for a user
     *
     * @param string $g_id
     * @param string $user_id
     */
    public function declineAllTeamInvitations($g_id, $user_id) {
        $this->course_db->query(
            "DELETE FROM teams AS t USING gradeable_teams AS gt
          WHERE gt.g_id=? AND gt.team_id = t.team_id AND t.user_id=? AND t.state=0",
            [$g_id, $user_id]
        );
    }


    /**
     * Return Team object for team with given Team ID
     *
     * @param  string $team_id
     * @return \app\models\Team|null
     */
    public function getTeamById($team_id) {
        $this->course_db->query(
            "
            SELECT gt.team_id, gt.registration_section, gt.rotating_section, gt.team_name, COALESCE(NULLIF(jsonb_agg(u)::text, '[null]'), '[]')::jsonb AS users
            FROM gradeable_teams gt
              LEFT JOIN
              (SELECT t.team_id, t.state, u.*
               FROM teams t
                 JOIN users u ON t.user_id = u.user_id
              ) AS u ON gt.team_id = u.team_id
            WHERE gt.team_id = ?
            GROUP BY gt.team_id",
            [$team_id]
        );
        if (count($this->course_db->rows()) === 0) {
            return null;
        }
        $details = $this->course_db->row();
        $details["users"] = json_decode($details["users"], true);
        return new Team($this->core, $details);
    }

    /**
     * Return Team object for team which the given user belongs to on the given gradeable
     *
     * @param  string $g_id
     * @param  string $user_id
     * @return \app\models\Team|null
     */
    public function getTeamByGradeableAndUser($g_id, $user_id) {
        $this->course_db->query(
            "
            SELECT gt.team_id, gt.registration_section, gt.rotating_section, gt.team_name, COALESCE(NULLIF(jsonb_agg(u)::text, '[null]'), '[]')::jsonb AS users
            FROM gradeable_teams gt
              LEFT JOIN
              (SELECT t.team_id, t.state, u.*
               FROM teams t
                 JOIN users u ON t.user_id = u.user_id
              ) AS u ON gt.team_id = u.team_id
            WHERE g_id=? AND gt.team_id IN (
              SELECT team_id
              FROM teams
              WHERE user_id=? AND state=1)
            GROUP BY gt.team_id",
            [$g_id, $user_id]
        );
        if (count($this->course_db->rows()) === 0) {
            return null;
        }
        $details = $this->course_db->row();
        $details["users"] = json_decode($details["users"], true);
        return new Team($this->core, $details);
    }

    /**
     * Returns a boolean for whether the given user has multiple pending team invites for the given gradeable
     *
     * @param string $user_id
     * @param string $g_id
     * @return bool
     */
    public function getUserMultipleTeamInvites(string $g_id, string $user_id): bool {
        $this->course_db->query(
            "
            SELECT gtm.*, tm.*
            FROM gradeable_teams gtm
            INNER JOIN teams tm
            ON gtm.team_id = tm.team_id
            WHERE gtm.g_id = ? AND tm.user_id = ?",
            [$g_id,$user_id]
        );
        return count($this->course_db->rows()) > 1;
    }

    /**
     * Return an array of Team objects for all teams on given gradeable
     *
     * @param  string $g_id
     * @return \app\models\Team[]
     */
    public function getTeamsByGradeableId($g_id) {
        $this->course_db->query(
            "
            SELECT gt.team_id, gt.registration_section, gt.rotating_section, gt.team_name, COALESCE(NULLIF(jsonb_agg(u)::text, '[null]'), '[]')::jsonb AS users
            FROM gradeable_teams gt
              LEFT JOIN
                (SELECT t.team_id, t.state, u.*
                 FROM teams t
                   JOIN users u ON t.user_id = u.user_id
                ) AS u ON gt.team_id = u.team_id
            WHERE g_id=?
            GROUP BY gt.team_id
            ORDER BY team_id",
            [$g_id]
        );

        $teams = [];
        foreach ($this->course_db->rows() as $row) {
            $row['users'] = json_decode($row['users'], true);
            $teams[] = new Team($this->core, $row);
        }

        return $teams;
    }


    /**
     * Return an array of team_ids for a gradeable that have at least one user in the team
     *
     * @param  string $g_id
     * @return string[] team ids
     */
    public function getTeamsWithMembersFromGradeableID($g_id) {
        $team_map = $this->core->getQueries()->getTeamIdsAllGradeables();

        if (!array_key_exists($g_id, $team_map)) {
            return [];
        }

        $teams = $team_map[$g_id];

        $this->course_db->query("SELECT team_id FROM teams");
        $teams_with_members = [];
        foreach ($this->course_db->rows() as $row) {
            $teams_with_members[] = $row['team_id'];
        }

        return array_intersect($teams, $teams_with_members);
    }


    /**
     * Add ($g_id,$user_id, $message) to table seeking_team
     *
     * @param string $g_id
     * @param string $user_id
     * @param string $message
     */
    public function addToSeekingTeam($g_id, $user_id, $message) {
        $this->course_db->query("INSERT INTO seeking_team(g_id, user_id, message) VALUES (?,?,?)", [$g_id, $user_id, $message]);
    }

    /**
     * Remove ($g_id,$user_id) pair from table seeking_team
     *
     * @param string $g_id
     * @param string $user_id
     */
    public function removeFromSeekingTeam($g_id, $user_id) {
        $this->course_db->query("DELETE FROM seeking_team WHERE g_id=? AND user_id=?", [$g_id, $user_id]);
    }

    /**
     * Edit the user's message from table seeking_team
     */
    public function updateSeekingTeamMessageById(string $g_id, string $user_id, ?string $message) {
        $this->course_db->query("UPDATE seeking_team SET message=? WHERE g_id=? AND user_id=?", [$message, $g_id, $user_id]);
    }

    /**
     * Get the user's message from table seeking_team
     *
     * @param string $g_id
     * @param string $user_id
     */
    public function getSeekMessageByUserId($g_id, $user_id) {
        $this->course_db->query(
            "SELECT message
          FROM seeking_team
          WHERE g_id=?
          AND user_id=?",
            [$g_id, $user_id]
        );

        $row = $this->course_db->row();
        if (count($row) === 0) {
            return null;
        }
        return $row['message'];
    }

    /**
     * Return an array of user_id who are seeking team who passed gradeable_id
     *
     * @param  string $g_id
     * @return array $users_seeking_team
     */
    public function getUsersSeekingTeamByGradeableId($g_id) {
        $this->course_db->query(
            "SELECT user_id
          FROM seeking_team
          WHERE g_id=?
          ORDER BY user_id",
            [$g_id]
        );

        $users_seeking_team = [];
        foreach ($this->course_db->rows() as $row) {
            array_push($users_seeking_team, $row['user_id']);
        }
        return $users_seeking_team;
    }

    /**
     * Return array of counts of teams/users without team/graded components
     * corresponding to each registration/rotating section
     *
     * @param  string $g_id
     * @param  int[]  $sections
     * @param  string $section_key
     * @return int[] $return
     *
     */
    public function getTotalTeamCountByGradingSections($g_id, $sections, $section_key) {
        $return = [];
        $params = [$g_id];
        $sections_query = "";
        if (count($sections) > 0) {
            $sections_query = " ({$section_key} IN " . $this->createParameterList(count($sections)) . ") IS NOT FALSE AND";
            $params = array_merge($sections, $params);
        }
        if ($section_key === 'registration_section') {
            $orderby = "SUBSTRING({$section_key}, '^[^0-9]*'), COALESCE(SUBSTRING({$section_key}, '[0-9]+')::INT, -1), SUBSTRING({$section_key}, '[^0-9]*$')";
        }
        else {
            $orderby = $section_key;
        }

        $this->course_db->query(
            "
SELECT count(*) as cnt, {$section_key}
FROM gradeable_teams
WHERE {$sections_query} g_id=? AND team_id IN (
  SELECT team_id
  FROM teams
)
GROUP BY {$section_key}
ORDER BY {$orderby}",
            $params
        );
        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }

            $return[$row[$section_key]] = intval($row['cnt']);
        }
        foreach ($sections as $section) {
            if (!isset($return[$section])) {
                $return[$section] = 0;
            }
        }
        return $return;
    }

    public function getSubmittedTeamCountByGradingSections($g_id, $sections, $section_key) {
        $return = [];
        $params = [$g_id];
        $where = "";
        if (count($sections) > 0) {
            // Expand out where clause
            $sections_keys = array_values($sections);
            $placeholders = $this->createParameterList(count($sections_keys));
            $where = "WHERE ({$section_key} IN {$placeholders}) IS NOT FALSE";
            $params = array_merge($params, $sections_keys);
        }
        $this->course_db->query(
            "
SELECT count(*) as cnt, {$section_key}
FROM gradeable_teams
INNER JOIN electronic_gradeable_version
ON
gradeable_teams.team_id = electronic_gradeable_version.team_id
AND electronic_gradeable_version.active_version>0
AND electronic_gradeable_version.g_id=?
{$where}
GROUP BY {$section_key}
ORDER BY {$section_key}",
            $params
        );

        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
            $return[$row[$section_key]] = intval($row['cnt']);
        }

        return $return;
    }

    /**
     * Gets the number of bad (late) team submissions associated with this gradeable.
     * @param  string $g_id gradeable id we are looking up
     * @param  array<int> $sections an array holding sections of the given gradeable
     * @param  string $section_key key we are basing grading sections off of
     * @return array<int,int> with a key representing a section and value representing the number of bad submissions
     */
    public function getBadTeamSubmissionsByGradingSection($g_id, $sections, $section_key) {
        $return = [];
        $params = [$g_id];
        $where = "";
        if (count($sections) > 0) {
            // Expand out where clause
            $sections_keys = array_values($sections);
            $placeholders = $this->createParameterList(count($sections_keys));
            $where = "WHERE ({$section_key} IN {$placeholders}) IS NOT FALSE";
            $params = array_merge($params, $sections_keys);
        }

        $this->course_db->query(
            "
            SELECT gradeable_teams.team_id, count(*) as cnt, {$section_key}
            FROM gradeable_teams
            INNER JOIN electronic_gradeable_version
            ON
            gradeable_teams.team_id = electronic_gradeable_version.team_id
            AND electronic_gradeable_version.active_version>0
            AND electronic_gradeable_version.g_id=?
            INNER JOIN electronic_gradeable
            ON 
            electronic_gradeable.g_id=electronic_gradeable_version.g_id
            AND electronic_gradeable.eg_submission_due_date IS NOT NULL
            INNER JOIN late_day_cache AS ldc
            ON ldc.g_id=electronic_gradeable.g_id
            AND ldc.team_id=gradeable_teams.team_id
            AND ldc.submission_days_late>0
            AND ldc.submission_days_late>ldc.late_day_exceptions 
            And ldc.late_days_change =0
            {$where}
            GROUP BY gradeable_teams.team_id, {$section_key}
            ORDER BY gradeable_teams.team_id, {$section_key}",
            $params
        );

        $teamsCounted = [];
        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
            $teamId = $row['team_id'];
            $section = $row[$section_key];
            $count = intval($row['cnt']);
            if (!isset($teamsCounted[$teamId])) {
                $teamsCounted[$teamId] = [];
            }
            $teamsCounted[$teamId][$section] = $count;
        }

        foreach ($this->course_db->rows() as $row) {
            $teamId = $row['team_id'];
            $section = $row[$section_key];
            // Check if the team exists in $teamsCounted for this section
            if (isset($teamsCounted[$teamId][$section])) {
                // Increment the count for this section in $return
                if (!isset($return[$section])) {
                    $return[$section] = 1;
                }
                else {
                    $return[$section]++;
                }
            }
        }
        return $return;
    }

    public function getUsersWithoutTeamByGradingSections($g_id, $sections, $section_key) {
        $return = [];
        $params = [$g_id];
        $sections_query = "";
        if (count($sections) > 0) {
            $sections_keys = array_values($sections);
            $placeholders = $this->createParameterList(count($sections_keys));
            $sections_query = "({$section_key} IN {$placeholders}) IS NOT FALSE AND";
            $params = array_merge($sections, $params);
        }
        $orderBy = "";
        if ($section_key == "registration_section") {
            $orderBy = "SUBSTRING(registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(registration_section, '[0-9]+')::INT, -1), SUBSTRING(registration_section, '[^0-9]*$')";
        }
        else {
            $orderBy = $section_key;
        }
        $this->course_db->query(
            "
SELECT count(*) as cnt, {$section_key}
FROM users
WHERE {$sections_query} user_id NOT IN (
  SELECT user_id
  FROM gradeable_teams NATURAL JOIN teams
  WHERE g_id=?
  ORDER BY user_id
)
GROUP BY {$section_key}
ORDER BY {$orderBy}",
            $params
        );
        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
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
        $return = [];
        $params = [$g_id];
        $sections_query = "";
        if (count($sections) > 0) {
            $sections_keys = array_values($sections);
            $placeholders = $this->createParameterList(count($sections_keys));
            $sections_query = "({$section_key} IN {$placeholders}) IS NOT FALSE AND";
            $params = array_merge($sections, $params);
        }
        $orderBy = "";
        if ($section_key == "registration_section") {
            $orderBy = "SUBSTRING(registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(registration_section, '[0-9]+')::INT, -1), SUBSTRING(registration_section, '[^0-9]*$')";
        }
        else {
            $orderBy = $section_key;
        }

        $this->course_db->query(
            "
SELECT count(*) as cnt, {$section_key}
FROM users
WHERE {$sections_query} user_id IN (
  SELECT user_id
  FROM gradeable_teams NATURAL JOIN teams
  WHERE g_id=?
  ORDER BY user_id
)
GROUP BY {$section_key}
ORDER BY {$orderBy}",
            $params
        );
        foreach ($this->course_db->rows() as $row) {
            if ($row[$section_key] === null) {
                $row[$section_key] = "NULL";
            }
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
        $return = [];
        $params = [$g_id];
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE {$section_key} IN " . $this->createParameterList(count($sections));
            $params = array_merge($params, $sections);
        }
        $this->course_db->query(
            "
SELECT count(gt.*) as cnt, gt.{$section_key}
FROM gradeable_teams AS gt
INNER JOIN (
  SELECT * FROM gradeable_data AS gd LEFT JOIN gradeable_component_data AS gcd ON gcd.gd_id = gd.gd_id WHERE g_id=?
) AS gd ON gt.team_id = gd.gd_team_id
{$where}
GROUP BY gt.{$section_key}
ORDER BY gt.{$section_key}",
            $params
        );
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
        $this->course_db->query(
            "
        SELECT u.user_id, user_givenname, user_preferred_givenname,
          user_familyname, user_preferred_familyname, allowed_late_days, since_timestamp
        FROM users AS u
        FULL OUTER JOIN late_days AS l
          ON u.user_id=l.user_id
        WHERE allowed_late_days IS NOT NULL
        ORDER BY
          user_email ASC, since_timestamp DESC;"
        );

        $return = [];
        foreach ($this->course_db->rows() as $row) {
            $return[] = new SimpleLateUser($this->core, $row);
        }
        return $return;
    }

    /**
     * Return an array of users with extensions
     *
     * @param  string $gradeable_id
     * @return SimpleLateUser[]
     */
    public function getUsersWithExtensions($gradeable_id) {
        $this->course_db->query(
            "
        SELECT u.user_id, user_givenname,
          user_preferred_givenname, user_familyname,
          late_day_exceptions, reason_for_exception
        FROM users as u
        FULL OUTER JOIN late_day_exceptions as l
          ON u.user_id=l.user_id
        WHERE g_id=?
          AND late_day_exceptions IS NOT NULL
          AND late_day_exceptions>0
        ORDER BY user_email ASC;",
            [$gradeable_id]
        );

        $return = [];
        foreach ($this->course_db->rows() as $row) {
            $return[] = new SimpleLateUser($this->core, $row);
        }
        return $return;
    }

    /**
     * Return an array of users with overridden Grades
     *
     * @param  string $gradeable_id
     * @return SimpleGradeOverriddenUser[]
     */
    public function getUsersWithOverriddenGrades(string $gradeable_id): array {
        $return = [];
        foreach ($this->getRawUsersWIthOverriddenGrades($gradeable_id) as $row) {
            $return[] = new SimpleGradeOverriddenUser($this->core, $row);
        }
        return $return;
    }

    /**
     * Return an array of users with overridden Grades
     *
     * @param  string $gradeable_id
     * @return array
     */
    public function getRawUsersWithOverriddenGrades(string $gradeable_id): array {
        $this->course_db->query(
            "
        SELECT u.user_id, user_givenname,
          user_preferred_givenname, user_familyname, marks, comment
        FROM users as u
        FULL OUTER JOIN grade_override as g
          ON u.user_id=g.user_id
        WHERE g_id=?
          AND marks IS NOT NULL
        ORDER BY user_email ASC;",
            [$gradeable_id]
        );
        return $this->course_db->rows();
    }

    /**
     * Return a user with overridden Grades for specific gradable and user_id
     *
     * @param  string $gradeable_id
     * @param  string $user_id
     * @return SimpleGradeOverriddenUser|null
     */
    public function getAUserWithOverriddenGrades($gradeable_id, $user_id) {
        $this->course_db->query(
            "
        SELECT u.user_id, user_givenname,
          user_preferred_givenname, user_familyname, marks, comment
        FROM users as u
        FULL OUTER JOIN grade_override as g
          ON u.user_id=g.user_id
        WHERE g_id=?
          AND marks IS NOT NULL
          AND u.user_id=?",
            [$gradeable_id,$user_id]
        );

          return ($this->course_db->getRowCount() > 0) ? new SimpleGradeOverriddenUser($this->core, $this->course_db->row()) : null;
    }

    public function getAllOverriddenGrades() {
        $query = <<<SQL
SELECT
    u.user_id,
    g.g_id,
    u.user_givenname,
    u.user_preferred_givenname,
    u.user_familyname,
    g.marks,
    g.comment
FROM users as u
FULL OUTER JOIN grade_override as g
    ON u.user_id=g.user_id
WHERE g.marks IS NOT NULL
ORDER BY user_id ASC
SQL;
        $this->course_db->query($query);

        $return = [];
        foreach ($this->course_db->rows() as $row) {
            if (!isset($return[$row['user_id']])) {
                $return[$row['user_id']] = [];
            }
            $return[$row['user_id']][$row['g_id']] = new SimpleGradeOverriddenUser($this->core, $row);
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
     * @param string  $user_id
     * @param string  $timestamp
     * @param integer $days
     * @param string  $csv_option value determined by selected radio button
     */
    public function updateLateDays($user_id, $timestamp, $days, $csv_option = null) {
        //Update query and values list.
        $query = "
            INSERT INTO late_days (user_id, since_timestamp, allowed_late_days)
            VALUES(?,?,?)
            ON CONFLICT (user_id, since_timestamp) DO UPDATE
            SET allowed_late_days=?
            WHERE late_days.user_id=? AND late_days.since_timestamp=?";
        $vals = [$user_id, $timestamp, $days, $days, $user_id, $timestamp];

        switch ($csv_option) {
            case 'csv_option_preserve_higher':
                //Does NOT overwrite a higher (or same) value of allowed late days.
                $query .= "AND late_days.allowed_late_days<?";
                $vals[] = $days;
                break;
            case 'csv_option_overwrite_all':
            default:
                //Default behavior: overwrite all late days for user and timestamp.
                //No adjustment to SQL query.
        }

        $this->course_db->query($query, $vals);
    }

    /**
     * Delete a given user's allowed late days entry at given effective time
     *
     * @param string $user_id
     * @param string $timestamp
     */
    public function deleteLateDays($user_id, $timestamp) {
        $this->course_db->query(
            "
          DELETE FROM late_days
          WHERE user_id=?
          AND since_timestamp=?",
            [$user_id, $timestamp]
        );
    }

    /**
     * Updates a given user's extensions for a given homework
     *
     * @param string  $user_id
     * @param string  $g_id
     * @param integer $days
     * @param string  $reason
     */
    public function updateExtensions($user_id, $g_id, $days, $reason) {
        $this->course_db->query(
            "
          UPDATE late_day_exceptions
          SET late_day_exceptions=?,
              reason_for_exception=?
          WHERE user_id=?
            AND g_id=?;",
            [$days, $reason, $user_id, $g_id]
        );
        if ($this->course_db->getRowCount() === 0) {
            $this->course_db->query(
                "
            INSERT INTO late_day_exceptions
            (user_id, g_id, late_day_exceptions, reason_for_exception)
            VALUES(?,?,?,?)",
                [$user_id, $g_id, $days, $reason]
            );
        }
    }

    /**
     * Updates overridden grades for given homework
     *
     * @param string  $user_id
     * @param string  $g_id
     * @param integer $marks
     * @param string  $comment
     */
    public function updateGradeOverride($user_id, $g_id, $marks, $comment) {
        $this->course_db->query(
            "
          UPDATE grade_override
          SET marks=?, comment=?
          WHERE user_id=?
            AND g_id=?;",
            [$marks, $comment, $user_id, $g_id]
        );
        if ($this->course_db->getRowCount() === 0) {
            $this->course_db->query(
                "
            INSERT INTO grade_override
            (user_id, g_id, marks, comment)
            VALUES(?,?,?,?)",
                [$user_id, $g_id, $marks, $comment]
            );
        }
    }

    /**
     * Delete a given overridden grades for specific user for specific gradeable
     *
     * @param string $user_id
     * @param string $g_id
     */
    public function deleteOverriddenGrades($user_id, $g_id) {
        $this->course_db->query(
            "
          DELETE FROM grade_override
          WHERE user_id=?
          AND g_id=?",
            [$user_id, $g_id]
        );
    }

    /**
     * Adds an assignment for someone to grade another person for peer grading
     *
     * @param string $student
     * @param string $grader
     * @param string $gradeable_id
     */
    public function insertPeerGradingAssignment($grader, $student, $gradeable_id) {
        $this->course_db->query("INSERT INTO peer_assign(grader_id, user_id, g_id) VALUES (?,?,?)", [$grader, $student, $gradeable_id]);
    }

    /**
     * Removes a specific grader's student from a given assignment
     *
     * @param string $gradeable_id
     * @param string $grader_id
     */
    public function removePeerAssignment($gradeable_id, $grader_id, $student_id) {
        $this->course_db->query("DELETE FROM peer_assign WHERE g_id = ? AND grader_id = ? AND user_id = ?", [$gradeable_id, $grader_id, $student_id]);
    }

    /**
     * Removes a specific grader and their students from a given assignment
     *
     * @param string $gradeable_id
     * @param string $grader_id
     */
    public function removePeerAssignmentsForGrader($gradeable_id, $grader_id) {
        $this->course_db->query("DELETE FROM peer_assign WHERE g_id = ? AND grader_id = ?", [$gradeable_id, $grader_id]);
    }

    /**
     * Adds an assignment for someone to grade another person for peer grading
     *
     * @param string $grader
     * @param string $student
     * @param string $gradeable_id
     * @param string $feedback
     */
    public function insertPeerGradingFeedback($grader, $student, $gradeable_id, $feedback) {
        $this->course_db->query("SELECT feedback FROM peer_feedback WHERE grader_id = ? AND user_id = ? AND g_id = ?", [$grader, $student, $gradeable_id]);
        if (count($this->course_db->rows()) > 0) {
            $this->course_db->query("UPDATE peer_feedback SET feedback = ? WHERE grader_id = ? AND user_id = ? AND g_id = ?", [$feedback, $grader, $student, $gradeable_id]);
        }
        else {
            $this->course_db->query("INSERT INTO peer_feedback(grader_id, user_id, g_id, feedback) VALUES (?,?,?,?)", [$grader, $student, $gradeable_id, $feedback]);
        }
    }

  /**
   * Bulk Uploads Peer Grading Assignments
   *
   * @param string $values
   */
    public function insertBulkPeerGradingAssignment($values) {
        $this->course_db->query("INSERT INTO peer_assign(grader_id, user_id, g_id) VALUES " . $values);
    }


    /**
     * Removes all peer grading pairs from a given assignment
     *
     * @param string $gradeable_id
     */
    public function clearPeerGradingAssignment($gradeable_id) {
        $this->course_db->query("DELETE FROM peer_assign WHERE g_id = ?", [$gradeable_id]);
    }

    /**
     * Adds an assignment for someone to get all the peer grading pairs for a given gradeable
     *
     * @param string $gradeable_id
     */
    public function getPeerGradingAssignment($gradeable_id) {
        $this->course_db->query("SELECT grader_id, user_id FROM peer_assign WHERE g_id = ? ORDER BY grader_id", [$gradeable_id]);
        $return = [];
        foreach ($this->course_db->rows() as $id) {
            if (!array_key_exists($id['grader_id'], $return)) {
                $return[$id['grader_id']] = [];
            }
            array_push($return[$id['grader_id']], $id['user_id']);
        }
        return $return;
    }

    /**
     * Adds an assignment for someone to get the peer feedback for a given user for a given gradeable
     *
     * @param string $gradeable_id
     */
    public function getPeerFeedbackForUser($gradeable_id, $user_id, $anon = false) {
        if ($anon) {
            $this->course_db->query("
            SELECT
                ga.anon_id AS grader_id,
                p.user_id,
                p.feedback
            FROM
                peer_feedback p
            INNER JOIN
                gradeable_anon ga
            ON
                ga.user_id = p.grader_id
            WHERE
                p.g_id = ?
                AND p.user_id = ?
            ORDER BY
                p.grader_id;
            ", [$gradeable_id, $user_id]);
        }
        else {
            $this->course_db->query("SELECT grader_id, user_id, feedback FROM peer_feedback WHERE g_id = ? AND user_id = ? ORDER BY grader_id", [$gradeable_id, $user_id]);
        }
        $return = [];
        foreach ($this->course_db->rows() as $id) {
            $return[$id['grader_id']][$id['user_id']]['feedback'] = $id['feedback'];
        }
        return $return;
    }

    public function getPeerFeedbackInstance($gradeable_id, $grader_id, $user_id) {
        $this->course_db->query("SELECT feedback FROM peer_feedback WHERE g_id = ? AND grader_id = ? AND user_id = ? ORDER BY grader_id", [$gradeable_id, $grader_id, $user_id]);
        $results = $this->course_db->rows();
        if (count($results) > 0) {
            return $results[0]['feedback'];
        }
        return null;
    }
    /**
     * Get all peers assigned to grade a specific student
     *
     * @param string $gradeable_id
     */
    public function getPeerGradingAssignmentForSubmitter($gradeable_id, $submitter_id) {
        $this->course_db->query("SELECT grader_id FROM peer_assign WHERE g_id = ? AND user_id = ? ORDER BY grader_id", [$gradeable_id, $submitter_id]);
        $return = [];
        foreach ($this->course_db->rows() as $id) {
            $return[] = $id['grader_id'];
        }
        return $return;
    }

    /**
     * Get all assignments a student is assigned to peer grade
     *
     * @param string $grader_id
     */
    public function getPeerGradingAssignmentsForGrader($grader_id) {
        $this->course_db->query("SELECT g_id, user_id FROM peer_assign WHERE grader_id = ? ORDER BY g_id", [$grader_id]);
        $return = [];
        foreach ($this->course_db->rows() as $id) {
            if (!array_key_exists($id['g_id'], $return)) {
                $return[$id['g_id']] = [];
            }
            array_push($return[$id['g_id']], $id['user_id']);
        }
        return $return;
    }

    /**
     * Retrieves all unarchived/archived courses (and details) that are accessible by $user_id
     *
     * If the $archived parameter is false, then we run the check:
     * (u.user_id=? AND c.status=1) checks if a course is active where
     * an active course may be accessed by all users
     *
     * If the parameter is true, then we run the check:
     * (u.user_id=? AND c.status=2 AND u.user_group=1) checks if $user_id is an instructor
     * Instructors may access all of their courses
     * Inactive courses may only be accessed by the instructor
     *
     * If $dropped is true, we return null section courses as well.
     *
     * @param  string $user_id  User Id of user we're getting courses for.
     * @param  bool   $archived True if we want archived courses.
     * @param  bool   $dropped  True if we want null section courses.
     * @return Course[] archived courses (and their details) accessible by $user_id
     */
    public function getCourseForUserId($user_id, bool $archived = false, bool $dropped = false): array {
        $include_archived = "AND c.status=1";
        if ($archived) {
            $include_archived = "AND c.status=2 AND u.user_group=1";
        }
        $force_nonnull = "";
        if ($dropped) {
            $force_nonnull = "NOT";
        }

        $query = <<<SQL
SELECT t.name AS term_name, u.term, u.course, u.user_group, u.registration_section
FROM courses_users u
INNER JOIN courses c ON u.course=c.course AND u.term=c.term
INNER JOIN terms t ON u.term=t.term_id
WHERE u.user_id=? {$include_archived} AND {$force_nonnull} (u.registration_section IS NOT NULL OR u.user_group<>4)
ORDER BY u.user_group ASC, t.start_date DESC, u.course ASC
SQL;
        $this->submitty_db->query($query, [$user_id]);
        $return = [];
        foreach ($this->submitty_db->rows() as $row) {
            $course = new Course($this->core, $row);
            $course->loadDisplayName();
            $return[] = $course;
        }
        return $return;
    }

    /**
     * Get all courses where the user with the specified user_id is assigned as an instructor
     * @param string $user_id
     * @return array
     */
    public function getInstructorLevelAccessCourse(string $user_id): array {
        $this->submitty_db->query("SELECT term, course FROM courses_users WHERE user_id=? AND user_group=1", [$user_id]);
        return $this->submitty_db->rows();
    }

    /**
     * @return array<Course>
     */
    public function getSelfRegistrationCourses(string $user_id): array {
        $query = <<<SQL
SELECT c.*, t.name AS term_name FROM courses c, terms t
WHERE c.self_registration_type > ? AND c.status = ? AND  c.course NOT IN (
    SELECT course FROM courses_users WHERE user_id = ? AND (registration_section IS NOT NULL OR user_group <> 4) AND term = t.term_id
) AND c.term = t.term_id ORDER BY t.term_id ASC
SQL;
        $this->submitty_db->query($query, [ConfigurationController::NO_SELF_REGISTER, Course::ACTIVE_STATUS, $user_id]);
        $return = [];
        foreach ($this->submitty_db->rows() as $row) {
            $course = new Course($this->core, $row);
            $course->loadDisplayName();
            $return[] = $course;
        }
        return $return;
    }

    public function getSelfRegistrationType(string $term, string $course): int {
        $this->submitty_db->query("SELECT self_registration_type FROM courses WHERE course=? AND term=?", [$course, $term]);
        return $this->submitty_db->row()['self_registration_type'];
    }

    public function setSelfRegistrationType(string $term, string $course, int $self_registration_type): void {
        $this->submitty_db->query("UPDATE courses set self_registration_type=? WHERE course=? AND term=?", [$self_registration_type, $course, $term]);
    }

    /**
     * Get all unarchived courses where the user with the specified user_id is assigned as an instructor
     */
    public function getInstructorLevelUnarchivedCourses(string $user_id): array {
        $query = "
        SELECT t.name AS term_name, c.term, c.course
        FROM courses AS c
        INNER JOIN terms AS t ON c.term=t.term_id
        INNER JOIN courses_users AS cu ON c.course=cu.course AND c.term=cu.term
        WHERE c.status = 1 AND cu.user_id = ? AND cu.user_group = 1
        ORDER BY t.start_date DESC, c.course ASC
        ";
        $this->submitty_db->query($query, [$user_id]);
        return $this->submitty_db->rows();
    }

    public function getAllCoursesForUserId(string $user_id): array {
        $query = "
        SELECT t.name AS term_name, u.term, u.course, u.user_group
        FROM courses_users u
        INNER JOIN courses c ON u.course=c.course AND u.term=c.term
        INNER JOIN terms t ON u.term=t.term_id
        WHERE u.user_id=? AND (u.registration_section IS NOT NULL OR u.user_group<>4)
        ORDER BY u.user_group ASC, t.start_date DESC, u.course ASC
        ";
        $this->submitty_db->query($query, [$user_id]);
        $return = [];
        foreach ($this->submitty_db->rows() as $row) {
            $course = new Course($this->core, $row);
            $course->loadDisplayName();
            $return[] = $course;
        }
        return $return;
    }

    public function getCourseStatus($semester, $course) {
        $this->submitty_db->query("SELECT status FROM courses WHERE term=? AND course=?", [$semester, $course]);
        return $this->submitty_db->row()['status'];
    }

    public function getPeerAssignment($gradeable_id, $grader) {
        $this->course_db->query("SELECT user_id FROM peer_assign WHERE g_id=? AND grader_id=?", [$gradeable_id, $grader]);
        $return = [];
        foreach ($this->course_db->rows() as $id) {
            $return[] = $id['user_id'];
        }
        return $return;
    }

    public function getNumPeerComponents($g_id) {
        $this->course_db->query("SELECT COUNT(*) as cnt FROM gradeable_component WHERE gc_is_peer='t' and g_id=?", [$g_id]);
        return intval($this->course_db->row()['cnt']);
    }

    public function getNumGradedPeerComponents($gradeable_id, $grader) {
        if (!is_array($grader)) {
            $params = [$grader];
        }
        else {
            $params = $grader;
        }
        $grader_list = $this->createParameterList(count($params));
        $params[] = $gradeable_id;
        $this->course_db->query(
            "SELECT COUNT(*) as cnt
FROM gradeable_component_data as gcd
WHERE gcd.gcd_grader_id IN {$grader_list}
AND gc_id IN (
  SELECT gc_id
  FROM gradeable_component
  WHERE gc_is_peer='t' AND g_id=?
)",
            $params
        );

        return intval($this->course_db->row()['cnt']);
    }

    public function getGradedPeerComponentsByRegistrationSection($gradeable_id, $sections = []) {
        $where = "";
        $params = [];
        if (count($sections) > 0) {
            $where = "WHERE registration_section IN " . $this->createParameterList(count($sections));
            $params = $sections;
        }
        $params[] = $gradeable_id;
        $this->course_db->query(
            "
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
        ORDER BY SUBSTRING(u.registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(u.registration_section, '[0-9]+')::INT, -1), SUBSTRING(u.registration_section, '[^0-9]*$')",
            $params
        );

        $return = [];
        foreach ($this->course_db->rows() as $row) {
            $return[$row['registration_section']] = intval($row['count']);
        }
        return $return;
    }

    public function getGradedPeerComponentsByRotatingSection($gradeable_id, $sections = []) {
        $where = "";
        $params = [];
        if (count($sections) > 0) {
            $where = "WHERE rotating_section IN " . $this->createParameterList(count($sections));
            $params = $sections;
        }
        $params[] = $gradeable_id;
        $this->course_db->query(
            "
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
        ORDER BY u.rotating_section",
            $params
        );

        $return = [];
        foreach ($this->course_db->rows() as $row) {
            $return[$row['rotating_section']] = intval($row['count']);
        }
        return $return;
    }

    public function existsThread($thread_id) {
        $this->course_db->query("SELECT 1 FROM threads where deleted = false AND id = ?", [$thread_id]);
        $result = $this->course_db->rows();
        return count($result) > 0;
    }

    public function existsPost($thread_id, $post_id) {
        $this->course_db->query("SELECT 1 FROM posts where thread_id = ? and id = ? and deleted = false", [$thread_id, $post_id]);
        $result = $this->course_db->rows();
        return count($result) > 0;
    }

    public function getDisplayUserInfoFromUserId($user_id) {
        $this->course_db->query("SELECT user_givenname, user_preferred_givenname, user_familyname, user_preferred_familyname, user_email, user_pronouns, display_pronouns FROM users WHERE user_id = ?", [$user_id]);
        $name_rows = $this->course_db->rows()[0];
        $ar = [];
        $ar["given_name"] = (empty($name_rows["user_preferred_givenname"])) ? $name_rows["user_givenname"]      : $name_rows["user_preferred_givenname"];
        $ar["family_name"]  = (empty($name_rows["user_preferred_familyname"]))  ? " " . $name_rows["user_familyname"] : " " . $name_rows["user_preferred_familyname"];
        $ar["user_email"] = $name_rows["user_email"];
        $ar["pronouns"] = $name_rows["user_pronouns"];
        $ar["display_pronouns"] = $name_rows["display_pronouns"];

        return $ar;
    }

    public function filterCategoryDesc($category_desc) {
        return str_replace("|", " ", $category_desc);
    }

    public function addNewCategory($category, $rank, $visibleDate = null) {
        //Can't get "RETURNING category_id" syntax to work
        $this->course_db->query("INSERT INTO categories_list (category_desc, rank, visible_date) VALUES (?, ?, ?) RETURNING category_id", [$this->filterCategoryDesc($category), $rank, $visibleDate]);
        $this->course_db->query("SELECT MAX(category_id) as category_id from categories_list");
        return $this->course_db->rows()[0];
    }

    public function deleteCategory($category_id) {
        // TODO, check if no thread is using current category
        $this->course_db->query("SELECT 1 FROM thread_categories WHERE category_id = ?", [$category_id]);
        if (count($this->course_db->rows()) == 0) {
            $this->course_db->query("DELETE FROM categories_list WHERE category_id = ?", [$category_id]);
            return true;
        }
        else {
            return false;
        }
    }

    public function editCategory($category_id, $category_desc, $category_color, $visible_date) {
        $this->course_db->beginTransaction();
        if (!is_null($category_desc)) {
            $this->course_db->query("UPDATE categories_list SET category_desc = ? WHERE category_id = ?", [$category_desc, $category_id]);
        }
        if (!is_null($category_color)) {
            $this->course_db->query("UPDATE categories_list SET color = ? WHERE category_id = ?", [$category_color, $category_id]);
        }
        if (!is_null($visible_date)) {
            if ($visible_date === "") {
                $visible_date = null;
            }

            $this->course_db->query("UPDATE categories_list SET visible_date = ? WHERE category_id = ?", [$visible_date, $category_id]);
        }

        $this->course_db->commit();
    }

    public function reorderCategories($categories_in_order) {
        $this->course_db->beginTransaction();
        foreach ($categories_in_order as $rank => $id) {
            $this->course_db->query("UPDATE categories_list SET rank = ? WHERE category_id = ?", [$rank, $id]);
        }
        $this->course_db->commit();
    }

    public function getCategories() {
        $this->course_db->query("SELECT *, extract(hours from now() - visible_date) as diff from categories_list ORDER BY rank ASC NULLS LAST, category_id");
        return $this->course_db->rows();
    }

    public function getRootPostOfNonMergedThread($thread_id, &$title, &$message) {
        $this->course_db->query("SELECT title FROM threads WHERE id = ? and merged_thread_id = -1 and merged_post_id = -1", [$thread_id]);
        $result_rows = $this->course_db->rows();
        if (count($result_rows) == 0) {
            $message = "Can't find thread";
            return false;
        }
        $title = $result_rows[0]['title'] . "\n";
        $this->course_db->query("SELECT id FROM posts where thread_id = ? and parent_id = -1", [$thread_id]);
        return $this->course_db->row()['id'];
    }

    public function mergeThread($parent_thread_id, $child_thread_id, &$message, &$child_root_post) {
        try {
            $this->course_db->beginTransaction();
            $parent_thread_title = null;
            $child_thread_title = null;
            if (!($parent_root_post = $this->getRootPostOfNonMergedThread($parent_thread_id, $parent_thread_title, $message))) {
                $this->course_db->rollback();
                return false;
            }
            if (!($child_root_post = $this->getRootPostOfNonMergedThread($child_thread_id, $child_thread_title, $message))) {
                $this->course_db->rollback();
                return false;
            }

            $child_thread_title = "Merged Thread Title: " . $child_thread_title . "\n";

            if ($child_root_post <= $parent_root_post) {
                $message = "Child thread must be newer than parent thread";
                $this->course_db->rollback();
                return false;
            }

            $children = [$child_root_post];
            $this->findChildren($child_root_post, $child_thread_id, $children);

            // $merged_post_id is PK of linking node and $merged_thread_id is immediate parent thread_id
            $this->course_db->query("UPDATE threads SET merged_thread_id = ?, merged_post_id = ? WHERE id = ?", [$parent_thread_id, $child_root_post, $child_thread_id]);
            foreach ($children as $post_id) {
                $this->course_db->query("UPDATE posts SET thread_id = ? WHERE id = ?", [$parent_thread_id,$post_id]);
            }
            $this->course_db->query("UPDATE posts SET parent_id = ?, content = ? || content WHERE id = ?", [$parent_root_post, $child_thread_title, $child_root_post]);

            $this->course_db->commit();
            return true;
        }
        catch (DatabaseException $dbException) {
             $this->course_db->rollback();
        }
        return false;
    }

    // ANONYMOUS ID QUERIES

    /**
     * Set gradeable-specific user anon_id
     *
     * @param array|string $user_ids
     * @param string $g_id
     * @param string $anon_id
     */
    public function insertGradeableAnonId($user_ids, $g_id, $anon_id = null) {
        $user_ids = is_array($user_ids) ? $user_ids : [$user_ids];
        foreach ($user_ids as $user_id) {
            if ($anon_id === null) {
                $this->core->getQueries()->getUserById($user_id)->getAnonId($g_id);
                continue;
            }
            $params = [$user_id, $g_id, $anon_id];
            $this->course_db->query("INSERT INTO gradeable_anon(user_id, g_id, anon_id) VALUES (?, ?, ?) ON CONFLICT DO NOTHING", $params);
        }
    }

    public function getAllAnonIdsByGradeable(string $g_id): array {
        $this->course_db->query("SELECT anon_id FROM gradeable_anon WHERE g_id=?", [$g_id]);
        return $this->course_db->rows();
    }

    public function getAllAnonIdsByGradeableWithUserIds(string $g_id): array {
        $this->course_db->query("SELECT anon_id, user_id FROM gradeable_anon WHERE g_id=?", [$g_id]);
        return $this->course_db->rows();
    }

    public function getAllTeamAnonIdsByGradeable(string $g_id): array {
        $this->course_db->query("SELECT team_id, anon_id FROM gradeable_teams WHERE g_id=?", [$g_id]);
        return $this->course_db->rows();
    }

    /**
     * Get gradeable-specific user anon_id
     *
     * @param array|string $user_ids
     * @param string $g_id
     */
    public function getAnonId($user_ids, $g_id) {
        $params = (is_array($user_ids)) ? $user_ids : [$user_ids];
        $question_marks = $this->createParameterList(count($params));
        $params[] = $g_id;
        if (count($params) < 2) {
            return [];
        }
        $this->course_db->query("SELECT user_id, anon_id FROM gradeable_anon WHERE user_id IN {$question_marks} AND g_id=?", $params);
        $return = [];
        foreach ($this->course_db->rows() as $id_map) {
            $return[$id_map['user_id']] = $id_map['anon_id'];
        }
        return $return;
    }

    public function getTeamAnonId($team_id) {
        $params = (is_array($team_id)) ? $team_id : [$team_id];

        $question_marks = $this->createParameterList(count($params));
        $this->course_db->query("SELECT team_id, anon_id FROM gradeable_teams WHERE team_id IN {$question_marks}", $params);
        $return = [];
        foreach ($this->course_db->rows() as $id_map) {
            $return[$id_map['team_id']] = $id_map['anon_id'];
        }
        return $return;
    }

    public function getUserFromAnon($anon_id, $g_id) {
        $params = is_array($anon_id) ? $anon_id : [$anon_id];
        $question_marks = $this->createParameterList(count($params));
        $params[] = $g_id;
        $this->course_db->query("SELECT anon_id, user_id FROM gradeable_anon WHERE anon_id IN {$question_marks} AND g_id=?", $params);
        $return = [];
        foreach ($this->course_db->rows() as $id_map) {
            $return[$id_map['anon_id']] = $id_map['user_id'];
        }
        return $return;
    }

    public function getTeamIdFromAnonId($anon_id) {
        $params = is_array($anon_id) ? $anon_id : [$anon_id];

        $question_marks = $this->createParameterList(count($params));
        $this->course_db->query("SELECT anon_id, team_id FROM gradeable_teams WHERE anon_id IN {$question_marks}", $params);
        $return = [];
        foreach ($this->course_db->rows() as $id_map) {
            $return[$id_map['anon_id']] = $id_map['team_id'];
        }
        return $return;
    }

    public function getAllAnonIds() {
        $this->course_db->query("SELECT anon_id FROM gradeable_anon");
        return $this->course_db->rows();
    }

    public function getSubmitterIdFromAnonId(string $anon_id, string $g_id = null) {
        return $this->getUserFromAnon($anon_id, $g_id)[$anon_id] ??
            $this->getTeamIdFromAnonId($anon_id)[$anon_id] ??
                null;
    }

    // NOTIFICATION/EMAIL QUERIES

    /**
     * get all users' ids
     *
     * @Param string $current_user_id
     * @return array
     */
    public function getAllUsersIds() {
        $query = "SELECT user_id FROM users";
        $this->course_db->query($query);
        return $this->rowsToArray($this->course_db->rows());
    }

    /**
     * Get all users with a preference
     *
     * @param  string $column
     * @return array
     */
    public function getAllUsersWithPreference(string $column) {
        $preferences = [
            'merge_threads',
            'all_new_threads',
            'all_new_posts',
            'all_modifications_forum',
            'reply_in_post_thread',
            'team_invite',
            'team_joined_email',
            'team_member_submission',
            'self_notification',
            'merge_threads_email',
            'all_new_threads_email',
            'all_new_posts_email',
            'all_modifications_forum_email',
            'reply_in_post_thread_email',
            'team_invite_email',
            'team_joined_email',
            'team_member_submission_email',
            'self_registration_email',
            'self_notification_email',
        ];
        $query = "SELECT user_id FROM notification_settings WHERE {$column} = 'true'";
        $this->course_db->query($query);
        if (!in_array($column, $preferences)) {
            throw new DatabaseException("Given column, {$column}, is not a valid column", $query);
        }
        return $this->rowsToArray($this->course_db->rows());
    }

    /**
     * Gets the user's row in the notification settings table
     *
     * @param string[] $user_ids
     * @return array
     */
    public function getUsersNotificationSettings(array $user_ids) {
        $params = $user_ids;
        $user_id_query = $this->createParameterList(count($user_ids));
        $query = "SELECT * FROM notification_settings WHERE user_id in " . $user_id_query;
        $this->course_db->query($query, $params);
        return $this->course_db->rows();
    }

    /**
     * Gets All Parent Authors who this user responded to
     *
     * @param string $post_author_id current_user_id
     * @param string $post_id        the parent post id
     */
    public function getAllParentAuthors(string $post_author_id, string $post_id) {
        $params = [$post_id];
        $query = "SELECT * FROM
                  (WITH RECURSIVE parents AS (
                  SELECT
                    author_user_id, parent_id, id FROM  posts
                  WHERE id = ?
                  UNION SELECT
                    p.author_user_id, p.parent_id, p.id
                  FROM
                    posts p
                   INNER JOIN parents pa ON pa.parent_id = p.id
                  ) SELECT DISTINCT
                    author_user_id AS user_id
                  FROM
                    parents) AS parents;";
        $this->course_db->query($query, $params);
        return $this->rowsToArray($this->course_db->rows());
    }

    /**
     * returns all authors who want to be notified if a post has been made in a thread they have posted in
     *
     * @param  int $thread_id
     * @param  string $column    ("reply_in_thread" or "reply_in_thread_email")
     * @return array
     */
    public function getAllThreadAuthors($thread_id, $column) {
        $params = [$thread_id];
        $query = "SELECT author_user_id AS user_id FROM posts WHERE thread_id = ? AND
                  EXISTS (
                  SELECT user_id FROM notification_settings WHERE
                  user_id = author_user_id AND {$column} = 'true');";
        if ($column != 'reply_in_post_thread' && $column != 'reply_in_post_thread_email') {
            throw new DatabaseException("Given column, {$column}, is not a valid column", $query, $params);
        }
        $this->course_db->query($query, $params);
        return $this->rowsToArray($this->course_db->rows());
    }

    /*
     * helper function to convert rows array to one dimensional array of user ids
     *
     * @return array
     */
    protected function rowsToArray($rows) {
        $result = [];
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                $result[] = $value;
            }
        }
        return $result;
    }

    /**
     * Sends notifications to all recipients
     *
     * @param array $flattened_notifications
     * @param int   $notification_count
     */
    public function insertNotifications(array $flattened_notifications, int $notification_count) {
        // PDO Placeholders
        $row_string = "(?, ?, ?, current_timestamp, ?, ?)";
        $value_param_string = implode(', ', array_fill(0, $notification_count, $row_string));
        $this->course_db->query(
            "
            INSERT INTO notifications(component, metadata, content, created_at, from_user_id, to_user_id)
            VALUES " . $value_param_string,
            $flattened_notifications
        );
    }

    /**
     * Queues emails for all given recipients to be sent by email job
     *
     * @param array $flattened_params An array of email information.
     *  Rather than being a 2D array, each email information comes one after another
     *  in a 1D array.
     *  The order of the information for each email is:
     *      Email subject
     *      Email body
     *      Time email was created
     *      User_id of the email's receiver (if Submitty user)
     *      Name of the email's receiver (if non Submitty user)
     *      Email address of receiver
     *      Term email is being sent
     *      Course email belongs to
     * @param int   $email_count
     */
    public function insertEmails(array $flattened_params, int $email_count) {
        // PDO Placeholders
        $row_string = "(?, ?, current_timestamp, ?, ?, ?, ?, ?)";
        $value_param_string = implode(', ', array_fill(0, $email_count, $row_string));
        $this->submitty_db->query(
            "
            INSERT INTO emails(subject, body, created, user_id, to_name, email_address, term, course)
            VALUES " . $value_param_string,
            $flattened_params
        );
    }

    /**
     * Returns notifications for a user
     *
     * @param  string $user_id
     * @param  bool   $show_all
     * @return Notification[]
     */
    public function getUserNotifications($user_id, $show_all) {
        if ($show_all) {
            $seen_status_query = "true";
        }
        else {
            $seen_status_query = "seen_at is NULL";
        }
        $this->course_db->query(
            "SELECT id, component, metadata, content,
                (case when seen_at is NULL then false else true end) as seen,
                (extract(epoch from current_timestamp) - extract(epoch from created_at)) as elapsed_time, created_at
                FROM notifications WHERE to_user_id = ? and {$seen_status_query} ORDER BY created_at DESC",
            [$user_id]
        );
        $rows = $this->course_db->rows();
        $results = [];
        foreach ($rows as $row) {
            $results[] = Notification::createViewOnlyNotification(
                $this->core,
                [
                    'id' => $row['id'],
                    'component' => $row['component'],
                    'metadata' => $row['metadata'],
                    'content' => $row['content'],
                    'seen' => $row['seen'],
                    'elapsed_time' => $row['elapsed_time'],
                    'created_at' => $row['created_at']
                ]
            );
        }
        return $results;
    }

    public function getNotificationInfoById($user_id, $notification_id) {
        $this->course_db->query("SELECT metadata FROM notifications WHERE to_user_id = ? and id = ?", [$user_id, $notification_id]);
        return $this->course_db->row();
    }

    public function getUnreadNotificationsCount($user_id, $component) {
        $parameters = [$user_id];
        if (is_null($component)) {
            $component_query = "true";
        }
        else {
            $component_query = "component = ?";
            $parameters[] = $component;
        }
        $this->course_db->query("SELECT count(*) FROM notifications WHERE to_user_id = ? and seen_at is NULL and {$component_query}", $parameters);
        return $this->course_db->row()['count'];
    }

    /**
     * Marks $user_id notifications as seen
     *
     * @param string $user_id
     * @param int    $notification_id if $notification_id != -1 then marks corresponding as seen else mark all notifications as seen
     */
    public function markNotificationAsSeen($user_id, $notification_id, $thread_id = -1) {
        $parameters = [];
        $parameters[] = $user_id;
        if ($thread_id != -1) {
            $id_query = "metadata::json->>'thread_id' = ?";
            $parameters[] = $thread_id;
        }
        elseif ($notification_id == -1) {
            $id_query = "true";
        }
        else {
            $id_query = "id = ?";
            $parameters[] = $notification_id;
        }
        $this->course_db->query(
            "UPDATE notifications SET seen_at = current_timestamp
                WHERE to_user_id = ? and seen_at is NULL and {$id_query}",
            $parameters
        );
    }

    /**
     * Returns true if the student was ever in the course,
     * even if they are in the null section now.
     * @param string $user_id The name of the user.
     * @param string $course The course we're looking at.
     * @param string $term The term we're looking t.
     * @return bool True if the student was ever in the course, false otherwise.
     */
    public function wasStudentEverInCourse(
        string $user_id,
        string $course,
        string $term
    ): bool {
        $this->submitty_db->query("
                SELECT user_id
                FROM courses_users
                WHERE user_id=? and course=? and term=?;
            ", [$user_id, $course, $term]);
        $row = $this->submitty_db->row();
        return count($row) > 0;
    }

    /**
     * Returns the previous registration section the student was in.
     * If a student was ever not in the null section, then if they are in the null
     * section now, this value is guaranteed to be nonnull as their previous section
     * would be outside the null section.
     *
     * If the previous registration section was deleted or renamed, then this function
     * returns the top registration section of the course.
     *
     * @param string $user_id The name of the user we're querying.
     * @param string $course The course we are looking in.
     * @param string $term The term we are looking at.
     * @return string The previous section the student was in.
     */
    public function getPreviousRegistrationSection(
        string $user_id,
        string $term,
        string $course
    ): string|null {
        $this->submitty_db->query("
            WITH
                Term (term) AS (VALUES (?)),
                Course (course) AS (VALUES (?)),
                PreviousRegSection AS (
                    SELECT previous_registration_section, course
                    FROM courses_users
                    WHERE user_id=? and term=(table Term) and course=(table Course)
                ),
                TopSection AS (
                    SELECT registration_section_id, course
                    FROM courses_registration_sections
                    WHERE term=(table Term) and course=(table Course)
                    ORDER BY registration_section_id ASC
                    LIMIT 1
                )
            SELECT
                (CASE
                    WHEN (
                        SELECT registration_section_id
                        FROM courses_registration_sections
                        WHERE
                            term = (table Term)
                            and course = (table Course)
                            and registration_section_id = PreviousRegSection.previous_registration_section
                        ) IS NOT NULL THEN PreviousRegSection.previous_registration_section
                    ELSE TopSection.registration_section_id
                END) AS rejoin_section
            FROM
                Course
                LEFT JOIN PreviousRegSection on Course.course = PreviousRegSection.course
                LEFT JOIN TopSection on Course.course = TopSection.course;
        ", [$term, $course, $user_id]);
        return $this->submitty_db->row()["rejoin_section"];
    }

    /**
     * Returns the previous rotating section the student was in.
     * If the student was ever in a rotating section, this value is guaranteed
     * to be nonnull if the student currently has a null rotating section.
     *
     * If the previous rotating section was deleted or renamed, then this function
     * returns the top rotating section of the course.
     *
     * @param string $user_id The name of the user we're querying.
     * @return string|null The rotating section the student was last in.
     */
    public function getPreviousRotatingSection(string $user_id): string|null {
        $this->course_db->query("
            SELECT previous_rotating_section
            FROM users
            WHERE user_id=?;
        ", [$user_id]);
        $previous_section = $this->course_db->row()["previous_rotating_section"];

        $this->course_db->query("
            SELECT sections_rotating_id
            FROM sections_rotating
            WHERE sections_rotating_id = ?;
        ", [$previous_section]);
        if ($this->course_db->getRowCount() > 0) {
            // Rotating section still valid, time to return!
            return $previous_section;
        }

        // Else we return the top rotating section.
        $this->course_db->query("
            SELECT sections_rotating_id
            FROM sections_rotating
            ORDER BY sections_rotating_id ASC
            LIMIT 1;
        ");
        if ($this->course_db->getRowCount() > 0) {
            return $this->course_db->row()["sections_rotating_id"];
        }
        else {
            return null; // Course has no rotating sections.
        }
    }

    /**
     * Determines if a course is 'active' or if it was dropped.
     *
     * This is used to filter out courses displayed on the home screen, for when
     * a student has dropped a course.  SQL query checks for user_group=4 so
     * that only students are considered.  Returns false when course is dropped.
     * Returns true when course is still active, or user is not a student.
     * If course or semester is null, this method instead checks for if the student
     * is 'active' in any course
     *
     * @param  string $user_id
     * @param  string $course
     * @param  string $semester
     * @return bool
     */
    public function checkStudentActiveInCourse($user_id, $course, $semester): bool {
        if ($course == null || $semester == null) {
            $this->submitty_db->query(
                "
                SELECT
                    CASE WHEN registration_section IS NULL AND user_group=4 THEN FALSE
                    ELSE TRUE
                    END
                AS active
                FROM courses_users WHERE user_id=?",
                [$user_id]
            );
            $row = $this->submitty_db->row();
            return count($row) > 0 && $row['active'];
        }
        $this->submitty_db->query(
            "
            SELECT
                CASE WHEN registration_section IS NULL AND user_group=4 THEN FALSE
                ELSE TRUE
                END
            AS active
            FROM courses_users WHERE user_id=? AND course=? AND term=?",
            [$user_id, $course, $semester]
        );
        $row = $this->submitty_db->row();
        return count($row) > 0 && $row['active'];
    }

    public function checkIsInstructorInCourse($user_id, $course, $semester) {
        $this->submitty_db->query(
            "
            SELECT
                CASE WHEN user_group=1 THEN TRUE
                ELSE FALSE
                END
            AS is_instructor
            FROM courses_users WHERE user_id=? AND course=? AND term=?",
            [$user_id, $course, $semester]
        );
        return count($this->submitty_db->rows()) >= 1 &&
            $this->submitty_db->row()['is_instructor'];
    }

    public function getGradeInquiryStatus($user_id, $gradeable_id) {
        $this->course_db->query("SELECT * FROM grade_inquiries WHERE user_id = ? AND g_id = ? ", [$user_id, $gradeable_id]);
        return ($this->course_db->getRowCount() > 0) ? $this->course_db->row()['status'] : 0;
    }

    public function getGradeInquiriesUsers(string $gradeable_id, bool $ungraded_only = false, int $component_id = -1) {
        $parameters = [];
        $parameters[] = $gradeable_id;
        $ungraded_query = "";
        if ($ungraded_only) {
            $ungraded_query = "AND status = ? ";
            $parameters[] = GradeInquiry::STATUS_ACTIVE;
        }
        $component_query = "";
        if ($component_id !== -1) {
            $component_query = "AND (gc_id IS NULL OR gc_id = ?) ";
            $parameters[] = $component_id;
        }

        $this->course_db->query("SELECT user_id FROM grade_inquiries WHERE g_id = ? " . $ungraded_query . $component_query, $parameters);
        return $this->rowsToArray($this->course_db->rows());
    }


    /**
     * insert a new grade inquiry for a submitter
     * @return string the id of the first new post inserted of the new grade inquiry
     */
    public function insertNewGradeInquiry(GradedGradeable $graded_gradeable, User $sender, string $initial_message, $gc_id): string {
        $params = [$graded_gradeable->getGradeableId(), $graded_gradeable->getSubmitter()->getId(), GradeInquiry::STATUS_ACTIVE, $gc_id];
        $submitter_col = $graded_gradeable->getSubmitter()->isTeam() ? 'team_id' : 'user_id';
        try {
            $this->course_db->query("INSERT INTO grade_inquiries(g_id, timestamp, $submitter_col, status, gc_id) VALUES (?, current_timestamp, ?, ?, ?)", $params);
            $grade_inquiry_id = $this->course_db->getLastInsertId();
            return $this->insertNewGradeInquiryPost($grade_inquiry_id, $sender->getId(), $initial_message, $gc_id);
        }
        catch (DatabaseException $dbException) {
            if ($this->course_db->inTransaction()) {
                $this->course_db->rollback();
            }
            throw $dbException;
        }
    }

    public function getNumberGradeInquiries($gradeable_id, $is_grade_inquiry_per_component_allowed = true) {
        $grade_inquiry_all_only_query = !$is_grade_inquiry_per_component_allowed ? ' AND gc_id IS NULL' : '';
        $this->course_db->query("SELECT COUNT(*) AS cnt FROM grade_inquiries WHERE g_id = ? AND status = -1" . $grade_inquiry_all_only_query, [$gradeable_id]);
        return ($this->course_db->row()['cnt']);
    }

    /**
     * get the grader and the count of the gradeable component for inquiry
     * If the grade is not-by component, function will return all graders who were involved in grading the student
     * @return array<string, int> of gcd_grader_id and count of unresolved grade inquiries of the grader
     */
    public function getGraderofGradeInquiry(string $gradeable_id, bool $is_grade_inquiry_per_component_allowed = true): array {
        $return = [];
        if ($is_grade_inquiry_per_component_allowed) {
            $this->course_db->query("
                SELECT
                    count(gcd.*),
                    gcd.gcd_grader_id
                FROM grade_inquiries gi
                INNER JOIN gradeable_component_data gcd ON (
                    gi.gc_id = gcd.gc_id
                )
                INNER JOIN gradeable_data gd ON (
                    gcd.gd_id = gd.gd_id
                    AND gi.user_id = gd.gd_user_id
                )
                WHERE
                    gi.status = -1
                    AND gi.g_id = ?
                GROUP BY gcd.gcd_grader_id
            ", [$gradeable_id]);
            foreach ($this->course_db->rows() as $row) {
                $return[$row['gcd_grader_id']] = $row['count'];
            }
            return $return;
        }
        else {
            $this->course_db->query("
                SELECT
                    count(result.*),
                    result.gcd_grader_id
                FROM (
                    SELECT
                        DISTINCT gi.user_id,
                        gcd.gcd_grader_id
                    FROM grade_inquiries gi
                    INNER JOIN gradeable_data gd ON (
                        gi.g_id = gd.g_id
                        AND gi.user_id = gd.gd_user_id
                    )
                    INNER JOIN gradeable_component_data gcd ON (
                        gd.gd_id = gcd.gd_id
                    )
                    WHERE 
                        gi.status = -1 
                        AND gi.g_id = ?
                ) AS result
                GROUP BY result.gcd_grader_id
            ", [$gradeable_id]);
            foreach ($this->course_db->rows() as $row) {
                $return[$row['gcd_grader_id']] = $row['count'];
            }
            return $return;
        }
    }

    /*
     * This is used to convert one of the by-component inquiries per student for a gradeable to a non-component inquiry.
     * This allows graders to still respond to by component inquiries if in no-component mode.
     */
    public function convertInquiryComponentId($gradeable) {
        $this->course_db->query("UPDATE grade_inquiries
                                        SET gc_id = NULL
                                        WHERE id IN (
                                            SELECT a.id
                                            FROM (
                                                SELECT DISTINCT ON (t.user_id) user_id, t.id
                                                FROM (
                                                    SELECT *
                                                    FROM grade_inquiries
                                                    ORDER BY id
                                                ) t
                                                WHERE t.g_id = ?
                                            ) a
                                        );
", [$gradeable->getId()]);
    }


    /**
     * This is used to revert non-component inquiries back to by-component inquiries
     * convertInquiryComponentId function modifies the lowest gc_id of each student of the gradeable to null.
     * If instructor decides to switch to non-component from by-component then switch back to by-component,
     * this revertInquiryComponentId function will fetch the gc_id from grade_inquiry_discussion and update accordingly.
     * @param Gradeable $gradeable
     */
    public function revertInquiryComponentId(Gradeable $gradeable): void {
        $this->course_db->query("
            UPDATE grade_inquiries
            SET gc_id = (
                SELECT gid.gc_id
                FROM grade_inquiry_discussion gid
                WHERE grade_inquiries.id = gid.grade_inquiry_id
            )
            WHERE
                grade_inquiries.gc_id IS NULL
                AND grade_inquiries.g_id = ?
        ", [$gradeable->getId()]);
    }

    public function getGradeInquiryDiscussions(array $grade_inquiries) {
        if (count($grade_inquiries) == 0) {
            return [];
        }
        $grade_inquiry_ids = $this->createParameterList(count($grade_inquiries));
        $params = array_map(
            function ($grade_inquiry) {
                return $grade_inquiry->getId();
            },
            $grade_inquiries
        );
        $this->course_db->query("SELECT * FROM grade_inquiry_discussion WHERE grade_inquiry_id IN $grade_inquiry_ids AND deleted=false ORDER BY timestamp ASC ", $params);
        $result = [];
        foreach ($params as $id) {
            $result[$id] = array_filter(
                $this->course_db->rows(),
                function ($v) use ($id) {
                    return $v['grade_inquiry_id'] == $id;
                }
            );
        }
        return $result;
    }

    public function insertNewGradeInquiryPost($grade_inquiry_id, $user_id, $content, $gc_id) {
        $params = [$grade_inquiry_id, $user_id, $content, $gc_id];
        $this->course_db->query("INSERT INTO grade_inquiry_discussion(grade_inquiry_id, timestamp, user_id, content, gc_id) VALUES (?, current_timestamp, ?, ?, ?)", $params);
        return $this->course_db->getLastInsertId();
    }

    public function getGradeInquiryPost($post_id) {
        $this->course_db->query(
            "SELECT * FROM grade_inquiry_discussion WHERE id = ?",
            [$post_id]
        );
        return $this->course_db->row();
    }

    public function saveGradeInquiry(GradeInquiry $grade_inquiry) {
        $this->course_db->query("UPDATE grade_inquiries SET timestamp = current_timestamp, status = ? WHERE id = ?", [$grade_inquiry->getStatus(), $grade_inquiry->getId()]);
    }

    public function deleteGradeInquiry(GradeInquiry $grade_inquiry) {
        $grade_inquiry_id = $grade_inquiry->getId();
        $this->course_db->query("DELETE FROM grade_inquiry_discussion WHERE grade_inquiry_id = ?", [$grade_inquiry_id]);
        $this->course_db->query("DELETE FROM grade_inquiries WHERE id = ?", [$grade_inquiry_id]);
    }


    public function deleteGradeable($g_id) {
        $this->course_db->beginTransaction();
        $this->course_db->query("UPDATE electronic_gradeable SET eg_depends_on = null,
                                eg_depends_on_points = null WHERE eg_depends_on=?", [$g_id]);
        $this->course_db->query("DELETE FROM gradeable WHERE g_id=?", [$g_id]);
        $this->course_db->commit();
    }

    /**
     * Gets a single Gradeable instance by id
     *
     * @param  string $id The gradeable's id
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
     *
     * @param  string[]|null        $ids       ids of the gradeables to retrieve
     * @param  string[]|string|null $sort_keys An ordered list of keys to sort by (i.e. `id` or `grade_start_date DESC`)
     * @return \Iterator<Gradeable>  Iterates across array of Gradeables retrieved
     * @throws \InvalidArgumentException If any Gradeable or Component fails to construct
     * @throws ValidationException If any Gradeable or Component fails to construct
     */
    public function getGradeableConfigs($ids, $sort_keys = ['id']) {
        if ($ids === []) {
            return new \EmptyIterator();
        }
        if ($ids === null) {
            $ids = [];
        }

        // Generate the selector statement
        $selector = '';
        if (count($ids) > 0) {
            $place_holders = implode(',', array_fill(0, count($ids), '?'));
            $selector = "WHERE g.g_id IN ($place_holders)";
        }

        // Generate the ORDER BY clause
        $order = self::generateOrderByClause($sort_keys, []);

        $query = "
            SELECT
              g.g_id AS id,
              g_title AS title,
              g_instructions_url AS instructions_url,
              g_overall_ta_instructions AS ta_instructions,
              g_gradeable_type AS type,
              g_grader_assignment_method AS grader_assignment_method,
              g_ta_view_start_date AS ta_view_start_date,
              g_grade_start_date AS grade_start_date,
              g_grade_due_date AS grade_due_date,
              g_grade_released_date AS grade_released_date,
              g_min_grading_group AS min_grading_group,
              g_syllabus_bucket AS syllabus_bucket,
              g_allow_custom_marks AS allow_custom_marks,
              g_allowed_minutes AS allowed_minutes,
              eg.*,
              gamo.*,
              gc.*,
              pgp.*,
              (SELECT COUNT(*) AS cnt FROM grade_inquiries WHERE g_id=g.g_id AND status = -1) AS active_grade_inquiries_count,
              (SELECT EXISTS (SELECT 1 FROM gradeable_data WHERE g_id=g.g_id)) AS any_manual_grades
            FROM gradeable g
              LEFT JOIN (
                SELECT
                  g_id AS eg_g_id,
                  eg_config_path AS autograding_config_path,
                  eg_is_repository AS vcs,
                  eg_using_subdirectory AS using_subdirectory,
                  eg_vcs_subdirectory AS vcs_subdirectory,
                  eg_vcs_partial_path AS vcs_partial_path,
                  eg_vcs_host_type AS vcs_host_type,
                  eg_team_assignment AS team_assignment,
                  eg_max_team_size AS team_size_max,
                  eg_team_lock_date AS team_lock_date,
                  eg_grade_inquiry_start_date AS grade_inquiry_start_date,
                  eg_grade_inquiry_due_date AS grade_inquiry_due_date,
                  eg_grade_inquiry_allowed AS grade_inquiry_allowed,
                  eg_grade_inquiry_per_component_allowed AS grade_inquiry_per_component_allowed,
                  eg_thread_ids AS discussion_thread_ids,
                  eg_has_discussion AS discussion_based,
                  eg_use_ta_grading AS ta_grading,
                  eg_student_view AS student_view,
                  eg_student_view_after_grades as student_view_after_grades,
                  eg_student_download AS student_download,
                  eg_student_submit AS student_submit,
                  eg_instructor_blind AS instructor_blind,
                  eg_limited_access_blind AS limited_access_blind,
                  eg_peer_blind AS peer_blind,
                  eg_submission_open_date AS submission_open_date,
                  eg_submission_due_date AS submission_due_date,
                  eg_has_due_date AS has_due_date,
                  eg_has_release_date as has_release_date,
                  eg_late_days AS late_days,
                  eg_allow_late_submission AS late_submission_allowed,
                  eg_precision AS precision,
                  eg_hidden_files as hidden_files,
                  eg_depends_on as depends_on,
                  eg_depends_on_points as depends_on_points
                FROM electronic_gradeable
              ) AS eg ON g.g_id=eg.eg_g_id
                LEFT JOIN (
                SELECT
                  g_id AS pgp_g_id,
                  autograding, 
                  rubric, 
                  files,
                  solution_notes,
                  discussion
                FROM peer_grading_panel
                GROUP BY pgp_g_id
              ) AS pgp ON g.g_id=pgp.pgp_g_id
              LEFT JOIN (
                SELECT
                  g_id AS gamo_g_id,
                  json_agg(user_id) AS gamo_users,
                  json_agg(allowed_minutes) AS gamo_minutes
                FROM gradeable_allowed_minutes_override
                GROUP BY gamo_g_id
              ) AS gamo ON g.g_id=gamo.gamo_g_id
              LEFT JOIN (
                SELECT
                  g_id AS gc_g_id,
                  json_agg(gc.gc_id) AS array_id,
                  json_agg(gc_title) AS array_title,
                  json_agg(gc_ta_comment) AS array_ta_comment,
                  json_agg(gc_student_comment) AS array_student_comment,
                  json_agg(gc_lower_clamp) AS array_lower_clamp,
                  json_agg(gc_default) AS array_default,
                  json_agg(gc_max_value) AS array_max_value,
                  json_agg(gc_upper_clamp) AS array_upper_clamp,
                  json_agg(gc_is_text) AS array_text,
                  json_agg(gc_is_peer) AS array_peer_component,
                  json_agg(gc_order) AS array_order,
                  json_agg(gc_page) AS array_page,
                  json_agg(gc_is_itempool_linked) AS array_is_itempool_linked,
                  json_agg(gc_itempool) AS array_itempool,
                    json_agg(EXISTS(
                      SELECT gc_id
                      FROM gradeable_component_data
                      WHERE gc_id=gc.gc_id)) AS array_any_grades,
                  json_agg(gcm.array_id) AS array_mark_id,
                  json_agg(gcm.array_points) AS array_mark_points,
                  json_agg(gcm.array_title) AS array_mark_title,
                  json_agg(gcm.array_publish) AS array_mark_publish,
                  json_agg(gcm.array_order) AS array_mark_order,
                  json_agg(gcm.array_any_receivers) AS array_mark_any_receivers
                FROM gradeable_component gc
                LEFT JOIN (
                  SELECT
                    gc_id AS gcm_gc_id,
                    json_agg(gcm_id) AS array_id,
                    json_agg(gcm_points) AS array_points,
                    json_agg(gcm_note) AS array_title,
                    json_agg(gcm_publish) AS array_publish,
                    json_agg(gcm_order) AS array_order,
                    json_agg(EXISTS(
                      SELECT gcm_id
                      FROM gradeable_component_mark_data
                      WHERE gcm_id=in_gcm.gcm_id)) AS array_any_receivers
                    FROM gradeable_component_mark AS in_gcm
                  GROUP BY gcm_gc_id
                ) AS gcm ON gcm.gcm_gc_id=gc.gc_id
                GROUP BY g_id
              ) AS gc ON gc.gc_g_id=g.g_id
             $selector
             $order";

        $gradeable_constructor = function ($row) {
            if (!isset($row['eg_g_id']) && $row['type'] === GradeableType::ELECTRONIC_FILE) {
                throw new DatabaseException("Electronic gradeable didn't have an entry in the electronic_gradeable table!");
            }

            // Finally, create the gradeable
            $gradeable = new \app\models\gradeable\Gradeable($this->core, $row);
            $overrides = [];
            $users = json_decode($row['gamo_users'] ?? "[]");
            $minutes = json_decode($row['gamo_minutes'] ?? "[]");
            if ($users !== null) {
                for ($i = 0; $i < count($users); $i++) {
                    $overrides[] = [
                        'user_id' => $users[$i],
                        'allowed_minutes' => $minutes[$i]
                    ];
                }
            }
            $gradeable->setAllowedMinutesOverrides($overrides);

            // Construct the components
            $component_properties = [
                'id',
                'title',
                'ta_comment',
                'student_comment',
                'lower_clamp',
                'default',
                'max_value',
                'upper_clamp',
                'text',
                'peer_component',
                'order',
                'page',
                'is_itempool_linked',
                'itempool',
                'any_grades'
            ];
            $mark_properties = [
                'id',
                'points',
                'title',
                'publish',
                'order',
                'any_receivers'
            ];
            $component_mark_properties = array_map(
                function ($value) {
                    return 'mark_' . $value;
                },
                $mark_properties
            );

            // Unpack the component data
            $unpacked_component_data = [];
            foreach (array_merge($component_properties, $component_mark_properties) as $property) {
                $unpacked_component_data[$property] = json_decode($row['array_' . $property]) ?? [];
            }

            // Create the components
            $components = [];
            for ($i = 0; $i < count($unpacked_component_data['id']); ++$i) {
                // Transpose a single component at a time
                $component_data = [];
                foreach ($component_properties as $property) {
                    $component_data[$property] = $unpacked_component_data[$property][$i];
                }

                // Create the component instance
                $component = new Component($this->core, $gradeable, $component_data);

                // Unpack the mark data
                if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                    $unpacked_mark_data = [];
                    foreach ($mark_properties as $property) {
                        $unpacked_mark_data[$property] = $unpacked_component_data['mark_' . $property][$i];
                    }

                    // If there are no marks, there will be a single 'null' element in the unpacked arrays
                    if ($unpacked_mark_data['id'][0] !== null) {
                        // Create the marks
                        $marks = [];
                        for ($j = 0; $j < count($unpacked_mark_data['id']); ++$j) {
                            // Transpose a single mark at a time
                            $mark_data = [];
                            foreach ($mark_properties as $property) {
                                $mark_data[$property] = $unpacked_mark_data[$property][$j];
                            }

                            // Create the mark instance
                            $marks[] = new Mark($this->core, $component, $mark_data);
                        }
                        $component->setMarksFromDatabase($marks);
                    }
                }

                $components[] = $component;
            }

            // Set the components
            $gradeable->setComponentsFromDatabase($components);

            return $gradeable;
        };

        return $this->course_db->queryIterator(
            $query,
            $ids,
            $gradeable_constructor
        );
    }

    /**
     * Gets whether a gradeable has any manual grades yet
     *
     * @param  string $g_id id of the gradeable
     * @return bool True if the gradeable has manual grades
     */
    public function getGradeableHasGrades($g_id) {
        $this->course_db->query('SELECT EXISTS (SELECT 1 FROM gradeable_data WHERE g_id=?)', [$g_id]);
        return $this->course_db->row()['exists'];
    }

    /**
     * Returns array of User objects for users with given User IDs
     *
     * @param  string[] $user_ids
     * @return User[] The user objects, indexed by user id
     */
    public function getUsersById(array $user_ids) {
        if (count($user_ids) === 0) {
            return [];
        }

        // Generate placeholders for each team id
        $place_holders = implode(',', array_fill(0, count($user_ids), '?'));
        $query = "
            SELECT u.*, sr.grading_registration_sections
            FROM users u
            LEFT JOIN (
                SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
                FROM grading_registration
                GROUP BY user_id
            ) as sr ON u.user_id=sr.user_id
            WHERE u.user_id IN ($place_holders)";
        $this->course_db->query($query, array_values($user_ids));

        $users = [];
        foreach ($this->course_db->rows() as $user) {
            if (isset($user['grading_registration_sections'])) {
                $user['grading_registration_sections'] = $this->course_db->fromDatabaseToPHPArray($user['grading_registration_sections']);
            }
            $user = new User($this->core, $user);
            $users[$user->getId()] = $user;
        }

        return $users;
    }

    public function getUsersOrTeamsById(array $ids) {
        $users = $this->getUsersById($ids);
        if (empty($users)) {
            return $this->getTeamsById($ids);
        }
        return $users;
    }

    /**
     * Return array of Team objects for teams with given Team IDs
     *
     * @param  string[] $team_ids
     * @return Team[] The team objects, indexed by team id
     */
    public function getTeamsById(array $team_ids) {
        if (count($team_ids) === 0) {
            return [];
        }

        // Generate placeholders for each team id
        $place_holders = implode(',', array_fill(0, count($team_ids), '?'));
        $query = "
            SELECT gt.team_id, gt.registration_section, gt.rotating_section, gt.team_name, COALESCE(NULLIF(jsonb_agg(u)::text, '[null]'), '[]')::jsonb AS users
            FROM gradeable_teams gt
              LEFT JOIN
                (SELECT t.team_id, t.state, u.*
                 FROM teams t
                   JOIN users u ON t.user_id = u.user_id
                ) AS u ON gt.team_id = u.team_id
            WHERE gt.team_id IN ($place_holders)
            GROUP BY gt.team_id";

        $this->course_db->query($query, array_values($team_ids));

        $teams = [];
        foreach ($this->course_db->rows() as $row) {
            // Get the user data for the team
            $row['users'] = json_decode($row['users'], true);

            // Create the team with the query results and users array
            $team = new Team($this->core, $row);
            $teams[$team->getId()] = $team;
        }

        return $teams;
    }

    /**
     * Gets a user or team submitter by id
     *
     * @param  string $id User or team id
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
     *
     * @param  string[] $ids User or team ids
     * @return Submitter[]
     */
    public function getSubmittersById(array $ids) {
        //Get Submitter for each id in ids
        return array_map(
            function ($id) {
                return $this->getSubmitterById($id);
            },
            $ids
        );
    }

    /**
     * Gets a single GradedGradeable associated with the provided gradeable and
     *  user/team.  Note: The user's team for this gradeable will be retrieved if provided
     *
     * @param  \app\models\gradeable\Gradeable $gradeable
     * @param  string|null                     $user      The id of the user to get data for
     * @param  string|null                     $team      The id of the team to get data for
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
     *  submitter.  Note: The user's team for this gradeable will be retrieved if provided
     *
     * @param  Gradeable $gradeable
     * @param  Submitter                  $submitter The submitter to get data for
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
     *
     * @param  \app\models\gradeable\Gradeable[] $gradeables The gradeable(s) to retrieve data for
     * @param  string[]|string|null              $users     The id(s) of the user(s) to get data for
     * @param  string[]|string|null              $teams     The id(s) of the team(s) to get data for
     * @param  string[]|string|null              $sort_keys An ordered list of keys to sort by (i.e. `user_id` or `g_id DESC`)
     * @return \Iterator Iterator to access each GradeableData
     * @throws \InvalidArgumentException If any GradedGradeable or GradedComponent fails to construct
     */
    public function getGradedGradeables(array $gradeables, $users = null, $teams = null, $sort_keys = null) {
        $non_team_gradeables = [];
        $team_gradeables = [];
        foreach ($gradeables as $gradeable) {
            if ($gradeable->isTeamAssignment()) {
                $team_gradeables[] = $gradeable;
            }
            else {
                $non_team_gradeables[] = $gradeable;
            }
        }

        return new CascadingIterator(
            $this->getGradedGradeablesUserOrTeam($non_team_gradeables, $users, $teams, $sort_keys, false),
            $this->getGradedGradeablesUserOrTeam($team_gradeables, $users, $teams, $sort_keys, true)
        );
    }

    /**
     * Creates a new Mark in the database
     *
     * @param Mark $mark         The mark to insert
     * @param int  $component_id The Id of the component this mark belongs to
     */
    private function createMark(Mark $mark, $component_id) {
        $params = [
            $component_id,
            $mark->getPoints(),
            $mark->getTitle(),
            $mark->getOrder(),
            $mark->isPublish()
        ];
        $this->course_db->query(
            "
            INSERT INTO gradeable_component_mark (
              gc_id,
              gcm_points,
              gcm_note,
              gcm_order,
              gcm_publish)
            VALUES (?, ?, ?, ?, ?)",
            $params
        );

        // Setup the mark with its new id
        $mark->setIdFromDatabase($this->course_db->getLastInsertId());
    }

    /**
     * Updates a mark in the database
     *
     * @param Mark $mark The mark to update
     */
    private function updateMark(Mark $mark) {
        $params = [
            $mark->getComponent()->getId(),
            $mark->getPoints(),
            $mark->getTitle(),
            $mark->getOrder(),
            $mark->isPublish(),
            $mark->getId()
        ];
        $this->course_db->query(
            "
            UPDATE gradeable_component_mark SET
              gc_id=?,
              gcm_points=?,
              gcm_note=?,
              gcm_order=?,
              gcm_publish=?
            WHERE gcm_id=?",
            $params
        );
    }

    /**
     * Deletes an array of marks from the database and any
     *  data associated with them
     *
     * @param Mark[] $marks An array of marks to delete
     */
    private function deleteMarks(array $marks) {
        if (count($marks) === 0) {
            return;
        }
        // We only need the ids
        $mark_ids = array_values(
            array_map(
                function (Mark $mark) {
                    return $mark->getId();
                },
                $marks
            )
        );
        $place_holders = $this->createParameterList(count($marks));

        $this->course_db->query("DELETE FROM gradeable_component_mark_data WHERE gcm_id IN {$place_holders}", $mark_ids);
        $this->course_db->query("DELETE FROM gradeable_component_mark WHERE gcm_id IN {$place_holders}", $mark_ids);
    }

    /**
     * Creates a new Component in the database
     *
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
            $component->isText(),
            $component->getOrder(),
            $component->isPeerComponent(),
            $component->getPage(),
            $component->getIsItempoolLinked(),
            $component->getItempool()
        ];
        $this->course_db->query(
            "
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
              gc_page,
              gc_is_itempool_linked,
              gc_itempool)
            VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            $params
        );

        // Setup the component with its new id
        $component->setIdFromDatabase($this->course_db->getLastInsertId());
    }

    /**
     * Iterates through each mark in a component and updates/creates/deletes
     *  it in the database as necessary.  Note: the component must
     *  already exist in the database to add new marks
     *
     * @param Component $component
     */
    private function updateComponentMarks(Component $component) {

        // sort marks by order
        $marks = $component->getMarks();
        usort(
            $marks,
            function (Mark $a, Mark $b) {
                return $a->getOrder() - $b->getOrder();
            }
        );

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
     *
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
                $component->isText(),
                $component->getOrder(),
                $component->isPeerComponent(),
                $component->getPage(),
                $component->getIsItempoolLinked(),
                $component->getItempool(),
                $component->getId()
            ];
            $this->course_db->query(
                "
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
                  gc_page=?,
                  gc_is_itempool_linked=?,
                  gc_itempool=?
                WHERE gc_id=?",
                $params
            );
        }
    }

    /**
     * Deletes an array of components from the database and any
     *  data associated with them
     *
     * @param array $components
     */
    private function deleteComponents(array $components) {
        if (count($components) === 0) {
            return;
        }

        // We only want the ids in our array
        $component_ids = array_values(
            array_map(
                function (Component $component) {
                    return $component->getId();
                },
                $components
            )
        );
        $place_holders = $this->createParameterList(count($components));

        $this->course_db->query("DELETE FROM gradeable_component_data WHERE gc_id IN {$place_holders}", $component_ids);
        $this->course_db->query("DELETE FROM gradeable_component WHERE gc_id IN {$place_holders}", $component_ids);
    }

    /**
     * Creates / updates a component and its marks in the database
     *
     * @param Component $component
     */
    public function saveComponent(Component $component) {
        // New component, so add it
        if ($component->getId() < 1) {
            $this->createComponent($component);
        }
        else {
            $this->updateComponent($component);
        }

        // Then, update/create/delete its marks
        $this->updateComponentMarks($component);
    }

    /**
     * Creates a new gradeable in the database
     *
     * @param \app\models\gradeable\Gradeable $gradeable The gradeable to insert
     */
    public function createGradeable(\app\models\gradeable\Gradeable $gradeable) {
        $params = [
            $gradeable->getId(),
            $gradeable->getTitle(),
            $gradeable->getInstructionsUrl(),
            $gradeable->getTaInstructions(),
            $gradeable->getType(),
            $gradeable->getGraderAssignmentMethod(),
            DateUtils::dateTimeToString($gradeable->getTaViewStartDate()),
            DateUtils::dateTimeToString($gradeable->getGradeStartDate()),
            DateUtils::dateTimeToString($gradeable->getGradeDueDate()),
            $gradeable->getGradeReleasedDate() !== null ?
                    DateUtils::dateTimeToString($gradeable->getGradeReleasedDate()) : null,
            $gradeable->getMinGradingGroup(),
            $gradeable->getSyllabusBucket(),
            $gradeable->getAllowCustomMarks()
        ];
        $this->course_db->query(
            "
            INSERT INTO gradeable(
              g_id,
              g_title,
              g_instructions_url,
              g_overall_ta_instructions,
              g_gradeable_type,
              g_grader_assignment_method,
              g_ta_view_start_date,
              g_grade_start_date,
              g_grade_due_date,
              g_grade_released_date,
              g_min_grading_group,
              g_syllabus_bucket,
              g_allow_custom_marks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            $params
        );
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            $params = [
                $gradeable->getId(),
                DateUtils::dateTimeToString($gradeable->getSubmissionOpenDate()),
                $gradeable->getSubmissionDueDate() !== null ?
                    DateUtils::dateTimeToString($gradeable->getSubmissionDueDate()) : null,
                $gradeable->isVcs(),
                $gradeable->isUsingSubdirectory(),
                $gradeable->getVcsSubdirectory(),
                $gradeable->getVcsPartialPath(),
                $gradeable->getVcsHostType(),
                $gradeable->isTeamAssignment(),
                $gradeable->getTeamSizeMax(),
                DateUtils::dateTimeToString($gradeable->getTeamLockDate()),
                $gradeable->isTaGrading(),
                $gradeable->isStudentView(),
                $gradeable->isStudentViewAfterGrades(),
                $gradeable->canStudentDownload(),
                $gradeable->isStudentSubmit(),
                $gradeable->hasDueDate(),
                $gradeable->getAutogradingConfigPath(),
                $gradeable->getLateDays(),
                $gradeable->isLateSubmissionAllowed(),
                $gradeable->getPrecision(),
                $gradeable->getLimitedAccessBlind(),
                $gradeable->getPeerBlind(),
                DateUtils::dateTimeToString($gradeable->getGradeInquiryStartDate()),
                DateUtils::dateTimeToString($gradeable->getGradeInquiryDueDate()),
                $gradeable->isGradeInquiryAllowed(),
                $gradeable->isGradeInquiryPerComponentAllowed(),
                $gradeable->getDiscussionThreadId(),
                $gradeable->isDiscussionBased(),
                $gradeable->getHiddenFiles(),
                $gradeable->getDependsOn(),
                $gradeable->getDependsOnPoints()
            ];
            $this->course_db->query(
                "
                INSERT INTO electronic_gradeable(
                  g_id,
                  eg_submission_open_date,
                  eg_submission_due_date,
                  eg_is_repository,
                  eg_using_subdirectory,
                  eg_vcs_subdirectory,
                  eg_vcs_partial_path,
                  eg_vcs_host_type,
                  eg_team_assignment,
                  eg_max_team_size,
                  eg_team_lock_date,
                  eg_use_ta_grading,
                  eg_student_view,
                  eg_student_view_after_grades,
                  eg_student_download,
                  eg_student_submit,
                  eg_has_due_date,
                  eg_config_path,
                  eg_late_days,
                  eg_allow_late_submission,
                  eg_precision,
                  eg_limited_access_blind,
                  eg_peer_blind,
                  eg_grade_inquiry_start_date,
                  eg_grade_inquiry_due_date,
                  eg_grade_inquiry_allowed,
                  eg_grade_inquiry_per_component_allowed,
                  eg_thread_ids,
                  eg_has_discussion,
                  eg_hidden_files,
                  eg_depends_on,
                  eg_depends_on_points
                  )
                VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                $params
            );
            $params = [
                $gradeable->getId(),
                $gradeable->getPeerAutograding(),
                $gradeable->getPeerRubric(),
                $gradeable->getPeerFiles(),
                $gradeable->getPeerSolutions(),
                $gradeable->getPeerDiscussion(),
            ];
            $this->course_db->query(
                "
                INSERT INTO peer_grading_panel(
                    g_id,
                    autograding,
                    rubric,
                    files,
                    solution_notes,
                    discussion
                )
                VALUES(?, ?, ?, ?, ?, ?)",
                $params
            );
        }

        // Make sure to create the rotating sections
        $this->setupRotatingSections($gradeable->getRotatingGraderSections(), $gradeable->getId());

        // Also make sure to create components
        $this->updateGradeableComponents($gradeable);

        // Create a map of gradeable-specific user anonymous ids
        $this->insertGradeableAnonId($this->getAllUsersIds(), $gradeable->getId());
    }

    /**
     * Iterates through each component in a gradeable and updates/creates
     *  it in the database as necessary.  It also reloads the marks/components
     *  if any were added
     *
     * @param \app\models\gradeable\Gradeable $gradeable
     */
    private function updateGradeableComponents(\app\models\gradeable\Gradeable $gradeable) {

        // sort components by order
        $components = $gradeable->getComponents();
        usort(
            $components,
            function (Component $a, Component $b) {
                return $a->getOrder() - $b->getOrder();
            }
        );

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
     *
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
                $gradeable->getGraderAssignmentMethod(),
                DateUtils::dateTimeToString($gradeable->getTaViewStartDate()),
                DateUtils::dateTimeToString($gradeable->getGradeStartDate()),
                DateUtils::dateTimeToString($gradeable->getGradeDueDate()),
                DateUtils::dateTimeToString($gradeable->getGradeReleasedDate()),
                $gradeable->getMinGradingGroup(),
                $gradeable->getSyllabusBucket(),
                $gradeable->getAllowCustomMarks(),
                $gradeable->getId()
            ];
            $this->course_db->query(
                "
                UPDATE gradeable SET
                  g_title=?,
                  g_instructions_url=?,
                  g_overall_ta_instructions=?,
                  g_gradeable_type=?,
                  g_grader_assignment_method=?,
                  g_ta_view_start_date=?,
                  g_grade_start_date=?,
                  g_grade_due_date=?,
                  g_grade_released_date=?,
                  g_min_grading_group=?,
                  g_syllabus_bucket=?,
                  g_allow_custom_marks=?
                WHERE g_id=?",
                $params
            );
            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                $params = [
                    DateUtils::dateTimeToString($gradeable->getSubmissionOpenDate()),
                    DateUtils::dateTimeToString($gradeable->getSubmissionDueDate()),
                    $gradeable->isVcs(),
                    $gradeable->isUsingSubdirectory(),
                    $gradeable->getVcsSubdirectory(),
                    $gradeable->getVcsPartialPath(),
                    $gradeable->getVcsHostType(),
                    $gradeable->isTeamAssignment(),
                    $gradeable->getTeamSizeMax(),
                    DateUtils::dateTimeToString($gradeable->getTeamLockDate()),
                    $gradeable->isTaGrading(),
                    $gradeable->isStudentView(),
                    $gradeable->isStudentViewAfterGrades(),
                    $gradeable->canStudentDownload(),
                    $gradeable->isStudentSubmit(),
                    $gradeable->hasDueDate(),
                    $gradeable->hasReleaseDate(),
                    $gradeable->getAutogradingConfigPath(),
                    $gradeable->getLateDays(),
                    $gradeable->isLateSubmissionAllowed(),
                    $gradeable->getPrecision(),
                    $gradeable->getInstructorBlind(),
                    $gradeable->getLimitedAccessBlind(),
                    $gradeable->getPeerBlind(),
                    DateUtils::dateTimeToString($gradeable->getGradeInquiryStartDate()),
                    DateUtils::dateTimeToString($gradeable->getGradeInquiryDueDate()),
                    $gradeable->isGradeInquiryAllowed(),
                    $gradeable->isGradeInquiryPerComponentAllowed(),
                    $gradeable->getDiscussionThreadId(),
                    $gradeable->isDiscussionBased(),
                    $gradeable->getHiddenFiles(),
                    $gradeable->getDependsOn(),
                    $gradeable->getDependsOnPoints(),
                    $gradeable->getId()
                ];
                $this->course_db->query(
                    "
                    UPDATE electronic_gradeable SET
                      eg_submission_open_date=?,
                      eg_submission_due_date=?,
                      eg_is_repository=?,
                      eg_using_subdirectory=?,
                      eg_vcs_subdirectory=?,
                      eg_vcs_partial_path=?,
                      eg_vcs_host_type=?,
                      eg_team_assignment=?,
                      eg_max_team_size=?,
                      eg_team_lock_date=?,
                      eg_use_ta_grading=?,
                      eg_student_view=?,
                      eg_student_view_after_grades=?,
                      eg_student_download=?,
                      eg_student_submit=?,
                      eg_has_due_date=?,
                      eg_has_release_date=?,
                      eg_config_path=?,
                      eg_late_days=?,
                      eg_allow_late_submission=?,
                      eg_precision=?,
                      eg_instructor_blind=?,
                      eg_limited_access_blind=?,
                      eg_peer_blind=?,
                      eg_grade_inquiry_start_date=?,
                      eg_grade_inquiry_due_date=?,
                      eg_grade_inquiry_allowed=?,
                      eg_grade_inquiry_per_component_allowed=?,
                      eg_thread_ids=?,
                      eg_has_discussion=?,
                      eg_hidden_files=?,
                      eg_depends_on=?,
                      eg_depends_on_points=?
                    WHERE g_id=?",
                    $params
                );
                // Below params contain grading interface panels
                $params = [
                    $gradeable->getId(),
                    $gradeable->getPeerAutograding(),
                    $gradeable->getPeerRubric(),
                    $gradeable->getPeerFiles(),
                    $gradeable->getPeerSolutions(),
                    $gradeable->getPeerDiscussion()
                ];
                // Update row if exists, else Insert row
                $this->course_db->query(
                    "
                    INSERT INTO peer_grading_panel (g_id, autograding, rubric, files, solution_notes, discussion)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON CONFLICT (g_id) DO UPDATE
                    SET autograding = EXCLUDED.autograding,
                        rubric = EXCLUDED.rubric,
                        files = EXCLUDED.files,
                        solution_notes = EXCLUDED.solution_notes,
                        discussion = EXCLUDED.discussion
                    ",
                    $params
                );
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
     *
     * @param GradedComponent $graded_component
     * @param int[]           $mark_ids
     */
    private function deleteGradedComponentMarks(GradedComponent $graded_component, $mark_ids) {
        if ($mark_ids === null || count($mark_ids) === 0) {
            return;
        }

        $param = array_merge(
            [
            $graded_component->getTaGradedGradeable()->getId(),
            $graded_component->getComponentId(),
            $graded_component->getGraderId(),
            ],
            $mark_ids
        );
        $place_holders = $this->createParameterList(count($mark_ids));
        $this->course_db->query(
            "DELETE FROM gradeable_component_mark_data
            WHERE gd_id=? AND gc_id=? AND gcd_grader_id=? AND gcm_id IN {$place_holders}",
            $param
        );
    }

    /**
     * Adds the provided mark ids as marks assigned to a graded component
     *
     * @param GradedComponent $graded_component
     * @param int[]           $mark_ids
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
     *
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
     *
     * @param GradedComponent $graded_component
     */
    private function updateGradedComponent(GradedComponent $graded_component) {
        if ($graded_component->isModified()) {
            if (!$graded_component->getComponent()->isPeerComponent()) {
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
                      gcd_grade_time=?
                    WHERE gd_id=? AND gc_id=? AND gcd_grader_id=?";
            }
            $this->course_db->query($query, $params);
        }
    }

    /**
     * Deletes a GradedComponent from the database
     *
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

    private function updateOverallComments(TaGradedGradeable $ta_graded_gradeable) {
        foreach ($ta_graded_gradeable->getOverallComments() as $user_id => $comment) {
            $this->updateOverallComment($ta_graded_gradeable, $comment, $user_id);
        }
    }

    private function updateOverallComment(TaGradedGradeable $ta_graded_gradeable, $comment, $grader_id) {
        $g_id = $ta_graded_gradeable->getGradedGradeable()->getGradeable()->getId();
        if ($comment === "") {
            $this->deleteOverallComment($g_id, $grader_id, $ta_graded_gradeable->getGradedGradeable()->getSubmitter()->getId());
            $ta_graded_gradeable->removeOverallComment($grader_id);
            return;
        }

        $user_id = null;
        $team_id = null;

        // TODO: replace this with a single upsert when postgres can do an on conflict on
        //   multiple constraints (gradeable_data_overall_comment_user_unique, gradeable_data_overall_comment_team_unique)
        if ($ta_graded_gradeable->getGradedGradeable()->getGradeable()->isTeamAssignment()) {
            $team_id = $ta_graded_gradeable->getGradedGradeable()->getSubmitter()->getId();
            $conflict_clause = "(g_id, goc_team_id, goc_grader_id)";
        }
        else {
            $user_id = $ta_graded_gradeable->getGradedGradeable()->getSubmitter()->getId();
            $conflict_clause = "(g_id, goc_user_id, goc_grader_id)";
        }

        $query = "
            INSERT INTO gradeable_data_overall_comment (g_id, goc_user_id, goc_team_id, goc_grader_id, goc_overall_comment)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT {$conflict_clause}
                DO
                    UPDATE SET
                        goc_overall_comment=?;
            ";

        $params = [$g_id, $user_id, $team_id, $grader_id, $comment, $comment];
        $this->course_db->query($query, $params);
    }

    /**
     * @param string $gradeable_id
     * @param string $grader_id
     * @param string $submitter_id
     * @return void
     */
    public function deleteOverallComment($gradeable_id, $grader_id, $submitter_id) {
        $this->course_db->query(
            "DELETE FROM gradeable_data_overall_comment WHERE g_id=? AND goc_grader_id=? AND (goc_user_id=? OR goc_team_id=?)",
            [$gradeable_id, $grader_id, $submitter_id, $submitter_id]
        );
    }

    /**
     * Update/create the components/marks for a gradeable.
     *
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    private function updateGradedComponents(TaGradedGradeable $ta_graded_gradeable) {
        // iterate through graded components and see if any need updating/creating
        foreach ($ta_graded_gradeable->getGradedComponentContainers() as $container) {
            foreach ($container->getGradedComponents() as $component_grade) {
                if ($component_grade->isNew()) {
                    $this->createGradedComponent($component_grade);
                }
                else {
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
     *
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    private function createTaGradedGradeable(TaGradedGradeable $ta_graded_gradeable) {
        $submitter_id = $ta_graded_gradeable->getGradedGradeable()->getSubmitter()->getId();
        $is_team = $ta_graded_gradeable->getGradedGradeable()->getSubmitter()->isTeam();
        $params = [
            $ta_graded_gradeable->getGradedGradeable()->getGradeable()->getId(),
            $is_team ? null : $submitter_id,
            $is_team ? $submitter_id : null,
            $ta_graded_gradeable->getUserViewedDate() !== null ?
                DateUtils::dateTimeToString($ta_graded_gradeable->getUserViewedDate()) : null
        ];
        $query = "
            INSERT INTO gradeable_data (
                g_id,
                gd_user_id,
                gd_team_id,
                gd_user_viewed_date)
            VALUES(?, ?, ?, ?)";
        $this->course_db->query($query, $params);

        // Setup the graded gradeable with its new id
        $ta_graded_gradeable->setIdFromDatabase($this->course_db->getLastInsertId());

        // Also be sure to save the components
        $this->updateGradedComponents($ta_graded_gradeable);
        // And to separately update the overall comments
        $this->updateOverallComments($ta_graded_gradeable);
    }

    /**
     * Updates an existing Ta Grade in the database along with its graded components/marks
     *
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    private function updateTaGradedGradeable(TaGradedGradeable $ta_graded_gradeable) {
        // If the grade has been modified, then update its properties
        if ($ta_graded_gradeable->isModified()) {
            $params = [
                $ta_graded_gradeable->getUserViewedDate() !== null ?
                    DateUtils::dateTimeToString($ta_graded_gradeable->getUserViewedDate()) : null,
                $ta_graded_gradeable->getId()
            ];
            $query = "
                UPDATE gradeable_data SET
                  gd_user_viewed_date=?
                WHERE gd_id=?";
            $this->course_db->query($query, $params);
        }

        // Also be sure to save the components
        $this->updateGradedComponents($ta_graded_gradeable);
        // And to update the overall comment
        $this->updateOverallComments($ta_graded_gradeable);
    }

    /**
     * Creates a Ta Grade in the database if it doesn't exist, otherwise it just updates it
     *
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    public function saveTaGradedGradeable(TaGradedGradeable $ta_graded_gradeable) {
        // Ta Grades are initialized to have an id of 0 if not loaded from the db, so use that to check
        if ($ta_graded_gradeable->getId() < 1) {
            $this->createTaGradedGradeable($ta_graded_gradeable);
        }
        else {
            $this->updateTaGradedGradeable($ta_graded_gradeable);
        }
    }

    /**
     * Deletes an entry from the gradeable_data table
     *
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    public function deleteTaGradedGradeable(TaGradedGradeable $ta_graded_gradeable) {
        $this->course_db->query("DELETE FROM gradeable_data WHERE gd_id=?", [$ta_graded_gradeable->getId()]);
    }

    /**
     * Deletes an entry from the gradeable_data table with the provided gradeable id and user/team id
     *
     * @param string $gradeable_id
     * @param int    $submitter_id User or Team id
     */
    public function deleteTaGradedGradeableByIds($gradeable_id, $submitter_id) {
        $this->course_db->query(
            'DELETE FROM gradeable_data WHERE g_id=? AND (gd_user_id=? OR gd_team_id=?)',
            [$gradeable_id, $submitter_id, $submitter_id]
        );
    }

    /**
     * Changes the graded version of a gradeable for a particular student
     *
     * @param int[] $component_ids
     */
    public function changeGradedVersionOfComponents(string $gradeable_id, string $submitter_id, int $version, array $component_ids): void {
        $this->course_db->query(
            'UPDATE gradeable_component_data AS gcd
            SET gcd_graded_version = ?
            FROM gradeable_data AS gd
            WHERE (
                gd.g_id = ?
                AND gd.gd_user_id = ?
                AND gcd.gc_id IN ' . $this->createParameterList(count($component_ids)) . '
                AND gd.gd_id = gcd.gd_id
            )',
            array_merge([$version, $gradeable_id, $submitter_id], $component_ids)
        );
    }

    /**
     * Gets if the provided submitter has a submission for a particular gradeable
     *
     * @param  \app\models\gradeable\Gradeable $gradeable
     * @param  Submitter                       $submitter
     * @return bool
     */
    public function getHasSubmission(Gradeable $gradeable, Submitter $submitter) {
        $this->course_db->query(
            'SELECT EXISTS (SELECT g_id FROM electronic_gradeable_data WHERE g_id=? AND (user_id=? OR team_id=?))',
            [$gradeable->getId(), $submitter->getId(), $submitter->getId()]
        );
        return $this->course_db->row()['exists'] ?? false;
    }

    /**
     * checks if there are any custom marks saved for the provided gradeable
     *
     * @param string $gradeable_id
     * @return bool
     */
    public function getHasCustomMarks($gradeable_id) {
        //first get the gc_id's for all components associated with the gradeable
        $this->course_db->query(
            "SELECT gc.gc_id FROM gradeable_component AS gc
                   INNER JOIN gradeable_component_data AS gcd ON gc.gc_id=gcd.gc_id
                   WHERE gc.g_id=? AND gcd.gcd_component_comment <> '' ",
            [$gradeable_id]
        );
        if (count($this->course_db->rows()) > 0) {
            return true;
        }
        return false;
    }

    /**
     * Gets if the provided submitter has a submission for a particular gradeable
     *
     * @param  \app\models\gradeable\Gradeable $gradeable
     * @param  String                     $userid
     * @return bool
     */
    public function getUserHasSubmission(Gradeable $gradeable, string $userid): bool {
        $this->course_db->query(
            'SELECT user_id FROM electronic_gradeable_data WHERE g_id=? AND (user_id=?)',
            [$gradeable->getId(), $userid]
        );
        return $this->course_db->getRowCount() > 0;
    }

    /**
     * Get the active version for all given submitter ids. If they do not have an active version,
     * their version will be zero.
     *
     * @param  \app\models\gradeable\Gradeable $gradeable
     * @param  string[]                        $submitter_ids
     * @return bool[] Map of id=>version
     */
    public function getActiveVersions(Gradeable $gradeable, array $submitter_ids) {
        if (count($submitter_ids) === 0) {
            return [];
        }

        // (?), (?), (?)
        $id_placeholders = implode(',', array_fill(0, count($submitter_ids), '(?)'));

        $query = "
            SELECT ids.id, (CASE WHEN m IS NULL THEN 0 ELSE m END) AS max
            FROM (VALUES $id_placeholders) ids(id)
            LEFT JOIN (
              SELECT COALESCE(user_id, team_id) AS id, active_version as m
              FROM electronic_gradeable_version
              WHERE g_id = ? AND (user_id IS NOT NULL OR team_id IS NOT NULL)
            ) AS versions
            ON versions.id = ids.id
        ";

        $params = array_merge($submitter_ids, [$gradeable->getId()]);

        $this->course_db->query($query, $params);
        $versions = [];
        foreach ($this->course_db->rows() as $row) {
            $versions[$row["id"]] = $row["max"];
        }
        return $versions;
    }

    /**
     * Gets a list of emails with user ids for all active particpants in a course
     */
    public function getEmailListWithIds() {
        $parameters = [];
        $this->course_db->query('SELECT user_id, user_email, user_group, registration_section FROM users WHERE user_group != 4 OR registration_section IS NOT null', $parameters);

        return $this->course_db->rows();
    }

    /**
     * Gives true if thread is locked
     */
    public function isThreadLocked(int $thread_id): bool {
        $this->course_db->query('SELECT lock_thread_date FROM threads WHERE id = ?', [$thread_id]);
        $row = $this->course_db->row();
        $lock_date = $row['lock_thread_date'] ?? null;
        if (empty($this->course_db->row()['lock_thread_date'])) {
            return false;
        }
        $current_date = new \DateTime();
        $lock_date_time = new \DateTime($lock_date);
        return $lock_date_time < $current_date;
    }

    /**
     * Returns an array of users in the current course which have not been completely graded for the given gradeable.
     * Excludes users in the null section
     *
     * If a component_id is passed in, then the list of returned users will be limited to users with
     * that specific component ungraded
     *
     * @param  Gradeable $gradeable
     * @return array
     */
    public function getUsersNotFullyGraded(Gradeable $gradeable, $component_id = "-1") {

        // Get variables needed for query
        $component_count = count($gradeable->getComponents());
        $gradeable_id = $gradeable->getId();

        // Configure which type of grading this gradeable is using
        // If there are graders assigned to rotating sections we are very likely using rotating sections
        $rotation_sections = $gradeable->getRotatingGraderSections();
        count($rotation_sections) ? $section_type = 'rotating_section' : $section_type = 'registration_section';

        // Configure variables related to user vs team submission
        if ($gradeable->isTeamAssignment()) {
            $id_string = 'team_id';
            $table = 'gradeable_teams';
        }
        else {
            $id_string = 'user_id';
            $table = 'users';
        }

        $main_query = "select $id_string from $table where $section_type is not null and $id_string not in";

        $parameters = [];
        // Select which subquery to use
        if ($component_id != "-1") {
            // Use this sub query to select users who do not have a specific component within this gradable graded
            $sub_query = "(select gd_$id_string
                from gradeable_component_data left join gradeable_data on gradeable_component_data.gd_id = gradeable_data.gd_id
                where g_id = ? and gc_id = ?);";

            $parameters = [$gradeable_id, $component_id];
        }
        else {
            // Use this sub query to select users who have at least one component not graded
            $sub_query = "(select gradeable_data.gd_$id_string
             from gradeable_component_data left join gradeable_data on gradeable_component_data.gd_id = gradeable_data.gd_id
             where g_id = ? group by gradeable_data.gd_id having count(gradeable_data.gd_id) = ?);";

            $parameters = [$gradeable_id, $component_count];
        }

        // Assemble complete query
        $query = "$main_query $sub_query";

        // Run query
        $this->course_db->query($query, $parameters);

        // Capture results
        $not_fully_graded = $this->course_db->rows();

        // Clean up results
        $not_fully_graded = array_column($not_fully_graded, $id_string);

        return $not_fully_graded;
    }



/////////////////Office Hours Queue queries/////////////////////////////////////

  /*
  current_state values
      ('waiting'):Waiting
      ('being_helped'):Being helped
      ('done'):Done/Fully out of the queue
  removal_type values
      (null):Still in queue
      ('self'):Removed yourself
      ('helped'):Mentor/TA helped you
      ('removed'):Mentor/TA removed you
      ('emptied'):Kicked out because queue emptied
      ('self_helped'):You helped you
  */

    public function getCurrentQueue() {
        $query = "
        SELECT ROW_NUMBER()
            OVER (ORDER BY time_in ASC),
                queue.*,
                helper.user_givenname AS helper_givenname,
                helper.user_preferred_givenname AS helper_preferred_givenname,
                helper.user_familyname AS helper_familyname,
                helper.user_preferred_familyname AS helper_preferred_familyname,
                helper.user_id AS helper_id,
                helper.user_email AS helper_email,
                helper.user_email_secondary AS helper_email_secondary,
                helper.user_email_secondary_notify AS helper_email_secondary_notify,
                helper.user_group AS helper_group,
                helper.user_pronouns AS helper_pronouns,
                h1.helped_today
            FROM queue
            LEFT JOIN users helper on helper.user_id = queue.help_started_by
            LEFT JOIN (
            SELECT user_id as uid, queue_code as qc, COUNT(queue_code) AS helped_today
            FROM queue WHERE time_in > ? AND (removal_type = 'helped' OR removal_type = 'self_helped')
            GROUP BY user_id, queue_code
          )
          AS h1
          ON queue.user_id = h1.uid AND queue_code = h1.qc
          WHERE current_state IN ('waiting','being_helped')
          ORDER BY ROW_NUMBER
        ";
        $this->course_db->query($query, [$this->core->getDateTimeNow()->format('Y-m-d')]);
        return $this->course_db->rows();
    }

    public function getPastQueue() {

        $query = "
        SELECT Row_number()
            OVER (ORDER BY time_out DESC, time_in DESC),
                queue.*,
                helper.user_givenname AS helper_givenname,
                helper.user_preferred_givenname AS helper_preferred_givenname,
                helper.user_familyname AS helper_familyname,
                helper.user_preferred_familyname AS helper_preferred_familyname,
                helper.user_id AS helper_id,
                helper.user_email AS helper_email,
                helper.user_email_secondary AS helper_email_secondary,
                helper.user_email_secondary_notify AS helper_email_secondary_notify,
                helper.user_group AS helper_group,
                helper.user_pronouns AS helper_pronouns,
                remover.user_givenname AS remover_givenname,
                remover.user_preferred_givenname AS remover_preferred_givenname,
                remover.user_familyname AS remover_familyname,
                remover.user_preferred_familyname AS remover_preferred_familyname,
                remover.user_id AS remover_id,
                remover.user_email AS remover_email,
                remover.user_email_secondary AS remover_email_secondary,
                remover.user_email_secondary_notify AS remover_email_secondary_notify,
                remover.user_group AS remover_group,
                remover.user_pronouns AS remover_pronouns,
                h1.helped_today,
                h.times_helped
            FROM    queue
            LEFT JOIN users helper ON helper.user_id = queue.help_started_by
            LEFT JOIN users remover ON remover.user_id = queue.removed_by
            LEFT JOIN (
              SELECT user_id as uid, COUNT(user_id) AS times_helped
              FROM queue
              WHERE extract(WEEK from time_in) = extract(WEEK from ?::DATE) AND (removal_type = 'helped' OR removal_type = 'self_helped')
              GROUP BY user_id
            )
            AS h
            ON queue.user_id = h.uid
            LEFT JOIN (
              SELECT user_id as uid, queue_code as qc, COUNT(queue_code) AS helped_today
              FROM queue WHERE time_in > ? AND (removal_type = 'helped' OR removal_type = 'self_helped')
              GROUP BY user_id, queue_code
            )
            AS h1
            ON queue.user_id = h1.uid AND queue.queue_code = h1.qc
            WHERE time_in > ? AND current_state IN ('done')
            ORDER BY row_number
        ";
        $current_date = $this->core->getDateTimeNow()->format('Y-m-d');
        $this->course_db->query($query, [$current_date, $current_date, $current_date]);
        return $this->course_db->rows();
    }

    /**
     * Return all queue information for a certain student
     *
     * @param string $user_id
     * @return array<string, mixed[]> user info, indexed by user id.
     */
    public function studentQueueSearch(string $user_id): array {
        $this->course_db->query("SELECT * FROM queue WHERE user_id = ? ORDER BY time_in", [$user_id]);
        return $this->course_db->rows();
    }


    public function isAnyQueueOpen() {
        $this->course_db->query("SELECT COUNT(*) AS num_open FROM queue_settings WHERE open = true");
        return $this->course_db->row()['num_open'] > 0;
    }


    public function openQueue(string $queue_code, $token, $regex_pattern, $require_contact_info) {
        $this->course_db->query("SELECT * FROM queue_settings WHERE UPPER(TRIM(code)) = UPPER(TRIM(?))", [$queue_code]);

        //cannot have more than one queue with the same code
        if (0 < count($this->course_db->rows())) {
            return false;
        }

        $this->course_db->query("INSERT INTO queue_settings (open,code,token,regex_pattern, contact_information) VALUES (TRUE, TRIM(?), TRIM(?), ?, ?)", [$queue_code,$token,$regex_pattern,$require_contact_info]);
        return true;
    }

    public function getQueueRegex(string $queue_code) {
        $this->course_db->query("SELECT regex_pattern FROM queue_settings WHERE code = ?", [$queue_code]);
        return $this->course_db->rows();
    }

    public function toggleQueue(string $queue_code, string $state) {
        if ($state === "1") {
            $state = 'false';
        }
        else {
            $state = 'true';
        }
        $this->course_db->query("UPDATE queue_settings SET open = ? where UPPER(TRIM(code)) = UPPER(TRIM(?))", [$state, $queue_code]);
    }

    public function deleteQueue(string $queue_code) {
        $this->emptyQueue($queue_code);
        $this->course_db->query("DELETE FROM queue_settings WHERE UPPER(TRIM(code)) = UPPER(TRIM(?))", [$queue_code]);
    }

    public function getValidatedCode(string $queue_code, string $token = null) {
        //first check if the queue has an access code
        $this->course_db->query("SELECT * FROM queue_settings WHERE UPPER(TRIM(code)) = UPPER(TRIM(?)) AND open = true", [$queue_code]);
        $row = $this->course_db->row();
        if ($this->course_db->getRowCount() > 0 && $row['token'] == null) {
            return $row['code'];
        }
        elseif ($token === null) {
            return false;
        }
        else {
            $this->course_db->query("SELECT * FROM queue_settings WHERE UPPER(TRIM(code)) = UPPER(TRIM(?)) AND UPPER(TRIM(token)) = UPPER(TRIM(?)) AND open = true", [$queue_code, $token]);
            if ($this->course_db->getRowCount() > 0) {
                return $this->course_db->row()['code'];
            }
        }
        return false;
    }

    public function alreadyInAQueue(string $user_id = null) {
        if (is_null($user_id)) {
            $user_id = $this->core->getUser()->getId();
        }
        $this->course_db->query("SELECT COUNT(*) FROM queue WHERE user_id = ? AND current_state IN ('waiting','being_helped')", [$user_id]);
        return $this->course_db->row()['count'] > 0;
    }

    public function getLastTimeInQueue(string $user_id, string $queue_code) {
        $this->course_db->query("SELECT max(time_in) FROM queue WHERE user_id = ? AND UPPER(TRIM(queue_code)) = UPPER(TRIM(?)) AND (removal_type IN ('helped', 'self_helped') OR help_started_by IS NOT NULL) ", [$user_id, $queue_code]);
        return $this->course_db->row()['max'];
    }

    //Gets the time a student joined the queue they are currently in
    public function getTimeJoinedQueue(string $user_id, string $queue_code) {
        $this->course_db->query("SELECT max(time_in) FROM queue WHERE user_id = ? AND UPPER(TRIM(queue_code)) = UPPER(TRIM(?)) ", [$user_id, $queue_code]);
        return $this->course_db->row()['max'];
    }

    public function getStarType(string $user_id, string $queue_code): string {
        $this->course_db->query("SELECT star_type FROM queue WHERE user_id = ?
            AND UPPER(TRIM(queue_code)) = UPPER(TRIM(?))
            ORDER BY entry_id DESC LIMIT 1", [$user_id, $queue_code]);

        return $this->course_db->row()['star_type'];
    }

    public function getQueueId(string $queue_code) {
        $this->course_db->query("select * from queue_settings where code = ?;", [$queue_code]);
        return $this->course_db->row()['id'];
    }

    public function getQueueHasContactInformation(string $queue_code) {
        $this->course_db->query("select * from queue_settings where code = ?;", [$queue_code]);
        return $this->course_db->row()['contact_information'];
    }


    public function addToQueue(string $queue_code, string $user_id, string $name, $contact_info, $time_in = 0) {
        $star_type = 'none';
        $last_time_in_queue = $this->getLastTimeInQueue($user_id, $queue_code);
        $one_day_ago = date_sub(new \DateTime('tomorrow', $this->core->getConfig()->getTimezone()), date_interval_create_from_date_string('1 days'));
        $one_week_ago = date_sub(new \DateTime('tomorrow', $this->core->getConfig()->getTimezone()), date_interval_create_from_date_string('5 days'));
        if ($last_time_in_queue === null || DateUtils::parseDateTime($last_time_in_queue, $this->core->getConfig()->getTimezone()) < $one_week_ago) {
            $star_type = 'full';
        }
        elseif (DateUtils::parseDateTime($last_time_in_queue, $this->core->getConfig()->getTimezone()) < $one_day_ago) {
            $star_type = 'half';
        }

        $entry_time = $time_in == 0 ? $this->core->getDateTimeNow() : $time_in;
        $this->course_db->query("INSERT INTO queue
            (
                current_state,
                removal_type,
                queue_code,
                user_id,
                name,
                time_in,
                time_help_start,
                time_out,
                added_by,
                help_started_by,
                removed_by,
                contact_info,
                last_time_in_queue,
                time_paused,
                time_paused_start,
                star_type
            ) VALUES (
                'waiting',
                NULL,
                TRIM(?),
                ?,
                ?,
                ?,
                NULL,
                NULL,
                ?,
                NULL,
                NULL,
                ?,
                ?,
                ?,
                NULL,
                ?
            )", [$queue_code,$user_id,$name, $entry_time,$user_id,$contact_info,$last_time_in_queue,0,$star_type]);
    }

    public function removeUserFromQueue(string $user_id, string $remove_type, string $queue_code) {
        $status_code = null;
        if ($remove_type !== 'self') {//user removing themselves
            $status_code = 'being_helped';//dont allow removing yourself if you are being helped
        }

        $this->course_db->query("SELECT * from queue where user_id = ? and UPPER(TRIM(queue_code)) = UPPER(TRIM(?)) and current_state IN ('waiting', ?)", [$user_id, $queue_code, $status_code]);
        if (count($this->course_db->rows()) <= 0) {
            if ($remove_type === 'self') {
                //This happens for 1 of 2 reason. They try and remove themself while being helped
                //In this case they should have refreshed and they can click finish helping
                //Or they try and remove themself but they are no longer in the queue
                //In this case when the page refreshes they will see that
                $this->core->addErrorMessage("Error: Please try again");
            }
            else {
                $this->core->addErrorMessage("User no longer in queue");
            }
            return false;
        }

        $star_type = 'none';
        $prev_type = $this->getStarType($user_id, $queue_code);
        if ($prev_type === 'full') {
            $star_type = 'prev_full';
        }
        elseif ($prev_type === 'half') {
            $star_type = 'prev_half';
        }

        $this->course_db->query("UPDATE queue SET current_state = 'done', removal_type = ?, time_out = ?, removed_by = ?, star_type = ? WHERE user_id = ? AND UPPER(TRIM(queue_code)) = UPPER(TRIM(?)) AND current_state IN ('waiting','being_helped')", [$remove_type,$this->core->getDateTimeNow(),$this->core->getUser()->getId(),$star_type,$user_id, $queue_code]);
    }

    public function startHelpUser(string $user_id, string $queue_code) {
        $this->course_db->query("SELECT * from queue where user_id = ? and UPPER(TRIM(queue_code)) = UPPER(TRIM(?)) and current_state IN ('waiting')", [$user_id, $queue_code]);
        if (count($this->course_db->rows()) <= 0) {
            $this->core->addErrorMessage("User not in queue");
            return false;
        }
        $this->course_db->query("UPDATE queue SET current_state = 'being_helped', time_help_start = ?, help_started_by = ? WHERE user_id = ? and UPPER(TRIM(queue_code)) = UPPER(TRIM(?)) and current_state IN ('waiting')", [$this->core->getDateTimeNow(), $this->core->getUser()->getId(), $user_id, $queue_code]);
    }

    public function finishHelpUser(string $user_id, string $queue_code, string $remove_type) {
        $this->course_db->query("SELECT * from queue where user_id = ? and UPPER(TRIM(queue_code)) = UPPER(TRIM(?)) and current_state IN ('being_helped')", [$user_id, $queue_code]);
        if (count($this->course_db->rows()) <= 0) {
            $this->core->addErrorMessage("User not in queue");
            return false;
        }

        $star_type = 'none';
        $current_star_type = $this->getStarType($user_id, $queue_code);
        if ($current_star_type === 'full') {
            $star_type = 'prev_full';
        }
        elseif ($current_star_type === 'half') {
            $star_type = 'prev_half';
        }

        $this->course_db->query(
            "UPDATE queue SET current_state = 'done', removal_type = ?, time_out = ?, removed_by = ?, star_type = ? WHERE user_id = ?
            AND UPPER(TRIM(queue_code)) = UPPER(TRIM(?))
            and current_state IN ('being_helped')",
            [$remove_type,$this->core->getDateTimeNow(),$this->core->getUser()->getId(),$star_type,$user_id,$queue_code]
        );
    }

    public function setQueuePauseState(bool $new_state) {
        $current_queue_state = $this->core->getQueries()->getCurrentQueueState();
        $time_paused_start = $current_queue_state['time_paused_start'];
        $current_state = $time_paused_start != null;
        if ($new_state != $current_state) {
            // The pause state is actually changing
            $time_paused = $current_queue_state['time_paused'];
            $time_paused_start = date_create($time_paused_start);
            if ($new_state) {
                // The student is pausing
                $time_paused_start = $this->core->getDateTimeNow();
                $date_interval = new \DateInterval("PT{$time_paused}S");
                $time_paused_start = date_sub($time_paused_start, $date_interval);
            }
            else {
                // The student is un-pausing
                $time_paused_end = $this->core->getDateTimeNow();
                $date_interval = date_diff($time_paused_start, $time_paused_end);
                $time_paused = ($date_interval->h * 60 + $date_interval->i) * 60 + $date_interval->s;
                $time_paused_start = null;
            }
            $this->course_db->query("UPDATE queue SET paused = ?, time_paused = ?, time_paused_start = ? WHERE current_state = 'waiting' AND user_id = ?", [$new_state, $time_paused, $time_paused_start, $this->core->getUser()->getId()]);
        }
    }

    public function emptyQueue(string $queue_code) {
        $this->course_db->query("UPDATE queue SET current_state = 'done', removal_type = 'emptied', removed_by = ?, time_out = ? where current_state IN ('waiting','being_helped') and UPPER(TRIM(queue_code)) = UPPER(TRIM(?))", [$this->core->getUser()->getId(),$this->core->getDateTimeNow(), $queue_code]);
    }

    public function getQueueFromEntryId(int $entry_id) {
        $this->course_db->query("SELECT * FROM queue WHERE entry_id = ?", [$entry_id]);
        if (count($this->course_db->rows()) <= 0) {
            $this->core->addErrorMessage("Invalid Entry ID");
            return;
        }
        return $this->course_db->rows()[0];
    }

    public function restoreUserToQueue(int $entry_id) {
        $row = $this->getQueueFromEntryId($entry_id);
        $user_id = $row['user_id'];
        $queue_code = $row['queue_code'];
        if (is_null($user_id)) {
            return;
        }
        if ($this->alreadyInAQueue($user_id)) {
            $this->core->addErrorMessage("Cannot restore a user that is currently in the queue. Please remove them first.");
            return;
        }

        $star_type = 'none';
        $prev_type = $this->getStarType($user_id, $queue_code);
        if ($prev_type === 'prev_full') {
            $star_type = 'full';
        }
        elseif ($prev_type === 'prev_half') {
            $star_type = 'half';
        }

        $last_time_in_queue = $this->getLastTimeInQueue($user_id, $queue_code);
        $this->course_db->query("UPDATE queue SET current_state = 'waiting', removal_type = null, removed_by = null, time_out = null, time_help_start = null, help_started_by = null, last_time_in_queue = ?, star_type = ? where entry_id = ?", [$last_time_in_queue, $star_type, $entry_id]);
        $this->core->addSuccessMessage("Student restored");
    }

    public function getAllQueues() {
        $this->course_db->query("
SELECT qs.*, COALESCE (ns.num_students, 0) AS num_students
FROM queue_settings qs
LEFT JOIN (
    SELECT q.queue_code, COUNT(distinct q.user_id) AS num_students
    FROM queue q
    WHERE q.current_state IN ('waiting')
    GROUP BY q.queue_code
) AS ns ON ns.queue_code = qs.code
ORDER BY id");
        return $this->course_db->rows();
    }

    public function getAllOpenQueues() {
        $this->course_db->query("SELECT * FROM queue_settings where open = true ORDER BY id");
        return $this->course_db->rows();
    }

    public function getQueueNumberAheadOfYou($queue_code = null) {
        if ($queue_code) {
            $this->course_db->query("
SELECT count(*)
FROM queue
WHERE current_state IN
    ('waiting')
    AND time_in <= (
        SELECT time_in
        FROM queue
        WHERE user_id = ? AND current_state IN ('waiting','being_helped')
    )
    AND UPPER(TRIM(queue_code)) = UPPER(TRIM(?))
                ", [$this->core->getUser()->getId(), $queue_code]);
        }
        else {
            $this->course_db->query("SELECT count(*) FROM queue WHERE current_state IN ('waiting')");
        }
        return $this->course_db->row()['count'];
    }

    public function getLastQueueDetails() {
        $this->course_db->query("SELECT name, contact_info from queue where user_id = ? order by time_in desc limit 1", [$this->core->getUser()->getId()]);
        return $this->course_db->row();
    }

    public function getCurrentQueueState() {
        $query = "
        SELECT
            queue.*,
            helper.user_givenname AS helper_givenname,
            helper.user_preferred_givenname AS helper_preferred_givenname,
            helper.user_familyname AS helper_familyname,
            helper.user_preferred_familyname AS helper_preferred_familyname,
            helper.user_id AS helper_id,
            helper.user_email AS helper_email,
            helper.user_email_secondary AS helper_email_secondary,
            helper.user_email_secondary_notify AS helper_email_secondary_notify,
            helper.user_group AS helper_group
        FROM queue LEFT JOIN users helper ON helper.user_id = queue.help_started_by
        WHERE queue.user_id = ? AND queue.current_state IN ('waiting','being_helped')
        ";
        $this->course_db->query($query, [$this->core->getUser()->getId()]);
        if ($this->course_db->rows()) {
            return $this->course_db->rows()[0];
        }
        return null;
    }

    public function changeQueueToken($token, $queue_code) {
        $this->course_db->query("UPDATE queue_settings SET token = ? WHERE code = ?", [$token, $queue_code]);
    }

    public function changeQueueRegex($regex_pattern, $queue_code) {
        $this->course_db->query("UPDATE queue_settings SET regex_pattern = ? WHERE code = ?", [$regex_pattern, $queue_code]);
    }

    public function changeQueueContactInformation(bool $contact_information, string $queue_code) {
        $this->course_db->query("UPDATE queue_settings SET contact_information = ? WHERE code = ?", [$contact_information, $queue_code]);
    }

    public function getNumberAheadInQueueThisWeek($queue_code, $time_in) {
        $day_threshold = $this->core->getDateTimeNow()->modify('-4 day')->format('Y-m-d 00:00:00O');
        $this->course_db->query("SELECT count(*) from queue where last_time_in_queue < ? AND UPPER(TRIM(queue_code)) = UPPER(TRIM(?)) and current_state IN ('waiting') and time_in < ?", [$day_threshold, $queue_code, $time_in]);
        return $this->course_db->row()['count'];
    }

    public function getNumberAheadInQueueToday($queue_code, $time_in) {
        $current_date = $this->core->getDateTimeNow()->format('Y-m-d 00:00:00O');
        $day_threshold = $this->core->getDateTimeNow()->modify('-4 day')->format('Y-m-d 00:00:00O');
        $this->course_db->query("SELECT count(*) from queue where last_time_in_queue < ? AND last_time_in_queue > ? AND UPPER(TRIM(queue_code)) = UPPER(TRIM(?)) and current_state IN ('waiting') and time_in < ?", [$current_date, $day_threshold, $queue_code, $time_in]);
        return $this->course_db->row()['count'];
    }

    public function getAllQueuesEver() {
        $this->course_db->query("SELECT DISTINCT queue_code FROM queue
                                UNION
                                SELECT DISTINCT code as queue_code FROM queue_settings");
        return $this->course_db->rows();
    }

    public function getQueueDataStudent() {
        $this->course_db->query("SELECT
                *
              FROM (SELECT
                user_id AS id,
                CASE
                  WHEN user_preferred_givenname IS NULL THEN user_givenname
                  ELSE user_preferred_givenname
                END AS given_name,
                CASE
                  WHEN user_preferred_familyname IS NULL THEN user_familyname
                  ELSE user_preferred_familyname
                END AS familyname
              FROM users
              WHERE user_group = 4) AS user_data
              LEFT JOIN (SELECT
                user_id,"
                . $this->getInnerQueueSelect() .
               ",SUM(CASE
                  WHEN removal_type IN ('removed', 'emptied', 'self') THEN 1
                  ELSE 0
                END) AS not_helped_count
              FROM queue
              GROUP BY user_id) AS queue_data
                ON queue_data.user_id = user_data.id
              ORDER BY queue_data.user_id");
        return $this->course_db->rows();
    }

    public function getQueueDataOverall() {
        $this->course_db->query("SELECT"
                . $this->getInnerQueueSelect() .
               "FROM queue");
        return $this->course_db->rows();
    }

    public function getQueueDataToday() {
        $current_date = $this->core->getDateTimeNow()->format('Y-m-d 00:00:00O');
        $this->course_db->query(
            "SELECT"
                 . $this->getInnerQueueSelect() .
                "FROM queue
                 WHERE time_in > ?",
            [$current_date]
        );
        return $this->course_db->rows();
    }

    public function getQueueDataByQueue() {
        $this->course_db->query("SELECT
                 queue_code,"
                 . $this->getInnerQueueSelect() .
              "FROM queue
               GROUP BY queue_code
               ORDER BY queue_code");
        return $this->course_db->rows();
    }

    public function getQueueDataByWeekDay() {
        $this->course_db->query("SELECT
              dow,"
              . $this->getInnerQueueSelect() .
           "FROM (SELECT
              *,
              extract(dow from time_in) AS dow
            FROM queue) AS dow_queue
            GROUP BY dow
            ORDER BY dow");
        return $this->course_db->rows();
    }

    public function getQueueDataByWeekDayThisWeek() {
        $current_date = $this->core->getDateTimeNow()->format('Y-m-d 00:00:00O');
        $this->course_db->query(
            "SELECT
              dow,"
              . $this->getInnerQueueSelect() .
            "FROM (SELECT
                *,
                extract(dow from time_in) AS dow
                FROM queue
                WHERE extract(WEEK from time_in) = extract(WEEK from ?::DATE)
            )
            AS dow_queue
            GROUP BY dow
            ORDER BY dow",
            [$current_date]
        );
        return $this->course_db->rows();
    }

    public function getQueueDataByWeekNumber() {
        $this->course_db->query("SELECT
              weeknum,
      			  min(yearnum) as yearnum,"
              . $this->getInnerQueueSelect() .
           "FROM (SELECT
              *,
              extract(WEEK from time_in) AS weeknum,
			        extract(YEAR from time_in) AS yearnum
            FROM queue) AS weeknum_queue
            GROUP BY weeknum
            ORDER BY weeknum");
        return $this->course_db->rows();
    }

    public function setQueueMessage($queue_code, $message) {
        //clear the message
        if ($message === 'null') {
            $this->course_db->query("UPDATE queue_settings SET message = null WHERE UPPER(TRIM(code)) = UPPER(TRIM(?)) ", [$queue_code]);
        }
        else {
            $current_date = $this->core->getDateTimeNow();
            $this->course_db->query("UPDATE queue_settings SET message = ?, message_sent_time = ? WHERE  UPPER(TRIM(code)) = UPPER(TRIM(?)) ", [$message, $current_date, $queue_code]);
        }
    }

    public function getQueueMessage($queue_code) {
        $this->course_db->query("SELECT message, message_sent_time from queue_settings where UPPER(TRIM(code)) = UPPER(TRIM(?))", [$queue_code]);
        if (count($this->course_db->rows()) > 0) {
            return $this->course_db->rows()[0];
        }
        else {
            return null;
        }
    }



/////////////////END Office Hours Queue queries//////////////////////////////////



    /**
     * Gets all GradedGradeable's associated with each Gradeable.  If
     *  Note: The users' teams will be included in the search
     *
     * @param  \app\models\gradeable\Gradeable[] $gradeables The gradeable(s) to retrieve data for
     * @param  string[]|string|null              $users      The id(s) of the user(s) to get data for
     * @param  string[]|string|null              $teams      The id(s) of the team(s) to get data for
     * @param  string[]|string|null              $sort_keys  An ordered list of keys to sort by (i.e. `user_id` or `g_id DESC`)
     * @param  bool                              $team       True to get only team information, false to get only user information
     * @return \Iterator Iterator to access each GradeableData
     * @throws \InvalidArgumentException If any GradedGradeable or GradedComponent fails to construct
     */
    private function getGradedGradeablesUserOrTeam(array $gradeables, $users, $teams, $sort_keys, bool $team) {

        // Get the gradeables array into a lookup table by id
        $gradeables_by_id = [];
        foreach ($gradeables as $gradeable) {
            if (!($gradeable instanceof \app\models\gradeable\Gradeable)) {
                throw new \InvalidArgumentException('Gradeable array must only contain Gradeables');
            }
            $gradeables_by_id[$gradeable->getId()] = $gradeable;
        }
        if (count($gradeables_by_id) === 0) {
            return new \EmptyIterator();
        }

        // If one array is blank, and the other is null or also blank, don't get anything
        if (
            ($users === [] && $teams === null)
            || ($users === null && $teams === [])
            || ($users === [] && $teams === [])
        ) {
            return new \EmptyIterator();
        }

        // Make sure that our users/teams are arrays
        if ($users !== null) {
            if (!is_array($users)) {
                $users = [$users];
            }
        }
        else {
            $users = [];
        }
        if ($teams !== null) {
            if (!is_array($teams)) {
                $teams = [$teams];
            }
        }
        else {
            $teams = [];
        }

        $users = array_values($users);
        $teams = array_values($teams);

        //
        // Generate selector for the submitters the user wants
        //
        // If both are zero-count, that indicates to get all users/teams
        $all = (count($users) === count($teams)) && count($users) === 0;

        // switch the join type depending on the boolean
        $submitter_type = $team ? 'team_id' : 'user_id';
        $submitter_type_ext = $team ? 'team.team_id' : 'u.user_id';

        // Generate a logical expression from the provided parameters
        $selector_union_list = [];
        $selector_union_list[] = strval($this->course_db->convertBoolean($all));

        $selector_intersection_list = [];

        $param = [];

        // If Users were provided, switch between single users and team members
        if (count($users) > 0) {
            $user_placeholders = implode(',', array_fill(0, count($users), '?'));
            $param = $users;
            if (!$team) {
                $selector_union_list[] = "u.user_id IN ($user_placeholders)";
            }
            else {
                // Select the users' teams as well
                $selector_union_list[] = "team.team_id IN (SELECT team_id FROM teams WHERE state=1 AND user_id IN ($user_placeholders))";
            }
        }

        $submitter_inject = 'ERROR ERROR';
        $submitter_data_inject = 'ERROR ERROR';
        if ($team) {
            $submitter_data_inject =
              'ldet.array_late_day_exceptions,
               ldet.array_reason_for_exception,
               ldet.array_late_day_user_ids,
               /* Aggregate Team User Data */
               team.team_id,
               team.array_team_users,
               team.registration_section,
               team.rotating_section,
               team.team_name';

            $submitter_inject = '
              JOIN (
                SELECT gt.team_id,
                  gt.registration_section,
                  gt.rotating_section,
                  gt.team_name,
                  json_agg(tu) AS array_team_users
                FROM gradeable_teams gt
                  JOIN (
                    SELECT
                      t.team_id,
                      t.state,
                      tu.*
                    FROM teams t
                    JOIN users tu ON t.user_id = tu.user_id ORDER BY t.user_id
                  ) AS tu ON gt.team_id = tu.team_id
                GROUP BY gt.team_id
              ) AS team ON eg.team_assignment AND EXISTS (
                SELECT 1 FROM gradeable_teams gt
                WHERE gt.team_id=team.team_id AND gt.g_id=g.g_id
                LIMIT 1)

              /* Join team late day exceptions */
              LEFT JOIN (
                SELECT
                  json_agg(e.late_day_exceptions) AS array_late_day_exceptions,
                  json_agg(e.reason_for_exception) AS array_reason_for_exception,
                  json_agg(e.user_id) AS array_late_day_user_ids,
                  t.team_id,
                  g_id
                FROM late_day_exceptions e
                LEFT JOIN teams t ON e.user_id=t.user_id AND t.state=1
                GROUP BY team_id, g_id
              ) AS ldet ON g.g_id=ldet.g_id AND ldet.team_id=team.team_id';
        }
        else {
            $submitter_data_inject = '
              u.user_id,
              u.user_numeric_id,
              u.g_anon,
              u.user_givenname,
              u.user_preferred_givenname,
              u.user_familyname,
              u.user_pronouns,
              u.display_pronouns,
              u.user_preferred_familyname,
              u.user_email,
              u.user_email_secondary,
              u.user_email_secondary_notify,
              u.user_group,
              u.manual_registration,
              u.last_updated,
              u.grading_registration_sections,
              u.registration_section,
              u.course_section_id,
              u.rotating_section,
              u.registration_type,
              ldeu.late_day_exceptions,
              ldeu.reason_for_exception,
              u.registration_subsection';
            $submitter_inject = '
            JOIN (
                SELECT u.*, ga.anon_id AS g_anon, ga.g_id, sr.grading_registration_sections, sec_reg.course_section_id
                FROM users u
                LEFT JOIN gradeable_anon ga
                ON u.user_id=ga.user_id
                LEFT JOIN (
                    SELECT
                        json_agg(sections_registration_id) AS grading_registration_sections,
                        user_id
                    FROM grading_registration
                    GROUP BY user_id
                ) AS sr ON u.user_id=sr.user_id
                LEFT JOIN (
                    SELECT
                        sections_registration_id,
                        course_section_id
                    FROM sections_registration
                ) AS sec_reg ON sec_reg.sections_registration_id=u.registration_section
            ) AS u ON (eg IS NULL OR NOT eg.team_assignment) AND u. g_id=g.g_id

            /* Join user late day exceptions */
            LEFT JOIN late_day_exceptions ldeu ON g.g_id=ldeu.g_id AND u.user_id=ldeu.user_id';
        }
        if ($team && count($teams) > 0) {
            $team_placeholders = implode(',', array_fill(0, count($teams), '?'));
            $selector_union_list[] = "team.team_id IN ($team_placeholders)";
            $param = array_merge($param, $teams);
        }

        $selector_intersection_list[] = '(' . implode(' OR ', $selector_union_list) . ')';

        //
        // Generate selector for the gradeables the user wants
        //
        $gradeable_placeholders = implode(',', array_fill(0, count($gradeables_by_id), '?'));
        $selector_intersection_list[] = "g.g_id IN ($gradeable_placeholders)";

        // Create the complete selector
        $selector = implode(' AND ', $selector_intersection_list);

        // Generate the ORDER BY clause
        $order = self::generateOrderByClause($sort_keys, $team ? self::graded_gradeable_key_map_team : self::graded_gradeable_key_map_user);

        $query = "
            SELECT /* Select everything we retrieved */

              g.g_id,

              /* Gradeable Data */
              gd.gd_id AS id,
              gd.gd_user_viewed_date AS user_viewed_date,

              /* get the overall comment */
              goc.commenter_ids AS array_commenter_ids,
              goc.overall_comments AS array_overall_comments,

              /* Aggregate Gradeable Component Data */
              gcd.array_comp_id,
              gcd.array_score,
              gcd.array_comment,
              gcd.array_grader_id,
              gcd.array_graded_version,
              gcd.array_grade_time,
              gcd.array_mark_id,
              gcd.array_verifier_id,
              gcd.array_verify_time,

              /* Aggregate Gradeable Component Grader Data */
              gcd.array_grader_user_id,
              gcd.array_grader_anon_id,
              gcd.array_grader_user_givenname,
              gcd.array_grader_user_preferred_givenname,
              gcd.array_grader_user_pronouns,
              gcd.array_grader_display_pronouns,
              gcd.array_grader_user_familyname,
              gcd.array_grader_user_email,
              gcd.array_grader_user_email_secondary,
              gcd.array_grader_user_email_secondary_notify,
              gcd.array_grader_user_group,
              gcd.array_grader_manual_registration,
              gcd.array_grader_last_updated,
              gcd.array_grader_registration_section,
              gcd.array_grader_rotating_section,
              gcd.array_grader_registration_type,
              gcd.array_grader_grading_registration_sections,

              /* Aggregate Gradeable Component Verifier Data */
              gcd.array_verifier_user_id,
              gcd.array_verifier_user_givenname,
              gcd.array_verifier_user_preferred_givenname,
              gcd.array_verifier_user_pronouns,
              gcd.array_verifier_display_pronouns,
              gcd.array_verifier_user_familyname,
              gcd.array_verifier_user_email,
              gcd.array_verifier_user_email_secondary,
              gcd.array_verifier_user_email_secondary_notify,
              gcd.array_verifier_user_group,
              gcd.array_verifier_manual_registration,
              gcd.array_verifier_last_updated,
              gcd.array_verifier_registration_section,
              gcd.array_verifier_rotating_section,
              gcd.array_verifier_registration_type,

              /* Aggregate Gradeable Component Data (versions) */
              egd.array_version,
              egd.array_non_hidden_non_extra_credit,
              egd.array_non_hidden_extra_credit,
              egd.array_hidden_non_extra_credit,
              egd.array_hidden_extra_credit,
              egd.array_submission_time,
              egd.array_autograding_complete,

              /* Active Submission Version */
              egv.active_version,

              /* Grade inquiry data */
             rr.array_grade_inquiries,
             gc.ag_graders AS ag_graders,
             gc.ag_timestamps AS ag_timestamps,
             gc.ag_graders_names AS ag_graders_names,

              {$submitter_data_inject}

            FROM gradeable g

              /* Get teamness so we know to join teams or users*/
              LEFT JOIN (
                SELECT
                  g_id,
                  eg_team_assignment AS team_assignment
                FROM electronic_gradeable
              ) AS eg ON eg.g_id=g.g_id

              /* Join submitter data */
              {$submitter_inject}

              /* Join manual grading data */
              LEFT JOIN (
                SELECT *
                FROM gradeable_data
              ) AS gd ON gd.g_id=g.g_id AND gd.gd_{$submitter_type}={$submitter_type_ext}

              LEFT JOIN LATERAL (
                SELECT
                  gc.g_id,
                  json_object_agg(gc.gc_id, graders) as ag_graders,
                  json_object_agg(gc.gc_id, graders_names) as ag_graders_names,
                  json_object_agg(gc.gc_id, timestamps) as ag_timestamps
                FROM gradeable_component gc
                LEFT JOIN (
                  SELECT
                    ag_{$submitter_type},
                    gc_id,
                    json_agg(grader_id) AS graders,
                    json_agg(COALESCE(NULLIF(user_preferred_givenname,''), user_givenname) || ' ' || substr(COALESCE(NULLIF(user_preferred_familyname,''), user_familyname), 1, 1) || '.') AS graders_names,
                    json_agg(timestamp) AS timestamps
                  FROM active_graders
                  LEFT JOIN users ON active_graders.grader_id = users.user_id
                  GROUP BY ag_{$submitter_type} , gc_id
                ) as ag on ag.gc_id = gc.gc_id AND ag.ag_{$submitter_type}={$submitter_type_ext}
                GROUP BY gc.g_id
              ) AS gc ON gc.g_id = g.g_id

              LEFT JOIN (
                SELECT
                    json_agg(goc_grader_id) as commenter_ids,
                    json_agg(goc_overall_comment) as overall_comments,
                    g_id,
                    goc_{$submitter_type}
                FROM gradeable_data_overall_comment
                GROUP BY g_id, goc_{$submitter_type}
              ) AS goc ON goc.g_id=g.g_id AND goc.goc_{$submitter_type}={$submitter_type_ext}

              /* Join aggregate gradeable component data */
              LEFT JOIN LATERAL (
                SELECT
                  json_agg(in_gcd.gc_id) AS array_comp_id,
                  json_agg(gcd_score) AS array_score,
                  json_agg(gcd_component_comment) AS array_comment,
                  json_agg(in_gcd.gcd_grader_id) AS array_grader_id,
                  json_agg(gcd_graded_version) AS array_graded_version,
                  json_agg(gcd_grade_time) AS array_grade_time,
                  json_agg(string_mark_id) AS array_mark_id,
                  json_agg(gcd_verifier_id) AS array_verifier_id,
                  json_agg(gcd_verify_time) AS array_verify_time,

                  json_agg(ug.user_id) AS array_grader_user_id,
                  json_agg(ug.g_anon) AS array_grader_anon_id,
                  json_agg(ug.user_givenname) AS array_grader_user_givenname,
                  json_agg(ug.user_preferred_givenname) AS array_grader_user_preferred_givenname,
                  json_agg(ug.user_pronouns) AS array_grader_user_pronouns,
                  json_agg(ug.display_pronouns) AS array_grader_display_pronouns,
                  json_agg(ug.user_familyname) AS array_grader_user_familyname,
                  json_agg(ug.user_email) AS array_grader_user_email,
                  json_agg(ug.user_email_secondary) AS array_grader_user_email_secondary,
                  json_agg(ug.user_email_secondary_notify) AS array_grader_user_email_secondary_notify,
                  json_agg(ug.user_group) AS array_grader_user_group,
                  json_agg(ug.manual_registration) AS array_grader_manual_registration,
                  json_agg(ug.last_updated) AS array_grader_last_updated,
                  json_agg(ug.registration_section) AS array_grader_registration_section,
                  json_agg(ug.rotating_section) AS array_grader_rotating_section,
                  json_agg(ug.registration_type) AS array_grader_registration_type,
                  json_agg(ug.grading_registration_sections) AS array_grader_grading_registration_sections,
                  json_agg(uv.user_id) AS array_verifier_user_id,
                  json_agg(uv.user_givenname) AS array_verifier_user_givenname,
                  json_agg(uv.user_preferred_givenname) AS array_verifier_user_preferred_givenname,
                  json_agg(uv.user_pronouns) AS array_verifier_user_pronouns,
                  json_agg(uv.display_pronouns) AS array_verifier_display_pronouns,
                  json_agg(uv.user_familyname) AS array_verifier_user_familyname,
                  json_agg(uv.user_email) AS array_verifier_user_email,
                  json_agg(uv.user_email_secondary) AS array_verifier_user_email_secondary,
                  json_agg(uv.user_email_secondary_notify) AS array_verifier_user_email_secondary_notify,
                  json_agg(uv.user_group) AS array_verifier_user_group,
                  json_agg(uv.manual_registration) AS array_verifier_manual_registration,
                  json_agg(uv.last_updated) AS array_verifier_last_updated,
                  json_agg(uv.registration_section) AS array_verifier_registration_section,
                  json_agg(uv.rotating_section) AS array_verifier_rotating_section,
                  json_agg(uv.registration_type) AS array_verifier_registration_type,
                  in_gcd.gd_id,
                  ug.g_id
                FROM (SELECT * FROM gradeable_component_data WHERE gd_id=gd.gd_id) in_gcd

                  LEFT JOIN (
                    SELECT
                      json_agg(gcm_id) AS string_mark_id,
                      gc_id,
                      gd_id,
                      gcd_grader_id
                    FROM gradeable_component_mark_data
                    GROUP BY gc_id, gd_id, gcd_grader_id
                  ) AS gcmd ON gcmd.gc_id=in_gcd.gc_id AND gcmd.gd_id=in_gcd.gd_id AND gcmd.gcd_grader_id=in_gcd.gcd_grader_id

                  /* Join grader data; TODO: do we want/need 'sr' information */
                  LEFT JOIN (
                    SELECT u.*, ga.anon_id AS g_anon, ga.g_id, grading_registration_sections
                    FROM users u
                    LEFT JOIN (
                      SELECT
                        json_agg(sections_registration_id) AS grading_registration_sections,
                        user_id
                      FROM grading_registration
                      GROUP BY user_id
                    ) AS sr ON u.user_id=sr.user_id
                    LEFT JOIN(
                        SELECT
                          anon_id,
                          user_id,
                          g_id
                        FROM gradeable_anon
                      ) AS ga ON u.user_id=ga.user_id AND ga.g_id=g.g_id
                  ) AS ug ON ug.user_id=in_gcd.gcd_grader_id
                  LEFT JOIN (
                    SELECT u.*
                    FROM users u
                  ) AS uv ON uv.user_id=in_gcd.gcd_verifier_id
                GROUP BY in_gcd.gd_id, ug.g_id
              ) AS gcd ON gcd.gd_id=gd.gd_id AND gcd.g_id=g.g_id

              /* Join aggregate gradeable version data */
              LEFT JOIN (
                SELECT
                  json_agg(in_egd.g_version) AS array_version,
                  json_agg(in_egd.autograding_non_hidden_non_extra_credit) AS array_non_hidden_non_extra_credit,
                  json_agg(in_egd.autograding_non_hidden_extra_credit) AS array_non_hidden_extra_credit,
                  json_agg(in_egd.autograding_hidden_non_extra_credit) AS array_hidden_non_extra_credit,
                  json_agg(in_egd.autograding_hidden_extra_credit) AS array_hidden_extra_credit,
                  json_agg(in_egd.submission_time) AS array_submission_time,
                  json_agg(in_egd.autograding_complete) AS array_autograding_complete,
                  g_id,
                  user_id,
                  team_id
                FROM electronic_gradeable_data AS in_egd
                GROUP BY g_id, user_id, team_id
              ) AS egd ON egd.g_id=g.g_id AND egd.{$submitter_type}={$submitter_type_ext}
              LEFT JOIN (
                SELECT *
                FROM electronic_gradeable_version
              ) AS egv ON egv.{$submitter_type}=egd.{$submitter_type} AND egv.g_id=egd.g_id

              /* Join grade inquiry */
              LEFT JOIN (
  				SELECT json_agg(rr) as array_grade_inquiries, user_id, team_id, g_id
  				FROM grade_inquiries AS rr
  				GROUP BY rr.user_id, rr.team_id, rr.g_id
  			  ) AS rr on egv.{$submitter_type}=rr.{$submitter_type} AND egv.g_id=rr.g_id
            WHERE $selector
            $order";


        $constructGradedGradeable = function ($row) use ($gradeables_by_id) {
            /** @var Gradeable $gradeable */
            $gradeable = $gradeables_by_id[$row['g_id']];

            // Get the submitter
            $submitter = null;
            if ($gradeable->isTeamAssignment()) {
                // Get the user data for the team
                $team_users = json_decode($row["array_team_users"], true);

                // Create the team with the query results and users array
                $submitter = new Team($this->core, array_merge($row, ['users' => $team_users]));

                // Get the late day exceptions for each user
                $late_day_exceptions = [];
                $reasons_for_exceptions = [];
                if (isset($row['array_late_day_user_ids'])) {
                    $late_day_exceptions = array_combine(
                        json_decode($row['array_late_day_user_ids']),
                        json_decode($row['array_late_day_exceptions'])
                    );
                    $reasons_for_exceptions = array_combine(
                        json_decode($row['array_late_day_user_ids']),
                        json_decode($row['array_reason_for_exception'])
                    );
                }
                foreach ($submitter->getMembers() as $user_id) {
                    if (!isset($late_day_exceptions[$user_id])) {
                        $late_day_exceptions[$user_id] = 0;
                    }
                    if (!isset($reasons_for_exceptions[$user_id])) {
                        $reasons_for_exceptions[$user_id] = '';
                    }
                }
            }
            else {
                if (isset($row['grading_registration_sections'])) {
                    $row['grading_registration_sections'] = json_decode($row['grading_registration_sections']);
                }
                $submitter = new User($this->core, $row);

                // Get the late day exception for the user
                $late_day_exceptions = [
                    $submitter->getId() => $row['late_day_exceptions'] ?? 0
                ];
                $reasons_for_exceptions = [
                    $submitter->getId() => $row['reason_for_exception'] ?? ''
                ];
            }

            // Create the graded gradeable instances
            $graded_gradeable = new GradedGradeable(
                $this->core,
                $gradeable,
                new Submitter($this->core, $submitter),
                [
                    'late_day_exceptions' => $late_day_exceptions,
                    'reasons_for_exceptions' => $reasons_for_exceptions
                ],
                json_decode($row['ag_graders'], true),
                json_decode($row['ag_timestamps'], true),
                json_decode($row['ag_graders_names'], true)
            );
            $ta_graded_gradeable = null;
            $auto_graded_gradeable = null;

            // This will be false if there is no manual grade yet
            if (isset($row['id'])) {
                // prepare overall comments
                $row["array_commenter_ids"] = json_decode($row["array_commenter_ids"] ?? "[]");
                $row["array_overall_comments"] = json_decode($row["array_overall_comments"] ?? "[]");
                $row["overall_comments"] = [];
                if ($row["array_commenter_ids"] !== null) {
                    for ($i = 0; $i < count($row["array_commenter_ids"]); $i++) {
                        $commenter = $row["array_commenter_ids"][$i];
                        $comment   = $row["array_overall_comments"][$i];
                        $row["overall_comments"][$commenter] = $comment;
                    }
                }
                $ta_graded_gradeable = new TaGradedGradeable($this->core, $graded_gradeable, $row);
                $graded_gradeable->setTaGradedGradeable($ta_graded_gradeable);
            }

            // Always construct an instance even if there is no data
            $auto_graded_gradeable = new AutoGradedGradeable($this->core, $graded_gradeable, $row);
            $graded_gradeable->setAutoGradedGradeable($auto_graded_gradeable);

            if (isset($row['array_grade_inquiries'])) {
                $grade_inquiries = json_decode($row['array_grade_inquiries'], true);
                $grade_inquiries_arr = [];
                foreach ($grade_inquiries as $grade_inquiry) {
                    $grade_inquiries_arr[] = new GradeInquiry($this->core, $grade_inquiry);
                }

                $graded_gradeable->setGradeInquiries($grade_inquiries_arr);
            }

            $graded_components_by_id = [];
            /** @var AutoGradedVersion[] $graded_versions */
            $graded_versions = [];

            // Break down the graded component / version / grader data into an array of arrays
            //  instead of arrays of sql array-strings
            $user_properties = [
                'user_id',
                'anon_id',
                'user_givenname',
                'user_preferred_givenname',
                'user_pronouns',
                'display_pronouns',
                'user_familyname',
                'user_email',
                'user_email_secondary',
                'user_email_secondary_notify',
                'user_group',
                'manual_registration',
                'last_updated',
                'registration_section',
                'rotating_section',
                'registration_type',
                'grading_registration_sections'
            ];
            $comp_array_properties = [
                'comp_id',
                'score',
                'comment',
                'grader_id',
                'graded_version',
                'grade_time',
                'mark_id',
                'verifier_id',
                'verify_time'
            ];
            $version_array_properties = [
                'version',
                'non_hidden_non_extra_credit',
                'non_hidden_extra_credit',
                'hidden_non_extra_credit',
                'hidden_extra_credit',
                'submission_time',
                'autograding_complete'
            ];
            $db_row_split = [];
            foreach (
                array_merge(
                    $version_array_properties,
                    $comp_array_properties,
                    array_map(
                        function ($elem) {
                            return 'grader_' . $elem;
                        },
                        $user_properties
                    ),
                    array_map(
                        function ($elem) {
                            return 'verifier_' . $elem;
                        },
                        array_diff(
                            $user_properties,
                            ['anon_id', 'grading_registration_sections']
                        )
                    )
                ) as $property
            ) {
                $db_row_split[$property] = json_decode($row['array_' . $property] ?? "");
            }

            if (isset($db_row_split['comp_id'])) {
                // Create all of the GradedComponents
                for ($i = 0; $i < count($db_row_split['comp_id']); ++$i) {
                    // Create a temporary array for each graded component instead of trying
                    //  to transpose the entire $db_row_split array
                    $comp_array = [];
                    foreach ($comp_array_properties as $property) {
                        $comp_array[$property] = $db_row_split[$property][$i];
                    }

                    //  Similarly, transpose just this grader
                    $user_array = [];
                    foreach ($user_properties as $property) {
                        $user_array[$property] = $db_row_split['grader_' . $property][$i];
                    }

                    // Create the grader user
                    $grader = new User($this->core, $user_array);

                    // Transpose the verifier if it exists
                    $verifier = null;
                    $verifier_array = [];
                    if (isset($db_row_split['verifier_user_id'][$i]) && $db_row_split['verifier_user_id'][$i] !== null) {
                        foreach ($user_properties as $property) {
                            if (isset($db_row_split['verifier_' . $property][$i])) {
                                $verifier_array[$property] = $db_row_split['verifier_' . $property][$i];
                            }
                        }
                        $verifier = new User($this->core, $verifier_array);
                    }

                    // Create the component
                    $graded_component = new GradedComponent(
                        $this->core,
                        $ta_graded_gradeable,
                        $gradeable->getComponent($db_row_split['comp_id'][$i]),
                        $grader,
                        $comp_array,
                        $verifier
                    );

                    $graded_component->setMarkIdsFromDb($db_row_split['mark_id'][$i] ?? []);
                    $graded_components_by_id[$graded_component->getComponentId()][] = $graded_component;
                }


                // Create containers for each component
                $containers = [];
                foreach ($gradeable->getComponents() as $component) {
                    $container = new GradedComponentContainer($this->core, $ta_graded_gradeable, $component);
                    $container->setGradedComponents($graded_components_by_id[$component->getId()] ?? []);
                    $containers[$component->getId()] = $container;
                }
                $ta_graded_gradeable->setGradedComponentContainersFromDatabase($containers);
            }

            if (isset($db_row_split['version'])) {
                // Create all of the AutoGradedVersions
                for ($i = 0; $i < count($db_row_split['version']); ++$i) {
                    // Similarly, transpose each version
                    $version_array = [];
                    foreach ($version_array_properties as $property) {
                        $version_array[$property] = $db_row_split[$property][$i];
                    }

                    $version = new AutoGradedVersion($this->core, $graded_gradeable, $version_array);
                    $graded_versions[$version->getVersion()] = $version;
                }
                $auto_graded_gradeable->setAutoGradedVersions($graded_versions);
            }

            return $graded_gradeable;
        };

        return $this->course_db->queryIterator(
            $query,
            array_merge(
                $param,
                array_keys($gradeables_by_id)
            ),
            $constructGradedGradeable
        );
    }

    /**
     * Given a user_id check the users table for a valid entry, returns a user object if found,
     * null otherwise. If is_numeric is true, the numeric_id key will be used to lookup the user.
     * This should be called through getUserById() or getUserByNumericId().
     *
     * @param string|int $user_id
     * @param bool $is_numeric
     * @return User|null
     */
    private function getUser($user_id, bool $is_numeric = false): ?User {
        $result = $this->getUsers([$user_id], $is_numeric);
        if ($result !== null && count($result) === 1) {
            //return first element
            return array_pop($result);
        }
        else {
            return null;
        }
    }

    /**
     * Given an array of user IDs, return an array of corresponding user objects or null if any one of them wasn't found
     *
     * @param array $user_id_list
     * @param bool $is_numeric
     * @return array|null
     */
    private function getUsers(array $user_id_list, bool $is_numeric = false): ?array {
        if (count($user_id_list) === 0) {
            return null;
        }
        $placeholders = $this->createParameterList(count($user_id_list));

        if (!$is_numeric) {
            $this->submitty_db->query("SELECT * FROM users WHERE user_id IN {$placeholders}", $user_id_list);
        }
        else {
            $this->submitty_db->query("SELECT * FROM users WHERE user_numeric_id IN {$placeholders}", $user_id_list);
        }

        if ($this->submitty_db->getRowCount() === 0) {
            return null;
        }

        $details_list = $this->submitty_db->rows();

        if ($this->course_db) {
            $this->course_db->query(
                "
            SELECT u.*, ns.merge_threads, ns.all_new_threads,
                 ns.all_new_posts, ns.all_modifications_forum,
                 ns.reply_in_post_thread,ns.team_invite,
                 ns.team_member_submission, ns.team_joined,
                 ns.self_notification,
                 ns.merge_threads_email, ns.self_registration_email, ns.all_new_threads_email,
                 ns.all_new_posts_email, ns.all_modifications_forum_email,
                 ns.reply_in_post_thread_email, ns.team_invite_email,
                 ns.team_member_submission_email, ns.team_joined_email,
                 ns.self_notification_email,sr.grading_registration_sections

            FROM users u
            LEFT JOIN notification_settings as ns ON u.user_id = ns.user_id
            LEFT JOIN (
              SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
              FROM grading_registration
              GROUP BY user_id
            ) as sr ON u.user_id=sr.user_id
            WHERE u.user_id IN {$placeholders}",
                $user_id_list
            );

            $updated_details_list = [];
            if ($this->course_db->getRowCount() > 0) {
                $i = 0;
                foreach ($details_list as $details) {
                    $user = $this->course_db->rows()[$i];
                    if (isset($user['grading_registration_sections'])) {
                        $user['grading_registration_sections'] = $this->course_db->fromDatabaseToPHPArray($user['grading_registration_sections']);
                    }
                    $updated_details_list[] = array_merge($details, $user);
                    $i++;
                }
                $details_list = $updated_details_list;
            }
        }

        $users = [];
        foreach ($details_list as $details) {
            $users[$details['user_id']] = new User($this->core, $details);
        }

        return $users;
    }



    /**
     * Maps sort keys to an array of expressions to sort by in place of the key.
     *  Useful for ambiguous keys or for key alias's
     */
    const graded_gradeable_key_map_user = [
        'registration_section' => [
            'SUBSTRING(u.registration_section, \'^[^0-9]*\')',
            'COALESCE(SUBSTRING(u.registration_section, \'[0-9]+\')::INT, -1)',
            'SUBSTRING(u.registration_section, \'[^0-9]*$\')',
        ],
        'rotating_section' => [
            'u.rotating_section',
        ],
        'team_id' => [],
        'section_subsection' => [
            'u.registration_subsection',
        ]
    ];
    const graded_gradeable_key_map_team = [
        'registration_section' => [
            'SUBSTRING(team.registration_section, \'^[^0-9]*\')',
            'COALESCE(SUBSTRING(team.registration_section, \'[0-9]+\')::INT, -1)',
            'SUBSTRING(team.registration_section, \'[^0-9]*$\')'
        ],
        'rotating_section' => [
            'team.rotating_section'
        ],
        'team_id' => [
            'team.team_id'
        ],
        'user_id' => [],
        'section_subsection' => [
            'u.registration_subsection'
        ]
    ];

    /**
     * Gets Total Number of Submissions on a Gradeable
     *
     * @param string $g_id the gradeable id to check for
     */
    public function getTotalSubmissions($g_id) {
        $this->course_db->query('SELECT * FROM electronic_gradeable_data WHERE g_id= ?', [$g_id]);
        return count($this->course_db->rows());
    }

    /**
     * Generates the ORDER BY clause with the provided sorting keys.
     *
     * For every element in $sort_keys, checks if the first word is in $key_map,
     * and if so, replaces it with the list of clauses in $key_map, and then joins that
     * list together using the second word (if it exists, assuming it is an order direction)
     * or blank otherwise. If the first word is not, return the clause as is. Finally,
     * join the resulting set of clauses together as appropriate for valid ORDER BY.
     *
     * @param  string[]|null $sort_keys
     * @param  array         $key_map   A map from sort keys to arrays of expressions to sort by instead
     *                                  (see self::graded_gradeable_key_map for example)
     * @return string
     */

    private static function generateOrderByClause($sort_keys, array $key_map) {
        if ($sort_keys !== null) {
            if (!is_array($sort_keys)) {
                $sort_keys = [$sort_keys];
            }
            if (count($sort_keys) === 0) {
                return '';
            }
            // Use simplified expression for empty keymap
            if (empty($key_map)) {
                return 'ORDER BY ' . implode(',', $sort_keys);
            }
            return 'ORDER BY ' . implode(
                ',',
                array_filter(
                    array_map(
                        function ($key_ext) use ($key_map) {
                            $split_key = explode(' ', $key_ext);
                            $key = $split_key[0];
                            if (isset($key_map[$key])) {
                                // Map any keys with special requirements to the proper statements and preserve specified order
                                $order = '';
                                if (count($split_key) > 1) {
                                    $order = $split_key[1];
                                }
                                if (count($key_map[$key]) === 0) {
                                    return '';
                                }
                                return implode(" $order,", $key_map[$key]) . " $order";
                            }
                            else {
                                return $key_ext;
                            }
                        },
                        $sort_keys
                    ),
                    function ($a) {
                        return $a !== '';
                    }
                )
            );
        }
        return '';
    }

    /**
     * Delete user from DBs identified by user_id, semester, and course.
     *
     * Query issues DELETE on master DB's courses_users table.  When potentially
     * successful, trigger function will attempt to delete user from course DB.
     * If the trigger fails, it is expected that user is also not removed from
     * master DB.  When user cannot be deleted (probably due to referential
     * integrity), query is expected to return 0 rows to indicate user was not
     * deleted.
     *
     * @param string $user_id
     * @param string $semester
     * @param string $course
     * @return bool false on failure (or 0 rows deleted), true otherwise.
     */
    public function deleteUser(string $user_id, string $semester, string $course): bool {
        $query = "DELETE FROM courses_users WHERE user_id=? AND term=? AND course=?";
        $this->submitty_db->query($query, [$user_id, $semester, $course]);
        return $this->submitty_db->getRowCount() > 0;
    }

    /**
     * Demote grader to a student, identified by user_id, semester, and course.
     * Set user group to 4 (student) and the query is successful if the row
     * count (number of affected rows) is positive.
     *
     * @param string $user_id
     * @param string $semester
     * @param string $course
     * @return bool false on failure, true otherwise
     */
    public function demoteGrader(string $user_id, string $semester, string $course): bool {
        $query = <<<SQL
UPDATE courses_users
SET user_group = 4
WHERE user_id=? AND term=? AND course=?
SQL;
        $this->submitty_db->query($query, [$user_id, $semester, $course]);
        return $this->submitty_db->getRowCount() > 0;
    }

    /**
     * Insert access attempt to a given gradeable by a user.
     */
    public function insertGradeableAccess(
        string $g_id,
        ?string $user_id,
        ?string $team_id,
        ?string $accessor_id
    ): void {
        $query = <<<SQL
INSERT INTO gradeable_access (g_id, user_id, team_id, accessor_id, "timestamp")
VALUES (?, ?, ?, ?, ?)
SQL;
        $this->course_db->query($query, [$g_id, $user_id, $team_id, $accessor_id, $this->core->getDateTimeNow()]);
    }

    public function getGradeableAccessUser(
        string $g_id,
        string $user_id
    ): array {
        $this->course_db->query(
            'SELECT * FROM gradeable_access WHERE g_id=? AND user_id=? ORDER BY "timestamp"',
            [$g_id, $user_id]
        );
        return $this->course_db->rows();
    }

    public function getGradeableAccessTeam(
        string $g_id,
        string $team_id
    ): array {
        $this->course_db->query(
            'SELECT * FROM gradeable_access WHERE g_id=? AND team_id=? ORDER BY "timestamp"',
            [$g_id, $team_id]
        );
        return $this->course_db->rows();
    }

    public function getUserGroups(string $user_id): array {
        $this->submitty_db->query(
            'SELECT DISTINCT c.group_name FROM courses c INNER JOIN courses_users cu on c.course = cu.course AND
                   c.term = cu.term WHERE cu.user_id = ? AND user_group = 1 AND c.group_name != \'root\'',
            [$user_id]
        );
        return $this->submitty_db->rows();
    }

    public function courseExists(string $semester, string $course): bool {
        $this->submitty_db->query(
            'SELECT * FROM courses WHERE term=? AND course=?',
            [$semester, $course]
        );
        return $this->submitty_db->getRowCount() === 1;
    }

    public function getOtherCoursesWithSameGroup(string $semester, string $course): array {
        $this->submitty_db->query(
            "SELECT c2.course, c2.term FROM courses c1 INNER JOIN courses c2 ON c1.group_name = c2.group_name
                   WHERE c1.term = ? AND c1.course = ? AND c1.group_name != 'root'",
            [$semester, $course]
        );
        return $this->submitty_db->rows();
    }

    private function getInnerQueueSelect(): string {
        return <<<SQL

      COUNT(*) AS queue_interactions,
      COUNT(DISTINCT user_id) AS number_distinct_students,
      DATE_TRUNC('second', AVG(time_out - time_help_start)) AS avg_help_time,
      MIN(time_out - time_help_start) AS min_help_time,
      MAX(time_out - time_help_start) AS max_help_time,
      DATE_TRUNC('second', AVG(time_help_start - time_in)) AS avg_wait_time,
      MIN(time_help_start - time_in) AS min_wait_time,
      MAX(time_help_start - time_in) AS max_wait_time,
      SUM(CASE
        WHEN removal_type IN ('helped', 'self_helped') THEN 1
        ELSE 0
      END) AS help_count,
      SUM(CASE
        WHEN removal_type IN ('removed', 'emptied', 'self') THEN 1
        ELSE 0
      END) AS not_helped_count

SQL;
    }

    /**
     * Gets the number of students who have submitted to a given gradeable
     *
     * @param string $gradeable_id
     * @return int
     */
    public function getTotalStudentsWithSubmissions(string $gradeable_id): int {
        $this->course_db->query('SELECT DISTINCT COUNT(*) user_id FROM electronic_gradeable_data WHERE g_id=?', [$gradeable_id]);
        return $this->course_db->row()["user_id"];
    }

    /**
     * Gets the total number of submissions made to a given gradeable
     *
     * @param string $gradeable_id
     * @return int
     */
    public function getTotalSubmissionsToGradeable(string $gradeable_id): int {
        $this->course_db->query('SELECT COUNT(*) FROM electronic_gradeable_data WHERE g_id=?', [$gradeable_id]);
        return $this->course_db->row()["count"];
    }

    /**
     * Gets the autograding metrics for a testcase
     *
     * @param string $user_id
     * @param string $gradeable_id
     * @param string $testcase_id
     * @param int $version the submission version that is currently being looked at
     */
    public function getMetrics(string $user_id, string $gradeable_id, string $testcase_id, int $version): array {
        $this->course_db->query("
            SELECT elapsed_time, max_rss_size FROM autograding_metrics
                WHERE
                    user_id = ?
                    AND g_id = ?
                    AND testcase_id = ?
                    AND g_version = cast(? AS int)
        ", [$user_id, $gradeable_id, $testcase_id, $version]);
        return $this->course_db->row();
    }

    /**
     * Gets the autograding metrics for a specific version and testcase
     *
     * @param string $user_id
     * @param string $gradeable_id
     * @param int $version
     * @return array{total_elapsed_time: float, total_max_rss_size: int}
     */
    public function getMetricSum(string $user_id, string $gradeable_id, int $version): array {
        $query = "
        SELECT SUM(elapsed_time) AS total_elapsed_time, MAX(max_rss_size) AS total_max_rss_size
        FROM autograding_metrics
        WHERE user_id = ?
            AND g_id = ?
            AND g_version = cast(? AS int)
    ";

        $this->course_db->query($query, [$user_id, $gradeable_id, $version]);
        return $this->course_db->rows();
    }

    /**
     * Gets a gradeable leaderboard
     * TODO get this working for teams
     *
     * @param string $gradeable_id
     * @param bool $countHidden true when leaderboard should include hidden testcases
     * @param array $valid_testcases a list of testcases to use in leaderboard
     */
    public function getLeaderboard(string $gradeable_id, bool $countHidden, array $valid_testcases): array {
        if ($this->core->getQueries()->getGradeableConfig($gradeable_id)->isTeamAssignment()) {
            throw new NotImplementedException("Leaderboards do not work for teams");
        }

        $param_list = $this->createParameterList(count($valid_testcases));

        array_unshift($valid_testcases, $gradeable_id);
        $valid_testcases[] = $countHidden;



        $this->course_db->query("
SELECT    leaderboard.*,
          gradeable_anon.anon_id,
          user_group,
          anonymous_leaderboard,
          Concat(
              COALESCE (user_preferred_givenname, user_givenname),
              ' ',
              COALESCE (user_preferred_familyname, user_familyname)
          ) as name
FROM (
                   SELECT     Round(Cast(Sum(elapsed_time) AS NUMERIC), 1) AS time,
                              Sum(max_rss_size)                            AS memory,
                              metrics.g_id                                 AS gradeable_id,
                              metrics.user_id                              AS user_id,
                              metrics.team_id                              AS team_id,
                              Sum(points)                                  AS points
                   FROM       autograding_metrics                          AS metrics
                   INNER JOIN electronic_gradeable_version                 AS version
                   ON         NULLIF(metrics.user_id, '') = NULLIF(version.user_id, '')
                   AND        metrics.g_id = version.g_id
                   AND        metrics.g_version = version.active_version
                   WHERE      metrics.g_id = ?
                   AND        passed = true
                   AND        testcase_id in {$param_list}
                              -- When true, this statement is always true, and so the value in the hidden column is ignored
                              -- When false, hidden values are left out of the query
                   AND        (
                                         hidden = false
                              OR         hidden = ?)
                   GROUP BY   metrics.user_id,
                              metrics.team_id,
                              metrics.g_id
) AS leaderboard
LEFT JOIN users
ON        leaderboard.user_id = users.user_id
LEFT JOIN gradeable_anon
ON leaderboard.user_id = gradeable_anon.user_id AND leaderboard.gradeable_id = gradeable_anon.g_id
LEFT JOIN electronic_gradeable_version
ON        leaderboard.gradeable_id = electronic_gradeable_version.g_id
          AND leaderboard.user_id = electronic_gradeable_version.user_id
ORDER BY
    points DESC,
    time,
    memory
        ", $valid_testcases);

        return $this->course_db->rows();
    }

    public function isUserAnonymousForGradeableLeaderboard(string $user_id, string $gradeable_id): bool {
        $this->course_db->query("
            SELECT COALESCE((SELECT anonymous_leaderboard
            FROM   electronic_gradeable_version
            WHERE  user_id = ?
                AND g_id = ?), false) AS anonymous_leaderboard
        ", [$user_id, $gradeable_id]);
        return $this->course_db->row()['anonymous_leaderboard'];
    }

    public function setUserAnonymousForGradeableLeaderboard(string $user_id, string $gradeable_id, bool $state): void {
        $this->course_db->query("
            UPDATE electronic_gradeable_version
            SET anonymous_leaderboard = ?
                WHERE
                    user_id = ?
                    AND g_id = ?
        ", [$state, $user_id, $gradeable_id]);
    }

    public function getSAMLAuthorizedUserIDs(string $saml_id): array {
        $this->submitty_db->query("
            SELECT user_id FROM saml_mapped_users WHERE saml_id = ? AND active = true
        ", [$saml_id]);
        return $this->submitty_db->rows();
    }

    public function getProxyMappedUsers(): array {
        $this->submitty_db->query("
            SELECT id, user_id, saml_id, active FROM saml_mapped_users
                WHERE saml_id != user_id ORDER BY saml_id, user_id;
        ");
        return $this->submitty_db->rows();
    }

    public function getSamlMappedUsers(): array {
        $this->submitty_db->query("
            SELECT id, user_id, saml_id FROM saml_mapped_users
                WHERE saml_id = user_id AND active = true;
        ");
        return $this->submitty_db->rows();
    }

    public function insertSamlMapping(string $saml_id, string $submitty_id) {
        $this->submitty_db->beginTransaction();
        $this->submitty_db->query("
            INSERT INTO saml_mapped_users (saml_id, user_id)
                VALUES (?, ?) ON CONFLICT (saml_id, user_id) DO UPDATE
                SET active = true;
        ", [$saml_id, $submitty_id]);
        $this->submitty_db->commit();
    }

    public function isSamlProxyUser(int $id): bool {
        $this->submitty_db->query("
            SELECT count(*) FROM saml_mapped_users WHERE
                id = ? AND user_id != saml_id
        ", [$id]);

        $row = $this->submitty_db->row();

        return $row['count'] > 0;
    }

    public function samlMappingDeletable(int $id): bool {
        $this->submitty_db->query("
            SELECT user_id FROM saml_mapped_users WHERE
                id = ? AND user_id != saml_id
        ", [$id]);

        $row = $this->submitty_db->rows();
        if (count($row) === 0) {
            return false;
        }

        $this->submitty_db->query("
            SELECT count(*) FROM saml_mapped_users WHERE
                user_id = ?
        ", [$row[0]["user_id"]]);

        $row = $this->submitty_db->row();
        if ($row['count'] < 2) {
            return false;
        }

        return true;
    }

    public function updateSamlMapping(int $id, bool $active) {
        $this->submitty_db->query("
            UPDATE saml_mapped_users SET active = ?
                WHERE id = ?;
        ", [$active, $id]);
    }

    public function deleteSamlMapping(int $id) {
        $this->submitty_db->query("
            DELETE FROM saml_mapped_users WHERE id = ?;
        ", [$id]);
    }

    public function checkNonMappedUsers() {
        $this->submitty_db->query("
            SELECT user_id FROM users WHERE user_id NOT IN
                (SELECT user_id FROM saml_mapped_users);
        ");
        return $this->rowsToArray($this->submitty_db->rows());
    }

    /**
     * Adds a component grader to the active_graders table
     */
    public function addComponentGrader(Component $component, bool $isTeam, string $grader_id, string $graded_id): void {
        $this->course_db->query("
            INSERT INTO active_graders (gc_id, grader_id, ag_user_id, ag_team_id, timestamp)
            VALUES (?, ?, ?, ?, ?) ON CONFLICT DO NOTHING
        ", [
            $component->getId(),
            $grader_id,
            $isTeam ? null : $graded_id,
            $isTeam ? $graded_id : null,
            $this->core->getDateTimeNow(),
        ]);
    }

    /**
     * Removes a component grader to the active_graders table
     */
    public function removeComponentGrader(Component $component, string $grader_id, string $graded_id): void {
            $this->course_db->query("
            DELETE FROM active_graders
            WHERE gc_id = ? AND grader_id = ? AND (ag_user_id = ? OR ag_team_id = ?)
            ", [
            $component->getId(),
            $grader_id,
            $graded_id,
            $graded_id,
            ]);
    }
    /**
     * @param string $image the full name of the image to get
     * @return string|false the user id of the image's owner or false if the image is not in the db
     */
    public function getDockerImageOwner(string $image): string|false {
        $this->submitty_db->query("SELECT image_name, user_id FROM docker_images WHERE image_name = ?", [$image]);
        if ($this->submitty_db->row() === []) {
            return false;
        }
        return $this->submitty_db->row()['user_id'] ?? '';
    }

    /**
     * @return array<string, string> the owners of all docker images, indexed by image name
     */
    public function getAllDockerImageOwners(): array {
        $result = [];
        $this->submitty_db->query("SELECT image_name, user_id FROM docker_images");
        foreach ($this->submitty_db->rows() as $row) {
            $result[$row['image_name']] = $row['user_id'] ?? '';
        }
        return $result;
    }

    /**
     * @param string $image the full name of the image to set ownership
     * @param string $user_id the user id of its owner.
     */
    public function setDockerImageOwner(string $image, string $user_id): void {
        $current_owner = $this->getDockerImageOwner($image);
        if ($current_owner === false) {
            $this->submitty_db->query("INSERT INTO docker_images (image_name, user_id) values (?, ?)", [$image,$user_id]);
        // If an instructor wants to add an image they didn't upload to a capability, the image will have no owner.
        // Only sysadmin will be able to remove it.
        }
        elseif ($current_owner !== $user_id) {
            $this->submitty_db->query("UPDATE docker_images SET user_id = NULL WHERE image_name = ?", [$image]);
        }
    }

    /**
     * @param string $image the full name of the image to remove
     * @param User $user the user who is removing the image
     * @return bool true if image was deleted, false otherwise
     */
    public function removeDockerImageOwner(string $image, User $user): bool {
        if ($user->getAccessLevel() === User::LEVEL_SUPERUSER) {
            $this->submitty_db->query("DELETE FROM docker_images WHERE image_name=?", [$image]);
        }
        else {
            $this->submitty_db->query("DELETE FROM docker_images WHERE image_name=? AND user_id=?", [$image, $user->getId()]);
        }
        return $this->submitty_db->getRowCount() > 0;
    }
}
