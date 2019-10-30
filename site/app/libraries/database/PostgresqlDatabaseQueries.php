<?php

namespace app\libraries\database;

use app\exceptions\DatabaseException;
use app\exceptions\ValidationException;
use app\libraries\CascadingIterator;
use app\libraries\GradeableType;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\Component;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedComponent;
use app\models\gradeable\GradedComponentContainer;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\Mark;
use app\models\gradeable\RegradeRequest;
use app\models\gradeable\Submitter;
use app\models\gradeable\TaGradedGradeable;
use app\models\User;
use app\models\SimpleLateUser;
use app\models\Team;
use app\models\SimpleStat;

class PostgresqlDatabaseQueries extends DatabaseQueries {

    //given a user_id check the users table for a valid entry, returns a user object if found, null otherwise
    //if is_numeric is true, the numeric_id key will be used to lookup the user
    //this should be called through getUserById() or getUserByNumericId()
    private function getUser($user_id, $is_numeric = false ){
        if(!$is_numeric){
            $this->submitty_db->query("SELECT * FROM users WHERE user_id=?", array($user_id));
        }else{
            $this->submitty_db->query("SELECT * FROM users WHERE user_numeric_id=?", array($user_id));
        }

        if ($this->submitty_db->getRowCount() === 0) {
            return null;
        }

        $details = $this->submitty_db->row();

        if ($this->course_db) {
            $this->course_db->query("
            SELECT u.*, ns.merge_threads, ns.all_new_threads,
                 ns.all_new_posts, ns.all_modifications_forum,
                 ns.reply_in_post_thread,ns.team_invite,
                 ns.team_member_submission, ns.team_joined,
                 ns.self_notification,
                 ns.merge_threads_email, ns.all_new_threads_email,
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
            WHERE u.user_id=?", array($user_id));

            if ($this->course_db->getRowCount() > 0) {
                $user = $this->course_db->row();
                if (isset($user['grading_registration_sections'])) {
                    $user['grading_registration_sections'] = $this->course_db->fromDatabaseToPHPArray($user['grading_registration_sections']);
                }
                $details = array_merge($details, $user);
            }
        }

        return new User($this->core, $details);
    }

    public function getUserById($user_id) {
        return $this->getUser($user_id);
    }

    public function getUserByNumericId($numeric_id) {
        return $this->getUser($numeric_id, true);
    }

    //looks up if the given id is a user_id, if null will then check
    //the numerical_id table
    public function getUserByIdOrNumericId($id){
        $ret = $this->getUser($id);
        if($ret === null ){
            return $this->getUser($id, true);
        }

        return $ret;
    }

    public function getGradingSectionsByUserId($user_id) {
        $this->course_db->query("
SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
FROM grading_registration
WHERE user_id=?
GROUP BY user_id", array($user_id));
        return $this->course_db->row();
    }

    public function getAllUsers($section_key="registration_section") {
        $keys = array("registration_section", "rotating_section");
        $section_key = (in_array($section_key, $keys)) ? $section_key : "registration_section";
        $orderBy = "";
        if($section_key == "registration_section") {
            $orderBy = "SUBSTRING(u.registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(u.registration_section, '[0-9]+')::INT, -1), SUBSTRING(u.registration_section, '[^0-9]*$'), u.user_id";
        }
        else {
            $orderBy = "u.{$section_key}, u.user_id";
        }

        $this->course_db->query("
SELECT u.*, sr.grading_registration_sections
FROM users u
LEFT JOIN (
	SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
	FROM grading_registration
	GROUP BY user_id
) as sr ON u.user_id=sr.user_id
ORDER BY {$orderBy}");
        $return = array();
        foreach ($this->course_db->rows() as $user) {
            if (isset($user['grading_registration_sections'])) {
                $user['grading_registration_sections'] = $this->course_db->fromDatabaseToPHPArray($user['grading_registration_sections']);
            }
            $return[] = new User($this->core, $user);
        }
        return $return;
    }

    public function getAllGraders() {
        $this->course_db->query("
SELECT u.*, sr.grading_registration_sections
FROM users u
LEFT JOIN (
	SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
	FROM grading_registration
	GROUP BY user_id
) as sr ON u.user_id=sr.user_id
WHERE u.user_group < 4
ORDER BY SUBSTRING(u.registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(u.registration_section, '[0-9]+')::INT, -1), SUBSTRING(u.registration_section, '[^0-9]*$'), u.user_id");
        $return = array();
        foreach ($this->course_db->rows() as $user) {
            if (isset($user['grading_registration_sections'])) {
                $user['grading_registration_sections'] = $this->course_db->fromDatabaseToPHPArray($user['grading_registration_sections']);
            }
            $return[] = new User($this->core, $user);
        }
        return $return;
    }

    public function getAllFaculty() {
        $this->submitty_db->query("
SELECT *
FROM users
WHERE user_access_level <= ?
ORDER BY user_id", [User::LEVEL_FACULTY]);
        $return = array();
        foreach ($this->submitty_db->rows() as $user) {
            $return[] = new User($this->core, $user);
        }
        return $return;
    }

    public function getAllUnarchivedSemester() {
        $this->submitty_db->query("
SELECT DISTINCT semester
FROM courses
WHERE status = 1");
        $return = array();
        foreach ($this->submitty_db->rows() as $row) {
            $return[] = $row['semester'];
        }
        return $return;
    }

    public function insertSubmittyUser(User $user) {
        $array = array($user->getId(), $user->getPassword(), $user->getNumericId(),
                       $user->getLegalFirstName(), $user->getPreferredFirstName(),
                       $user->getLegalLastName(), $user->getPreferredLastName(), $user->getEmail(),
                       $this->submitty_db->convertBoolean($user->isUserUpdated()),
                       $this->submitty_db->convertBoolean($user->isInstructorUpdated()));

        $this->submitty_db->query("INSERT INTO users (user_id, user_password, user_numeric_id, user_firstname, user_preferred_firstname, user_lastname, user_preferred_lastname, user_email, user_updated, instructor_updated)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $array);
    }

    public function insertCourseUser(User $user, $semester, $course) {
        $params = array($semester, $course, $user->getId(), $user->getGroup(), $user->getRegistrationSection(),
                        $this->submitty_db->convertBoolean($user->isManualRegistration()));
        $this->submitty_db->query("
INSERT INTO courses_users (semester, course, user_id, user_group, registration_section, manual_registration)
VALUES (?,?,?,?,?,?)", $params);

        $params = array($user->getRotatingSection(), $user->getId());
        $this->course_db->query("UPDATE users SET rotating_section=? WHERE user_id=?", $params);
        $this->updateGradingRegistration($user->getId(), $user->getGroup(), $user->getGradingRegistrationSections());
    }

    public function updateUser(User $user, $semester=null, $course=null) {
        $params = array($user->getNumericId(), $user->getLegalFirstName(), $user->getPreferredFirstName(),
                       $user->getLegalLastName(), $user->getPreferredLastName(), $user->getEmail(),
                       $this->submitty_db->convertBoolean($user->isUserUpdated()),
                       $this->submitty_db->convertBoolean($user->isInstructorUpdated()));
        $extra = "";
        if (!empty($user->getPassword())) {
            $params[] = $user->getPassword();
            $extra = ", user_password=?";
        }
        $params[] = $user->getId();

        $this->submitty_db->query("
UPDATE users
SET
  user_numeric_id=?, user_firstname=?, user_preferred_firstname=?,
  user_lastname=?, user_preferred_lastname=?,
  user_email=?, user_updated=?, instructor_updated=?{$extra}
WHERE user_id=?", $params);

        if (!empty($semester) && !empty($course)) {
            $params = array($user->getGroup(), $user->getRegistrationSection(),
                            $this->submitty_db->convertBoolean($user->isManualRegistration()), $semester, $course,
                            $user->getId());
            $this->submitty_db->query("
UPDATE courses_users SET user_group=?, registration_section=?, manual_registration=?
WHERE semester=? AND course=? AND user_id=?", $params);

            $params = array($user->getAnonId(), $user->getRotatingSection(), $user->getId());
            $this->course_db->query("UPDATE users SET anon_id=?, rotating_section=? WHERE user_id=?", $params);
            $this->updateGradingRegistration($user->getId(), $user->getGroup(), $user->getGradingRegistrationSections());
        }
    }


    // Moved from class LateDaysCalculation on port from TAGrading server.  May want to incorporate late day information into gradeable object rather than having a separate query
    public function getLateDayInformation($user_id) {
        $params = array(300);
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
        if($user_id !== null) {
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

    public function getAverageComponentScores($g_id, $section_key, $is_team) {
        $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        if($is_team) {
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
        }
        $return = array();
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
    WHERE g_id=? AND {$u_or_t}.{$section_key} IS NOT NULL
  )AS parts_of_comp
)AS comp
LEFT JOIN (
	SELECT COUNT(*) AS active_grade_inquiry_count, rr.gc_id
	FROM regrade_requests AS rr
	WHERE rr.g_id=? AND rr.status=-1
	GROUP BY rr.gc_id
) AS rr ON rr.gc_id=comp.gc_id
GROUP BY comp.gc_id, gc_title, gc_max_value, gc_is_peer, gc_order, rr.active_grade_inquiry_count
ORDER BY gc_order
        ", array($g_id, $g_id, $g_id, $g_id));
        foreach ($this->course_db->rows() as $row) {
            $return[] = new SimpleStat($this->core, $row);
        }
        return $return;
    }

    public function getAverageAutogradedScores($g_id, $section_key, $is_team) {
        $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        if($is_team){
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
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
      WHERE egd.g_version=egv.active_version AND egd.g_id=? AND {$u_or_t}.{$section_key} IS NOT NULL
   )g
) as individual;
          ", array($g_id));
        return ($this->course_db->getRowCount() > 0) ? new SimpleStat($this->core, $this->course_db->rows()[0]) : null;
    }

    public function getAverageForGradeable($g_id, $section_key, $is_team) {
        $u_or_t = "u";
        $users_or_teams = "users";
        $user_or_team_id = "user_id";
        if ($is_team) {
            $u_or_t = "t";
            $users_or_teams = "gradeable_teams";
            $user_or_team_id = "team_id";
        }
        $this->course_db->query("SELECT COUNT(*) as cnt FROM gradeable_component WHERE g_id=?", array($g_id));
        $count = $this->course_db->row()['cnt'];
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
          LEFT JOIN electronic_gradeable_data AS egd ON egv.g_id=egd.g_id AND egv.{$user_or_team_id}=egd.{$user_or_team_id} AND active_version=g_version
          )AS auto
        ON gd.g_id=auto.g_id AND gd_user_id=auto.{$user_or_team_id}
        INNER JOIN {$users_or_teams} AS {$u_or_t} ON {$u_or_t}.{$user_or_team_id} = auto.{$user_or_team_id}
        WHERE gc.g_id=? AND {$u_or_t}.{$section_key} IS NOT NULL
      )AS parts_of_comp
    )AS comp
    GROUP BY gd_id, autograding
  )g WHERE count=?
)AS individual
          ", array($g_id, $count));

        return ($this->course_db->getRowCount() > 0) ? new SimpleStat($this->core, $this->course_db->rows()[0]) : null;
    }

    public function getGradeablesRotatingGraderHistory($gradeable_id) {
        $params = [$gradeable_id];
        $this->course_db->query("
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
  ORDER BY user_group, user_id, g_grade_start_date",$params);
        $rows = $this->course_db->rows();
        $modified_rows = [];
        foreach($rows as $row) {
            $row['sections_rotating_id'] = json_decode($row['sections_rotating_id']);
            $modified_rows[] = $row;
        }
        return $modified_rows;
    }

    /**
     * Gets rotating sections of each grader for a gradeable
     * @param $gradeable_id
     * @return array An array (indexed by user id) of arrays of section numbers
     */
    public function getRotatingSectionsByGrader($gradeable_id) {
        $this->course_db->query("
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
    ",array($gradeable_id));

        // Split arrays into php arrays
        $rows = $this->course_db->rows();
        $sections_row = [];
        foreach($rows as $row) {
            $sections_row[$row['user_id']] = json_decode($row['sections']);
        }
        return $sections_row;
    }

    public function getUsersWithLateDays() {
        $this->course_db->query("
        SELECT u.user_id, user_firstname, user_preferred_firstname,
          user_lastname, user_preferred_lastname, allowed_late_days, since_timestamp::timestamp::date
        FROM users AS u
        FULL OUTER JOIN late_days AS l
          ON u.user_id=l.user_id
        WHERE allowed_late_days IS NOT NULL
        ORDER BY
          user_email ASC, since_timestamp DESC;");

        $return = array();
        foreach($this->course_db->rows() as $row){
            $return[] = new SimpleLateUser($this->core, $row);
        }
        return $return;
    }

    /**
     * "Upserts" a given user's late days allowed effective at a given time.
     *
     * Requires Postgresql 9.5+
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
     * @todo maybe process csv uploads as a batch transaction
     */
    public function updateLateDays($user_id, $timestamp, $days, $csv_option=null) {
        //Update query and values list.
        $query = "
            INSERT INTO late_days (user_id, since_timestamp, allowed_late_days)
            VALUES(?,?,?)
            ON CONFLICT (user_id, since_timestamp) DO UPDATE
            SET allowed_late_days=?
            WHERE late_days.user_id=? AND late_days.since_timestamp=?";
        $vals = array($user_id, $timestamp, $days, $days, $user_id, $timestamp);

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
     * Return Team object for team whith given Team ID
     * @param string $team_id
     * @return \app\models\Team|null
     */
    public function getTeamById($team_id) {
        $this->course_db->query("
            SELECT gt.team_id, gt.registration_section, gt.rotating_section, json_agg(u) AS users
            FROM gradeable_teams gt
              JOIN
              (SELECT t.team_id, t.state, u.*
               FROM teams t
                 JOIN users u ON t.user_id = u.user_id
              ) AS u ON gt.team_id = u.team_id
            WHERE gt.team_id = ?
            GROUP BY gt.team_id",
            array($team_id));
        if (count($this->course_db->rows()) === 0) {
            return null;
        }
        $details = $this->course_db->row();
        $details["users"] = json_decode($details["users"], true);
        return new Team($this->core, $details);
    }

    /**
     * Return Team object for team which the given user belongs to on the given gradeable
     * @param string $g_id
     * @param string $user_id
     * @return \app\models\Team|null
     */
    public function getTeamByGradeableAndUser($g_id, $user_id) {
        $this->course_db->query("
            SELECT gt.team_id, gt.registration_section, gt.rotating_section, json_agg(u) AS users
            FROM gradeable_teams gt
              JOIN
              (SELECT t.team_id, t.state, u.*
               FROM teams t
                 JOIN users u ON t.user_id = u.user_id
              ) AS u ON gt.team_id = u.team_id
            WHERE g_id=? AND gt.team_id IN (
              SELECT team_id
              FROM teams
              WHERE user_id=? AND state=1)
            GROUP BY gt.team_id",
            array($g_id, $user_id));
        if (count($this->course_db->rows()) === 0) {
            return null;
        }
        $details = $this->course_db->row();
        $details["users"] = json_decode($details["users"], true);
        return new Team($this->core, $details);
    }

    /**
     * Return an array of Team objects for all teams on given gradeable
     * @param string $g_id
     * @return \app\models\Team[]
     */
    public function getTeamsByGradeableId($g_id) {
        $this->course_db->query("
            SELECT gt.team_id, gt.registration_section, gt.rotating_section, json_agg(u) AS users
            FROM gradeable_teams gt
              JOIN
                (SELECT t.team_id, t.state, u.*
                 FROM teams t
                   JOIN users u ON t.user_id = u.user_id
                ) AS u ON gt.team_id = u.team_id
            WHERE g_id=?
            GROUP BY gt.team_id
            ORDER BY team_id",
            array($g_id));

        $teams = array();
        foreach($this->course_db->rows() as $row) {
            $row['users'] = json_decode($row['users'], true);
            $teams[] = new Team($this->core, $row);
        }

        return $teams;
    }

    /**
     * Returns array of User objects for users with given User IDs
     * @param string[] $user_ids
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

    /**
     * Return array of Team objects for teams with given Team IDs
     * @param string[] $team_ids
     * @return Team[] The team objects, indexed by team id
     */
    public function getTeamsById(array $team_ids) {
        if (count($team_ids) === 0) {
            return [];
        }

        // Generate placeholders for each team id
        $place_holders = implode(',', array_fill(0, count($team_ids), '?'));
        $query = "
            SELECT gt.team_id, gt.registration_section, gt.rotating_section, json_agg(u) AS users
            FROM gradeable_teams gt
              JOIN
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
     * Get an array of Teams for a Gradeable matching the given registration sections
     * @param string $g_id
     * @param array $sections
     * @param string $orderBy
     * @return Team[]
     */
    public function getTeamsByGradeableAndRegistrationSections($g_id, $sections, $orderBy="registration_section") {
        $return = array();
        if (count($sections) > 0) {
            $orderBy = str_replace("gt.registration_section","SUBSTRING(gt.registration_section, '^[^0-9]*'), COALESCE(SUBSTRING(gt.registration_section, '[0-9]+')::INT, -1), SUBSTRING(gt.registration_section, '[^0-9]*$')",$orderBy);
            $placeholders = implode(",", array_fill(0, count($sections), "?"));
            $params = [$g_id];
            $params = array_merge($params, $sections);

            $this->course_db->query("
                SELECT gt.team_id, gt.registration_section, gt.rotating_section, json_agg(u) AS users
                FROM gradeable_teams gt
                  JOIN
                    (SELECT t.team_id, t.state, u.*
                     FROM teams t
                       JOIN users u ON t.user_id = u.user_id
                    ) AS u ON gt.team_id = u.team_id
                WHERE gt.g_id = ?
                  AND gt.registration_section IN ($placeholders)
                GROUP BY gt.team_id
                ORDER BY {$orderBy}
            ", $params);
            foreach ($this->course_db->rows() as $row) {
                $row["users"] = json_decode($row["users"], true);
                $return[] = new Team($this->core, $row);
            }
        }
        return $return;
    }

    /**
     * Get an array of Teams for a Gradeable matching the given rotating sections
     * @param string $g_id
     * @param array $sections
     * @param string $orderBy
     * @return Team[]
     */
    public function getTeamsByGradeableAndRotatingSections($g_id, $sections, $orderBy="rotating_section") {
        $return = array();
        if (count($sections) > 0) {
            $placeholders = implode(",", array_fill(0, count($sections), "?"));
            $params = [$g_id];
            $params = array_merge($params, $sections);

            $this->course_db->query("
                SELECT gt.team_id, gt.registration_section, gt.rotating_section, json_agg(u) AS users
                FROM gradeable_teams gt
                  JOIN
                    (SELECT t.team_id, t.state, u.*
                     FROM teams t
                       JOIN users u ON t.user_id = u.user_id
                    ) AS u ON gt.team_id = u.team_id
                WHERE gt.g_id = ?
                  AND gt.rotating_section IN ($placeholders)
                GROUP BY gt.team_id
                ORDER BY {$orderBy}
            ", $params);
            foreach ($this->course_db->rows() as $row) {
                $row["users"] = json_decode($row["users"], true);
                $return[] = new Team($this->core, $row);
            }
        }
        return $return;
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
        'team_id' => []
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
        'user_id' => []
    ];

    /**
     * Generates the ORDER BY clause with the provided sorting keys.
     *
     * For every element in $sort_keys, checks if the first word is in $key_map,
     * and if so, replaces it with the list of clauses in $key_map, and then joins that
     * list together using the second word (if it exists, assuming it is an order direction)
     * or blank otherwise. If the first word is not, return the clause as is. Finally,
     * join the resulting set of clauses together as appropriate for valid ORDER BY.
     *
     * @param string[]|null $sort_keys
     * @param array $key_map A map from sort keys to arrays of expressions to sort by instead
     *          (see self::graded_gradeable_key_map for example)
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
            return 'ORDER BY ' . implode(',', array_filter(
                array_map(function ($key_ext) use ($key_map) {
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
                }, $sort_keys),
                function ($a) {
                    return $a !== '';
                }));
        }
        return '';
    }

    /**
     * Gets all GradedGradeable's associated with each Gradeable.  If
     *  both $users and $teams are null, then everyone will be retrieved.
     *  Note: The users' teams will be included in the search
     * @param \app\models\gradeable\Gradeable[] $gradeables The gradeable(s) to retrieve data for
     * @param string[]|string|null $users The id(s) of the user(s) to get data for
     * @param string[]|string|null $teams The id(s) of the team(s) to get data for
     * @param string[]|string|null $sort_keys An ordered list of keys to sort by (i.e. `user_id` or `g_id DESC`)
     * @return \Iterator Iterator to access each GradeableData
     * @throws \InvalidArgumentException If any GradedGradeable or GradedComponent fails to construct
     */
    public function getGradedGradeables(array $gradeables, $users = null, $teams = null, $sort_keys = null) {
        $non_team_gradeables = [];
        $team_gradeables = [];
        foreach ($gradeables as $gradeable) {
            if ($gradeable->isTeamAssignment()) {
                $team_gradeables[] = $gradeable;
            } else {
                $non_team_gradeables[] = $gradeable;
            }
        }

        return new CascadingIterator(
            $this->getGradedGradeablesUserOrTeam($non_team_gradeables, $users, $teams, $sort_keys, false),
            $this->getGradedGradeablesUserOrTeam($team_gradeables, $users, $teams, $sort_keys, true)
        );
    }

    /**
     * Gets all GradedGradeable's associated with each Gradeable.  If
     *  Note: The users' teams will be included in the search
     * @param \app\models\gradeable\Gradeable[] The gradeable(s) to retrieve data for
     * @param string[]|string|null $users The id(s) of the user(s) to get data for
     * @param string[]|string|null $teams The id(s) of the team(s) to get data for
     * @param string[]|string|null $sort_keys An ordered list of keys to sort by (i.e. `user_id` or `g_id DESC`)
     * @param bool $team True to get only team information, false to get only user information
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
        if ($users === [] && $teams === null ||
            $users === null && $teams === [] ||
            $users === [] && $teams === []) {
            return new \EmptyIterator();
        }

        // Make sure that our users/teams are arrays
        if ($users !== null) {
            if (!is_array($users)) {
                $users = [$users];
            }
        } else {
            $users = [];
        }
        if ($teams !== null) {
            if (!is_array($teams)) {
                $teams = [$teams];
            }
        } else {
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
            } else {
                // Select the users' teams as well
                $selector_union_list[] = "team.team_id IN (SELECT team_id FROM teams WHERE state=1 AND user_id IN ($user_placeholders))";
            }
        }

        $submitter_inject = 'ERROR ERROR';
        $submitter_data_inject = 'ERROR ERROR';
        if ($team) {
            $submitter_data_inject =
              'ldet.array_late_day_exceptions,
               ldet.array_late_day_user_ids,
               /* Aggregate Team User Data */
               team.team_id,
               team.array_team_users,
               team.registration_section,
               team.rotating_section';

            $submitter_inject = '
              JOIN (
                SELECT gt.team_id,
                  gt.registration_section,
                  gt.rotating_section,
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
                  json_agg(e.user_id) AS array_late_day_user_ids,
                  t.team_id,
                  g_id
                FROM late_day_exceptions e
                LEFT JOIN teams t ON e.user_id=t.user_id AND t.state=1
                GROUP BY team_id, g_id
              ) AS ldet ON g.g_id=ldet.g_id AND ldet.team_id=team.team_id';
        } else {
            $submitter_data_inject = '
              u.user_id,
              u.anon_id,
              u.user_firstname,
              u.user_preferred_firstname,
              u.user_lastname,
              u.user_preferred_lastname,
              u.user_email,
              u.user_group,
              u.manual_registration,
              u.last_updated,
              u.grading_registration_sections,
              u.registration_section, u.rotating_section,
              ldeu.late_day_exceptions';
            $submitter_inject = '
            JOIN (
                SELECT u.*, sr.grading_registration_sections
                FROM users u
                LEFT JOIN (
                    SELECT
                        json_agg(sections_registration_id) AS grading_registration_sections,
                        user_id
                    FROM grading_registration
                    GROUP BY user_id
                ) AS sr ON u.user_id=sr.user_id
            ) AS u ON eg IS NULL OR NOT eg.team_assignment

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
              gd.gd_overall_comment AS overall_comment,
              gd.gd_user_viewed_date AS user_viewed_date,

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
              gcd.array_grader_user_firstname,
              gcd.array_grader_user_preferred_firstname,
              gcd.array_grader_user_lastname,
              gcd.array_grader_user_email,
              gcd.array_grader_user_group,
              gcd.array_grader_manual_registration,
              gcd.array_grader_last_updated,
              gcd.array_grader_registration_section,
              gcd.array_grader_rotating_section,
              gcd.array_grader_grading_registration_sections,

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

              /* Join aggregate gradeable component data */
              LEFT JOIN (
                SELECT
                  json_agg(in_gcd.gc_id) AS array_comp_id,
                  json_agg(gcd_score) AS array_score,
                  json_agg(gcd_component_comment) AS array_comment,
                  json_agg(gcd_grader_id) AS array_grader_id,
                  json_agg(gcd_graded_version) AS array_graded_version,
                  json_agg(gcd_grade_time) AS array_grade_time,
                  json_agg(string_mark_id) AS array_mark_id,
                  json_agg(gcd_verifier_id) AS array_verifier_id,
                  json_agg(gcd_verify_time) AS array_verify_time,

                  json_agg(ug.user_id) AS array_grader_user_id,
                  json_agg(ug.anon_id) AS array_grader_anon_id,
                  json_agg(ug.user_firstname) AS array_grader_user_firstname,
                  json_agg(ug.user_preferred_firstname) AS array_grader_user_preferred_firstname,
                  json_agg(ug.user_lastname) AS array_grader_user_lastname,
                  json_agg(ug.user_email) AS array_grader_user_email,
                  json_agg(ug.user_group) AS array_grader_user_group,
                  json_agg(ug.manual_registration) AS array_grader_manual_registration,
                  json_agg(ug.last_updated) AS array_grader_last_updated,
                  json_agg(ug.registration_section) AS array_grader_registration_section,
                  json_agg(ug.rotating_section) AS array_grader_rotating_section,
                  json_agg(ug.grading_registration_sections) AS array_grader_grading_registration_sections,
                  in_gcd.gd_id
                FROM gradeable_component_data in_gcd
                  LEFT JOIN (
                    SELECT
                      json_agg(gcm_id) AS string_mark_id,
                      gc_id,
                      gd_id
                    FROM gradeable_component_mark_data
                    GROUP BY gc_id, gd_id
                  ) AS gcmd ON gcmd.gc_id=in_gcd.gc_id AND gcmd.gd_id=in_gcd.gd_id

                  /* Join grader data; TODO: do we want/need 'sr' information? */
                  LEFT JOIN (
                    SELECT u.*, grading_registration_sections
                    FROM users u
                    LEFT JOIN (
                      SELECT
                        json_agg(sections_registration_id) AS grading_registration_sections,
                        user_id
                      FROM grading_registration
                      GROUP BY user_id
                    ) AS sr ON u.user_id=sr.user_id
                  ) AS ug ON ug.user_id=in_gcd.gcd_grader_id
                GROUP BY in_gcd.gd_id
              ) AS gcd ON gcd.gd_id=gd.gd_id

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
  				FROM regrade_requests AS rr
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
                if (isset($row['array_late_day_user_ids'])) {
                    $late_day_exceptions = array_combine(
                        json_decode($row['array_late_day_user_ids']),
                        json_decode($row['array_late_day_exceptions'])
                    );
                }
                foreach ($submitter->getMembers() as $user_id) {
                    if (!isset($late_day_exceptions[$user_id])) {
                        $late_day_exceptions[$user_id] = 0;
                    }
                }
            } else {
                if (isset($row['grading_registration_sections'])) {
                    $row['grading_registration_sections'] = json_decode($row['grading_registration_sections']);
                }
                $submitter = new User($this->core, $row);

                // Get the late day exception for the user
                $late_day_exceptions = [
                    $submitter->getId() => $row['late_day_exceptions'] ?? 0
                ];
            }

            // Create the graded gradeable instances
            $graded_gradeable = new GradedGradeable($this->core, $gradeable, new Submitter($this->core, $submitter), [
                'late_day_exceptions' => $late_day_exceptions
            ]);
            $ta_graded_gradeable = null;
            $auto_graded_gradeable = null;

            // This will be false if there is no manual grade yet
            if (isset($row['id'])) {
                $ta_graded_gradeable = new TaGradedGradeable($this->core, $graded_gradeable, $row);
                $graded_gradeable->setTaGradedGradeable($ta_graded_gradeable);
            }

            // Always construct an instance even if there is no data
            $auto_graded_gradeable = new AutoGradedGradeable($this->core, $graded_gradeable, $row);
            $graded_gradeable->setAutoGradedGradeable($auto_graded_gradeable);

            if (isset($row['array_grade_inquiries'])) {
                $grade_inquiries = json_decode($row['array_grade_inquiries'],true);
                $grade_inquiries_arr = array();
                foreach ($grade_inquiries as $grade_inquiry) {
                    $grade_inquiries_arr[] = new RegradeRequest($this->core, $grade_inquiry);
                }

                $graded_gradeable->setRegradeRequests($grade_inquiries_arr);
            }

            $graded_components_by_id = [];
            /** @var AutoGradedVersion[] $graded_versions */
            $graded_versions = [];

            // Break down the graded component / version / grader data into an array of arrays
            //  instead of arrays of sql array-strings
            $user_properties = [
                'user_id',
                'anon_id',
                'user_firstname',
                'user_preferred_firstname',
                'user_lastname',
                'user_email',
                'user_group',
                'manual_registration',
                'last_updated',
                'registration_section',
                'rotating_section',
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
            foreach (array_merge($version_array_properties, $comp_array_properties,
                array_map(function ($elem) {
                    return 'grader_' . $elem;
                }, $user_properties)) as $property) {
                $db_row_split[$property] = json_decode($row['array_' . $property]);
            }

            if (isset($db_row_split['comp_id'])) {
                // Create all of the GradedComponents
                if (isset($db_row_split['comp_id'])) {
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

                        // Create the component
                        $graded_component = new GradedComponent($this->core,
                            $ta_graded_gradeable,
                            $gradeable->getComponent($db_row_split['comp_id'][$i]),
                            $grader,
                            $comp_array);
                        $graded_component->setMarkIdsFromDb($db_row_split['mark_id'][$i] ?? []);
                        $graded_components_by_id[$graded_component->getComponentId()][] = $graded_component;
                    }
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
     * Gets all Gradeable instances for the given ids (or all if id is null)
     * @param string[]|null $ids ids of the gradeables to retrieve
     * @param string[]|string|null $sort_keys An ordered list of keys to sort by (i.e. `id` or `grade_start_date DESC`)
     * @return \Iterator Iterates across array of Gradeables retrieved
     * @throws \InvalidArgumentException If any Gradeable or Component fails to construct
     * @throws ValidationException If any Gradeable or Component fails to construct
     */
    public function getGradeableConfigs($ids, $sort_keys = ['id']) {
        if($ids === []) {
            return new \EmptyIterator();
        }
        if($ids === null) {
            $ids = [];
        }

        // Generate the selector statement
        $selector = '';
        if(count($ids) > 0) {
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
              g_grade_locked_date AS grade_locked_date,
              g_min_grading_group AS min_grading_group,
              g_syllabus_bucket AS syllabus_bucket,
              eg.*,
              gc.*,
              (SELECT COUNT(*) AS cnt FROM regrade_requests WHERE g_id=g.g_id AND status = -1) AS active_regrade_request_count
            FROM gradeable g
              LEFT JOIN (
                SELECT
                  g_id AS eg_g_id,
                  eg_config_path AS autograding_config_path,
                  eg_is_repository AS vcs,
                  eg_subdirectory AS vcs_subdirectory,
                  eg_vcs_host_type AS vcs_host_type,
                  eg_team_assignment AS team_assignment,
                  eg_max_team_size AS team_size_max,
                  eg_team_lock_date AS team_lock_date,
                  eg_regrade_request_date AS regrade_request_date,
                  eg_regrade_allowed AS regrade_allowed,
                  eg_grade_inquiry_per_component_allowed AS grade_inquiry_per_component_allowed,
                  eg_thread_ids AS discussion_thread_ids,
                  eg_has_discussion AS discussion_based,
                  eg_use_ta_grading AS ta_grading,
                  eg_scanned_exam AS scanned_exam,
                  eg_student_view AS student_view,
                  eg_student_view_after_grades as student_view_after_grades,
                  eg_student_submit AS student_submit,
                  eg_peer_grading AS peer_grading,
                  eg_peer_grade_set AS peer_grade_set,
                  eg_submission_open_date AS submission_open_date,
                  eg_submission_due_date AS submission_due_date,
                  eg_has_due_date AS has_due_date,
                  eg_late_days AS late_days,
                  eg_allow_late_submission AS late_submission_allowed,
                  eg_precision AS precision
                FROM electronic_gradeable
              ) AS eg ON g.g_id=eg.eg_g_id
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
                  json_agg(gc_is_peer) AS array_peer,
                  json_agg(gc_order) AS array_order,
                  json_agg(gc_page) AS array_page,
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
                'peer',
                'order',
                'page',
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
            $component_mark_properties = array_map(function ($value) {
                return 'mark_' . $value;
            }, $mark_properties);

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

        return $this->course_db->queryIterator($query,
            $ids,
            $gradeable_constructor);
    }

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
              (SELECT user_id AS id, active_version as m
              FROM electronic_gradeable_version
              WHERE g_id = ? AND user_id IS NOT NULL)

              UNION

              (SELECT team_id AS id, active_version as m
              FROM electronic_gradeable_version
              WHERE g_id = ? AND team_id IS NOT NULL)
            ) AS versions
            ON versions.id = ids.id
        ";

        $params = array_merge($submitter_ids, [$gradeable->getId(), $gradeable->getId()]);

        $this->course_db->query($query, $params);
        $versions = [];
        foreach ($this->course_db->rows() as $row) {
            $versions[$row["id"]] = $row["max"];
        }
        return $versions;
    }

}
