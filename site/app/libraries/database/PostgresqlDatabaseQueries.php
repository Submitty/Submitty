<?php

namespace app\libraries\database;

use app\libraries\Utils;
use \app\libraries\GradeableType;
use app\models\AdminGradeable;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\GradeableComponentMark;
use app\models\GradeableVersion;
use app\models\User;
use app\models\SimpleLateUser;
use app\models\Team;
use app\models\SimpleStat;

class PostgresqlDatabaseQueries extends DatabaseQueries{

    public function getUserById($user_id) {
        $this->course_db->query("
SELECT u.*, sr.grading_registration_sections
FROM users u
LEFT JOIN (
	SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
	FROM grading_registration
	GROUP BY user_id
) as sr ON u.user_id=sr.user_id
WHERE u.user_id=?", array($user_id));
        if (count($this->course_db->rows()) > 0) {
            $user = $this->course_db->row();
            if (isset($user['grading_registration_sections'])) {
                $user['grading_registration_sections'] = $this->course_db->fromDatabaseToPHPArray($user['grading_registration_sections']);
            }
            return new User($this->core, $user);
        }
        else {
            return null;
        }
    }

    public function getGradingSectionsByUserId($user_id) {
        $this->course_db->query("
SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
FROM grading_registration
GROUP BY user_id
WHERE user_id=?", array($user_id));
        return $this->course_db->row();
    }

    public function getAllUsers($section_key="registration_section") {
        $keys = array("registration_section", "rotating_section");
        $section_key = (in_array($section_key, $keys)) ? $section_key : "registration_section";
        $this->course_db->query("
SELECT u.*, sr.grading_registration_sections
FROM users u
LEFT JOIN (
	SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
	FROM grading_registration
	GROUP BY user_id
) as sr ON u.user_id=sr.user_id
ORDER BY u.{$section_key}, u.user_id");
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
ORDER BY u.registration_section, u.user_id");
        $return = array();
        foreach ($this->course_db->rows() as $user) {
            if (isset($user['grading_registration_sections'])) {
                $user['grading_registration_sections'] = $this->course_db->fromDatabaseToPHPArray($user['grading_registration_sections']);
            }
            $return[] = new User($this->core, $user);
        }
        return $return;
    }


    public function insertSubmittyUser(User $user) {
        $array = array($user->getId(), $user->getPassword(), $user->getFirstName(), $user->getPreferredFirstName(),
                       $user->getLastName(), $user->getEmail(),
                       $this->submitty_db->convertBoolean($user->isUserUpdated()),
                       $this->submitty_db->convertBoolean($user->isInstructorUpdated()));

        $this->submitty_db->query("INSERT INTO users (user_id, user_password, user_firstname, user_preferred_firstname, user_lastname, user_email, user_updated, instructor_updated)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)", $array);
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
        $params = array($user->getFirstName(), $user->getPreferredFirstName(),
                       $user->getLastName(), $user->getEmail(),
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
  user_firstname=?, user_preferred_firstname=?, user_lastname=?,
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

    public function getGradeablesIterator($g_ids = null, $user_ids = null, $section_key="registration_section", $sort_key="u.user_id", $g_type = null, $extra_order_by = []) {
        $return = array();
        if (!is_array($extra_order_by)) {
            $extra_order_by = [];
        }
        $g_ids_query = "";
        $users_query = "";
        $g_type_query = "";
        $params = array();
        if ($g_ids !== null) {
            if (!is_array($g_ids)) {
                $g_ids = array($g_ids);
            }
            if (count($g_ids) > 0) {
                $g_ids_query = implode(",", array_fill(0, count($g_ids), "?"));
                $params = $g_ids;
            }
            else {
                return $return;
            }
        }
        if ($user_ids !== null && $user_ids !== true) {
            if (!is_array($user_ids)) {
                $user_ids = array($user_ids);
            }
            if (count($user_ids) > 0) {
                $users_query = implode(",", array_fill(0, count($user_ids), "?"));
                $params = array_merge($params, $user_ids);
            }
            else {
                return $return;
            }
        }
        // added toggling of gradeable type to only grab Homeworks for HWReport generation
        if ($g_type !== null) {
            if (!is_array($g_type)) {
                $g_type = array($g_type);
            }
            if (count($g_type) > 0) {
                $g_type_query = implode(",", array_fill(0, count($g_type), "?"));
                $params = array_merge($params, $g_type);
            }
            else {
                return $return;
            }
        }
        $section_keys = array("registration_section", "rotating_section");
        $section_key = (in_array($section_key, $section_keys)) ? $section_key : "registration_section";
        $sort_keys = array("u.user_firstname", "u.user_lastname", "u.user_id");
        $sort_key = (in_array($sort_key, $sort_keys)) ? $sort_key : "u.user_id";
        $sort = array();
        switch ($sort_key) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'u.user_firstname':
                $sort[] = 'u.user_firstname';
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'u.user_lastname':
                $sort[] = 'u.user_lastname';
            case 'u.user_id':
                $sort[] = 'u.user_id';
                break;
            default:
                $sort[] = 'u.user_firstname';
        }
        $sort_key = implode(', ', $sort);
        $query = "
SELECT";
        if ($user_ids !== null) {
            $query .= "
  u.*,";
        }
        $query .= "
  g.*,
  eg.eg_config_path,
  eg.eg_is_repository,
  eg.eg_subdirectory,
  eg.eg_team_assignment,
  eg.eg_max_team_size,
  eg.eg_team_lock_date,
  eg.eg_use_ta_grading,
  eg.eg_student_view,
  eg.eg_student_submit,
  eg.eg_student_download,
  eg.eg_student_any_version,
  eg.eg_peer_grading,
  eg.eg_peer_grade_set,
  eg.eg_submission_open_date,
  eg.eg_submission_due_date,
  eg.eg_late_days,
  eg.eg_precision,
  gc.array_gc_id,
  gc.array_gc_title,
  gc.array_gc_ta_comment,
  gc.array_gc_student_comment,
  gc.array_gc_lower_clamp,
  gc.array_gc_default,
  gc.array_gc_max_value,
  gc.array_gc_upper_clamp,
  gc.array_gc_is_text,
  gc.array_gc_is_peer,
  gc.array_gc_order,
  gc.array_gc_page,
  gc.array_array_gcm_id,
  gc.array_array_gc_id,
  gc.array_array_gcm_points,
  gc.array_array_gcm_note,
  gc.array_array_gcm_publish,
  gc.array_array_gcm_order";
        if ($user_ids !== null) {
            $query .= ",
  gd.gd_id,
  gd.gd_overall_comment,
  gd.gd_user_viewed_date,
  gd.array_array_gcm_mark,
  gd.array_gcd_gc_id,
  gd.array_gcd_score,
  gd.array_gcd_component_comment,
  gd.array_gcd_grader_id,
  gd.array_gcd_graded_version,
  gd.array_gcd_grade_time,
  gd.array_gcd_user_id,
  gd.array_gcd_anon_id,
  gd.array_gcd_user_firstname,
  gd.array_gcd_user_preferred_firstname,
  gd.array_gcd_user_lastname,
  gd.array_gcd_user_email,
  gd.array_gcd_user_group,
  CASE WHEN egd.active_version IS NULL THEN
    0 ELSE
    egd.active_version
  END AS active_version,
  egd.team_id,
  egd.g_version,
  egd.autograding_non_hidden_non_extra_credit,
  egd.autograding_non_hidden_extra_credit,
  egd.autograding_hidden_non_extra_credit,
  egd.autograding_hidden_extra_credit,
  egd.submission_time,
  egv.highest_version,
  COALESCE(lde.late_day_exceptions, 0) AS late_day_exceptions,
  GREATEST(0, CEIL((EXTRACT(EPOCH FROM(COALESCE(egd.submission_time, eg.eg_submission_due_date) - eg.eg_submission_due_date)) - (300*60))/86400)::integer) AS days_late,
  get_allowed_late_days(u.user_id, eg.eg_submission_due_date) AS student_allowed_late_days
FROM users AS u
NATURAL JOIN gradeable AS g";
        }
        else {
            $query .= "
FROM gradeable AS g";
        }
        $query .= "
LEFT JOIN electronic_gradeable AS eg ON eg.g_id=g.g_id
LEFT JOIN (
  SELECT
    g_id,
    array_agg(gc_is_peer) as array_gc_is_peer,
    array_agg(gc_id) as array_gc_id,
    array_agg(gc_title) AS array_gc_title,
    array_agg(gc_ta_comment) AS array_gc_ta_comment,
    array_agg(gc_student_comment) AS array_gc_student_comment,
    array_agg(gc_lower_clamp) AS array_gc_lower_clamp,
    array_agg(gc_default) AS array_gc_default,
    array_agg(gc_max_value) AS array_gc_max_value,
    array_agg(gc_upper_clamp) AS array_gc_upper_clamp,
    array_agg(gc_is_text) AS array_gc_is_text,
    array_agg(gc_order) AS array_gc_order,
    array_agg(gc_page) AS array_gc_page,
    array_agg(array_gcm_id) AS array_array_gcm_id,
    array_agg(array_gc_id) AS array_array_gc_id,
    array_agg(array_gcm_points) AS array_array_gcm_points,
    array_agg(array_gcm_note) AS array_array_gcm_note,
    array_agg(array_gcm_publish) AS array_array_gcm_publish,
    array_agg(array_gcm_order) AS array_array_gcm_order
  FROM
  (SELECT gc.*, gcm.array_gcm_id, gcm.array_gc_id, gcm.array_gcm_points, array_gcm_note, array_gcm_publish, array_gcm_order
  FROM gradeable_component AS gc
  LEFT JOIN(
    SELECT
      gc_id,
      array_to_string(array_agg(gcm_id), ',') as array_gcm_id,
      array_to_string(array_agg(gc_id), ',') as array_gc_id,
      array_to_string(array_agg(gcm_points), ',') as array_gcm_points,
      array_to_string(array_agg(gcm_note), ',') as array_gcm_note,
      array_to_string(array_agg(gcm_publish), ',') as array_gcm_publish,
      array_to_string(array_agg(gcm_order), ',') as array_gcm_order
    FROM gradeable_component_mark
    GROUP BY gc_id
  ) AS gcm
  ON gc.gc_id=gcm.gc_id) as gradeable_component
  GROUP BY g_id
) AS gc ON gc.g_id=g.g_id";
        if ($user_ids !== null) {
            $query .= "
LEFT JOIN (
  SELECT
    in_gd.*,
    in_gcd.array_gcd_gc_id,
    in_gcd.array_gcd_score,
    in_gcd.array_gcd_component_comment,
    in_gcd.array_gcd_grader_id,
    in_gcd.array_gcd_graded_version,
    in_gcd.array_gcd_grade_time,
    in_gcd.array_array_gcm_mark,
    in_gcd.array_gcd_user_id,
    in_gcd.array_gcd_anon_id,
    in_gcd.array_gcd_user_firstname,
    in_gcd.array_gcd_user_preferred_firstname,
    in_gcd.array_gcd_user_lastname,
    in_gcd.array_gcd_user_email,
    in_gcd.array_gcd_user_group
  FROM gradeable_data as in_gd
  LEFT JOIN (
    SELECT
      gcd.gd_id,
      array_agg(gc_id) AS array_gcd_gc_id,
      array_agg(gcd_score) AS array_gcd_score,
      array_agg(gcd_component_comment) AS array_gcd_component_comment,
      array_agg(gcd_grader_id) AS array_gcd_grader_id,
      array_agg(gcd_graded_version) AS array_gcd_graded_version,
      array_agg(gcd_grade_time) AS array_gcd_grade_time,
      array_agg(array_gcm_mark) AS array_array_gcm_mark,
      array_agg(u.user_id) AS array_gcd_user_id,
      array_agg(u.anon_id) AS array_gcd_anon_id,
      array_agg(u.user_firstname) AS array_gcd_user_firstname,
      array_agg(u.user_preferred_firstname) AS array_gcd_user_preferred_firstname,
      array_agg(u.user_lastname) AS array_gcd_user_lastname,
      array_agg(u.user_email) AS array_gcd_user_email,
      array_agg(u.user_group) AS array_gcd_user_group
    FROM(
        SELECT gcd.* , gcmd.array_gcm_mark
        FROM gradeable_component_data AS gcd
        LEFT JOIN (
          SELECT gc_id, gd_id, gcd_grader_id, array_to_string(array_agg(gcm_id), ',') as array_gcm_mark
          FROM gradeable_component_mark_data AS gcmd
          GROUP BY gc_id, gd_id, gd_id, gcd_grader_id
        ) as gcmd
    ON gcd.gc_id=gcmd.gc_id AND gcd.gd_id=gcmd.gd_id AND gcmd.gcd_grader_id=gcd.gcd_grader_id
    ) AS gcd
    INNER JOIN users AS u ON gcd.gcd_grader_id = u.user_id
    GROUP BY gcd.gd_id
  ) AS in_gcd ON in_gd.gd_id = in_gcd.gd_id
) AS gd ON g.g_id = gd.g_id AND (gd.gd_user_id = u.user_id OR u.user_id IN (
    SELECT
      t.user_id
    FROM gradeable_teams AS gt, teams AS t
    WHERE g.g_id = gt.g_id AND gt.team_id = t.team_id AND t.team_id = gd.gd_team_id AND t.state = 1)
)
LEFT JOIN (
  SELECT
    egd.*,
    egv.active_version
  FROM electronic_gradeable_version AS egv, electronic_gradeable_data AS egd
  WHERE egv.active_version = egd.g_version AND egv.g_id = egd.g_id AND (egv.user_id = egd.user_id OR egv.team_id = egd.team_id)
) AS egd ON g.g_id = egd.g_id AND (u.user_id = egd.user_id OR u.user_id IN (
    SELECT
      t.user_id
    FROM gradeable_teams AS gt, teams AS t
    WHERE g.g_id = gt.g_id AND gt.team_id = t.team_id AND t.team_id = egd.team_id AND t.state = 1)
)
LEFT JOIN (
  SELECT
    g_id,
    user_id,
    team_id,
    count(*) as highest_version
  FROM electronic_gradeable_data
  GROUP BY g_id, user_id, team_id
) AS egv ON g.g_id = egv.g_id AND (u.user_id = egv.user_id OR u.user_id IN (
    SELECT
      t.user_id
    FROM gradeable_teams AS gt, teams AS t
    WHERE g.g_id = gt.g_id AND gt.team_id = t.team_id AND t.team_id = egv.team_id AND t.state = 1)
)
LEFT JOIN late_day_exceptions AS lde ON g.g_id = lde.g_id AND u.user_id = lde.user_id";
        }

        $where = array();
        if ($g_ids !== null) {
            $where[] = "g.g_id IN ({$g_ids_query})";
        }
        if ($user_ids !== null && $user_ids !== true) {
            $where[] = "u.user_id IN ({$users_query})";
        }
        if ($g_type !== null) {
            $where[] = "g.g_gradeable_type IN ({$g_type_query})";
        }
        if (count($where) > 0) {
            $query .= "
WHERE ".implode(" AND ", $where);
        }
        $order_by = [];
        if ($user_ids !== null) {
            $order_by[] = "u.{$section_key}";
            $order_by[] = $sort_key;
        }
        $order_by = array_merge($order_by, $extra_order_by);
        if (count($order_by) > 0) {
            $query .= "
ORDER BY ".implode(", ", $order_by);
        }

        return $this->course_db->queryIterator($query, $params, function ($row) {
            $user = (isset($row['user_id']) && $row['user_id'] !== null) ? new User($this->core, $row) : null;
            if (isset($row['array_gc_id'])) {
                $fields = array('gc_id', 'gc_title', 'gc_ta_comment', 'gc_student_comment', 'gc_lower_clamp',
                                'gc_default', 'gc_max_value', 'gc_upper_clamp', 'gc_is_text', 'gc_is_peer',
                                'gc_order', 'gc_page', 'array_gcm_mark', 'array_gcm_id', 'array_gc_id',
                                'array_gcm_points', 'array_gcm_note', 'array_gcm_publish', 'array_gcm_order', 'gcd_gc_id', 'gcd_score',
                                'gcd_component_comment', 'gcd_grader_id', 'gcd_graded_version', 'gcd_grade_time',
                                'gcd_user_id', 'gcd_user_firstname', 'gcd_user_preferred_firstname',
                                'gcd_user_lastname', 'gcd_user_email', 'gcd_user_group');
                $bools = array('gc_is_text', 'gc_is_peer');
                foreach ($fields as $key) {
                    if (isset($row['array_' . $key])) {
                        $row['array_' . $key] = $this->core->getCourseDB()->fromDatabaseToPHPArray($row['array_' . $key], in_array($key, $bools));
                    }
                }
            }
            return new Gradeable($this->core, $row, $user);
        });
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
                $query .= " WHERE submissions.user_id IN (".implode(", ", array_fill(0, count($user_id), '?')).")";
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

    public function getAverageComponentScores($g_id, $section_key) {
        $return = array();
        $this->course_db->query("
SELECT gc_id, gc_title, gc_max_value, gc_is_peer, gc_order, round(AVG(comp_score),2) AS avg_comp_score, round(stddev_pop(comp_score),2) AS std_dev, COUNT(*) FROM(
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
      INNER JOIN users AS u ON u.user_id = egv.user_id, electronic_gradeable_version AS egd
      WHERE egv.g_id=? AND u.{$section_key} IS NOT NULL AND egv.g_version=egd.active_version AND active_version>0 AND egd.user_id=egv.user_id
   )g
) as individual;
          ", array($g_id));
        return ($this->course_db->getRowCount() > 0) ? new SimpleStat($this->core, $this->course_db->rows()[0]) : null;
    }

    public function getAverageForGradeable($g_id, $section_key) {
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
          SELECT egd.g_id, egd.user_id, (autograding_non_hidden_non_extra_credit + autograding_non_hidden_extra_credit + autograding_hidden_non_extra_credit + autograding_hidden_extra_credit) AS autograding
          FROM electronic_gradeable_version AS egv
          LEFT JOIN electronic_gradeable_data AS egd ON egv.g_id=egd.g_id AND egv.user_id=egd.user_id AND active_version=g_version
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

        return ($this->course_db->getRowCount() > 0) ? new SimpleStat($this->core, $this->course_db->rows()[0]) : null;
    }

    public function getGradeablesPastAndSection() {
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
      WHERE g_grade_by_registration = 'f'
    ) AS g
  ) as gu
  LEFT JOIN (
    SELECT
      g_id, user_id, array_agg(sections_rotating_id) as sections_rotating_id
    FROM
      grading_rotating
    GROUP BY g_id, user_id
  ) AS gr ON gu.user_id=gr.user_id AND gu.g_id=gr.g_id
  ORDER BY user_group, user_id, g_grade_start_date");
        return $this->course_db->rows();
    }

    public function getGradersForAllRotatingSections($gradeable_id) {
        $this->course_db->query("
    SELECT
        u.user_id, array_agg(sections_rotating_id ORDER BY sections_rotating_id ASC) AS sections
    FROM
        users AS u INNER JOIN grading_rotating AS gr ON u.user_id = gr.user_id
    WHERE
        g_id=?
    AND
        u.user_group BETWEEN 1 AND 3
    GROUP BY
        u.user_id
    ",array($gradeable_id));
        return $this->course_db->rows();
    }

    public function getGradeableInfo($gradeable_id, AdminGradeable $admin_gradeable, $template=false) {
        $this->course_db->query("SELECT * FROM gradeable WHERE g_id=?",array($gradeable_id));
        $admin_gradeable->setGradeableInfo($this->course_db->row(), $template);
        $this->course_db->query("SELECT * FROM gradeable_component WHERE g_id=? ORDER BY gc_order", array($gradeable_id));
        $admin_gradeable->setOldComponentsJson(json_encode($this->course_db->rows()));
        $components = array();
        foreach($this->course_db->rows() as $row) {
            $components[] = new GradeableComponent($this->core, $row);
        }
        $admin_gradeable->setOldComponents($components);
        foreach($components as $comp) {
            if($comp->getOrder() == -1 && $comp->getIsPeer()) {
                $admin_gradeable->setPeerGradeCompleteScore($comp->getMaxValue());
            }
            if($comp->getPage() != 0) {
                $admin_gradeable->setPdfPage(true);
                if($comp->getPage() == -1) {
                    $admin_gradeable->setPdfPageStudent(true);
                }
            }
        }
        //2 is numeric/text
        if($admin_gradeable->getGGradeableType() == 2) {
            $this->course_db->query("SELECT COUNT(*) AS cnt FROM gradeable AS g INNER JOIN gradeable_component AS gc
                        ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='false'", array($gradeable_id));
            $num['num_numeric'] = $this->course_db->row()['cnt'];
            $this->course_db->query("SELECT COUNT(*) AS cnt FROM gradeable AS g INNER JOIN gradeable_component AS gc
                        ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='true'", array($gradeable_id));
            $num['num_text'] = $this->course_db->row()['cnt'];
            $admin_gradeable->setNumericTextInfo($num);
        }
        $this->course_db->query("SELECT COUNT(*) as cnt FROM gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id
                    INNER JOIN gradeable_component_data AS gcd ON gcd.gc_id=gc.gc_id WHERE g.g_id=?",array($gradeable_id));
        $has_grades= $this->course_db->row()['cnt'];
        $admin_gradeable->setHasGrades($has_grades);
        //0 is electronic
        if($admin_gradeable->getGGradeableType() == 0) {
            //get the electronic file stuff
            $this->course_db->query("SELECT * FROM electronic_gradeable WHERE g_id=?", array($gradeable_id));
            $admin_gradeable->setElectronicGradeableInfo($this->course_db->row(), $template);
        }
        return $admin_gradeable;
    }

    public function getUsersWithLateDays() {
        $this->course_db->query("
        SELECT u.user_id, user_firstname, user_preferred_firstname,
          user_lastname, allowed_late_days, since_timestamp::timestamp::date
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

}

