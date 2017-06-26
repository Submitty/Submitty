<?php

namespace app\libraries\database;

use app\libraries\Core;
use app\libraries\Database;
use app\libraries\Utils;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\GradeableVersion;
use app\models\User;
use app\models\SimpleLateUser;
use app\models\Team;

class DatabaseQueriesPostgresql implements IDatabaseQueries{
    /** @var Core */
    private $core;

    /** @var Database */
    private $database;

    public function __construct(Core $core) {
        $this->core = $core;
        $this->database = $core->getDatabase();
    }

    public function getUserById($user_id) {
        $this->database->query("
SELECT u.*, sr.grading_registration_sections
FROM users u
LEFT JOIN (
	SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
	FROM grading_registration
	GROUP BY user_id
) as sr ON u.user_id=sr.user_id
WHERE u.user_id=?", array($user_id));
        return new User($this->database->row());
    }
    
    public function getAllUsers($section_key="registration_section") {
        $keys = array("registration_section", "rotating_section");
        $section_key = (in_array($section_key, $keys)) ? $section_key : "registration_section";
        $this->database->query("
SELECT u.*, sr.grading_registration_sections
FROM users u
LEFT JOIN (
	SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
	FROM grading_registration
	GROUP BY user_id
) as sr ON u.user_id=sr.user_id
ORDER BY u.{$section_key}, u.user_id");
        $return = array();
        foreach ($this->database->rows() as $row) {
            $return[] = new User($row);
        }
        return $return;
    }

    public function getAllGraders() {
        $this->database->query("
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
        foreach ($this->database->rows() as $row) {
            $return[] = new User($row);
        }
        return $return;
    }

    public function createUser(User $user) {

        $array = array($user->getId(), $user->getFirstName(), $user->getPreferredFirstName(), $user->getLastName(),
            $user->getEmail(), $user->getGroup(), $user->getRegistrationSection(), $user->getRotatingSection(),
            Utils::convertBooleanToString($user->isManualRegistration()));

        $this->database->query("
INSERT INTO users (user_id, user_firstname, user_preferred_firstname, user_lastname, user_email, 
                   user_group, registration_section, rotating_section, manual_registration) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", $array);
        $this->updateGradingRegistration($user->getId(), $user->getGroup(), $user->getGradingRegistrationSections());
    }

    public function updateUser(User $user) {
        $array = array($user->getFirstName(), $user->getPreferredFirstName(), $user->getLastName(),
            $user->getEmail(), $user->getGroup(), $user->getRegistrationSection(), $user->getRotatingSection(),
            Utils::convertBooleanToString($user->isManualRegistration()), $user->getId());
        $this->database->query("
UPDATE users SET user_firstname=?, user_preferred_firstname=?, user_lastname=?, user_email=?, user_group=?, 
registration_section=?, rotating_section=?, manual_registration=?
WHERE user_id=?", $array);
        $this->updateGradingRegistration($user->getId(), $user->getGroup(), $user->getGradingRegistrationSections());
    }

    public function updateGradingRegistration($user_id, $user_group, $sections) {
        $this->database->query("DELETE FROM grading_registration WHERE user_id=?", array($user_id));
        if ($user_group < 4) {
            foreach ($sections as $section) {
                $this->database->query("
    INSERT INTO grading_registration (user_id, sections_registration_id) VALUES(?, ?)", array($user_id, $section));
            }
        }
    }

    public function getGradeableComponents($g_id, $gd_id=null) {
        $this->database->query("
SELECT gcd.*, gc.*
FROM gradeable_component AS gc
LEFT JOIN (
  SELECT *
  FROM gradeable_component_data
  WHERE gd_id = ?
) as gcd ON gc.gc_id = gcd.gc_id
WHERE gc.g_id=?
", array($gd_id, $g_id));

        $return = array();
        foreach ($this->database->rows() as $row) {
            $return[$row['gc_id']] = new GradeableComponent($row);
        }
        return $return;
    }

    public function getGradeableVersions($g_id, $user_id, $team_id, $due_date) {
        if ($user_id === null) {
            $this->database->query("
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
            $this->database->query("
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
        foreach ($this->database->rows() as $row) {
            $row['submission_time'] = new \DateTime($row['submission_time'], $this->core->getConfig()->getTimezone());
            $return[$row['g_version']] = new GradeableVersion($row, $due_date);
        }

        return $return;
    }

    public function getAllGradeables($user_id = null) {
        return $this->getGradeables(null, $user_id);
    }

    public function getGradeable($g_id = null, $user_id = null) {
        return $this->getGradeables($g_id, $user_id)[0];
    }

    /*
     * TODO:
     * This should take in for:
     *  gradeable: [string] or [array] which then maps that into a where clause (g_id = string) OR (g_id IN (?, ?))
     *  users: [string] or [array] which then maps that into a where clause as well as adding in additional
     *      components for the SELECT cause and in the FROM clause (don't need gradeable_data if this is null, etc.)
     *  section_key:
     */
    public function getGradeables($g_ids = null, $user_ids = null, $section_key="registration_section", $sort_key="u.user_id") {
        $return = array();
        $g_ids_query = "";
        $users_query = "";
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

        if ($user_ids !== null) {
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
        $section_keys = array("registration_section", "rotating_section");
        $section_key = (in_array($section_key, $section_keys)) ? $section_key : "registration_section";
        $sort_keys = array("u.user_firstname", "u.user_lastname", "u.user_id");
        $sort_key = (in_array($sort_key, $sort_keys)) ? $sort_key : "u.user_id";
        $sort = array();
        switch ($sort_key) {
            case 'u.user_firstname':
                $sort[] = 'u.user_firstname';
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
  eg.eg_use_ta_grading,
  eg.eg_submission_open_date,
  eg.eg_submission_due_date,
  eg.eg_late_days,
  eg.eg_precision,
  gc.array_gc_id,
  gc.array_gc_title,
  gc.array_gc_ta_comment,
  gc.array_gc_student_comment,
  gc.array_gc_max_value,
  gc.array_gc_is_text,
  gc.array_gc_is_extra_credit,
  gc.array_gc_order";
        if ($user_ids !== null) {
            $query .= ",
  gd.gd_id,
  gd.gd_grader_id,
  gd.gd_overall_comment,
  gd.gd_status,
  gd.gd_late_days_used,
  gd.gd_active_version,
  gd.array_gcd_gc_id,
  gd.array_gcd_score,
  gd.array_gcd_component_comment,
  gd.gd_user_viewed_date,
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
  egd.submission_time
FROM users AS u
NATURAL JOIN gradeable AS g";
        }
        else {
            $query .= "
FROM gradeable AS g";
        }
        $query .= "
LEFT JOIN (
  SELECT *
  FROM electronic_gradeable
) AS eg ON eg.g_id=g.g_id
LEFT JOIN (
  SELECT
    g_id,
    array_agg(gc_id) as array_gc_id,
    array_agg(gc_title) AS array_gc_title,
    array_agg(gc_ta_comment) AS array_gc_ta_comment,
    array_agg(gc_student_comment) AS array_gc_student_comment,
    array_agg(gc_max_value) AS array_gc_max_value,
    array_agg(gc_is_text) AS array_gc_is_text,
    array_agg(gc_is_extra_credit) AS array_gc_is_extra_credit,
    array_agg(gc_order) AS array_gc_order
  FROM gradeable_component
  GROUP BY g_id
) AS gc ON gc.g_id=g.g_id";
        if ($user_ids !== null) {
            $query .= "
LEFT JOIN (
  SELECT 
    in_gd.*,
    in_gcd.array_gcd_gc_id,
    in_gcd.array_gcd_score,
    in_gcd.array_gcd_component_comment
  FROM gradeable_data as in_gd
  LEFT JOIN (
    SELECT
      gd_id,
      array_agg(gc_id) AS array_gcd_gc_id,
      array_agg(gcd_score) as array_gcd_score,
      array_agg(gcd_component_comment) as array_gcd_component_comment
    FROM gradeable_component_data
    GROUP BY gd_id
  ) AS in_gcd ON in_gd.gd_id = in_gcd.gd_id
) AS gd ON gd.gd_user_id = u.user_id AND g.g_id = gd.g_id
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
    WHERE g.g_id = gt.g_id AND gt.team_id = t.team_id AND t.team_id = egd.team_id AND t.state = 1))";
        }

        $where = array();
        if ($g_ids !== null) {
            $where[] = "g.g_id IN ({$g_ids_query})";
        }
        if ($user_ids !== null) {
            $where[] = "u.user_id IN ({$users_query})";
        }
        if (count($where) > 0) {
            $query .= "
WHERE ".implode(" AND ", $where);
        }
        if ($user_ids !== null) {
          $query .= "
ORDER BY u.{$section_key}, {$sort_key}";
        }


        $this->database->query($query, $params);

        foreach ($this->database->rows() as $row) {
            $user = (isset($row['user_id']) && $row['user_id'] !== null) ? new User($row) : null;
            $return[] = new Gradeable($this->core, $row, $user);
        }

        return $return;
    }

    public function getUsersByRegistrationSections($sections) {
        $return = array();
        if (count($sections) > 0) {
            $query = implode(",", array_fill(0, count($sections), "?"));
            $this->database->query("SELECT * FROM users WHERE registration_section IN ({$query}) ORDER BY registration_section", $sections);
            foreach ($this->database->rows() as $row) {
                $return[] = new User($row);
            }
        }
        return $return;
    }

    public function getTotalUserCountByRegistrationSections($sections) {
        $return = array();
        $params = array();
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE registration_section IN (".implode(",", array_fill(0, count($sections), "?")).")";
            $params = $sections;
        }
        $this->database->query("
SELECT count(*) as cnt, registration_section 
FROM users 
{$where}
GROUP BY registration_section 
ORDER BY registration_section", $params);
        foreach ($this->database->rows() as $row) {
            if ($row['registration_section'] === null) {
                $row['registration_section'] = "NULL";
            }
            $return[$row['registration_section']] = intval($row['cnt']);
        }
        return $return;
    }

    public function getGradedUserCountByRegistrationSections($g_id, $sections) {
        $return = array();
        $params = array($g_id);
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE registration_section IN (".implode(",", array_fill(0, count($sections), "?")).")";
            $params = array_merge($params, $sections);
        }
        $this->database->query("
SELECT count(u.*) as cnt, u.registration_section
FROM users AS u
INNER JOIN (
  SELECT * FROM gradeable_data WHERE g_id=? AND (gd_active_version >= 0 OR (gd_active_version = -1 AND gd_status = 0))
) AS gd ON u.user_id = gd.gd_user_id
{$where}
GROUP BY u.registration_section
ORDER BY u.registration_section", $params);
        foreach ($this->database->rows() as $row) {
            if ($row['registration_section'] === null) {
                $row['registration_section'] = "NULL";
            }
            $return[$row['registration_section']] = intval($row['cnt']);
        }
        return $return;
    }

    public function getGradersForRegistrationSections($sections) {
        $return = array();
        $params = array();
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE sections_registration_id IN (" . implode(",", array_fill(0, count($sections), "?")) . ")";
            $params = $sections;
        }
        $this->database->query("
SELECT g.*, u.* 
FROM grading_registration AS g
LEFT JOIN (
  SELECT *
  FROM users
) AS u ON u.user_id = g.user_id
{$where}
ORDER BY g.sections_registration_id, g.user_id", $params);
        $user_store = array();
        foreach ($this->database->rows() as $row) {
            if ($row['sections_registration_id'] === null) {
                $row['sections_registration_id'] = "NULL";
            }
            if (!isset($return[$row['sections_registration_id']])) {
                $return[$row['sections_registration_id']] = array();
            }
            if (!isset($user_store[$row['user_id']])) {
                $user_store[$row['user_id']] = new User($row);
            }
            $return[$row['sections_registration_id']][] = $user_store[$row['user_id']];
        }
        return $return;
    }

    public function getRotatingSectionsForGradeableAndUser($g_id, $user) {
        $this->database->query("SELECT sections_rotating_id FROM grading_rotating WHERE g_id=? AND user_id=?", array($g_id, $user));
        $return = array();
        foreach ($this->database->rows() as $row) {
            $return[] = $row['sections_rotating_id'];
        }
        return $return;
    }

    public function getUsersByRotatingSections($sections) {
        $return = array();
        if (count($sections) > 0) {
            $query = implode(",", array_fill(0, count($sections), "?"));
            $this->database->query("SELECT * FROM users WHERE rotating_section IN ({$query}) ORDER BY rotating_section", $sections);
            foreach ($this->database->rows() as $row) {
                $return[] = new User($row);
            }
        }
        return $return;
    }

    public function getTotalUserCountByRotatingSections($sections) {
        $return = array();
        $where = "";
        $params = array();
        if (count($sections) > 0) {
            $where = "WHERE rotating_section IN (".implode(",", array_fill(0, count($sections), "?")).")";
            $params = $sections;
        }
        $this->database->query("
SELECT count(*) as cnt, rotating_section 
FROM users 
{$where}
GROUP BY rotating_section 
ORDER BY rotating_section", $params);
        foreach ($this->database->rows() as $row) {
            if ($row['rotating_section'] === null) {
                $row['rotating_section'] = "NULL";
            }
            $return[$row['rotating_section']] = intval($row['cnt']);
        }
        return $return;
    }

    public function getGradedUserCountByRotatingSections($g_id, $sections) {
        $return = array();
        $params = array($g_id);
        $where = "";
        if (count($sections) > 0) {
            $where = "WHERE rotating_section IN (".implode(",", array_fill(0, count($sections), "?")).")";
            $params = array_merge($params, $sections);
        }
        $this->database->query("
SELECT count(u.*) as cnt, u.rotating_section
FROM users AS u
INNER JOIN (
  SELECT * FROM gradeable_data WHERE g_id=? AND (gd_active_version >= 0 OR (gd_active_version = -1 AND gd_status = 0))
) AS gd ON u.user_id = gd.gd_user_id
{$where}
GROUP BY u.rotating_section
ORDER BY u.rotating_section", $params);
        foreach ($this->database->rows() as $row) {
            if ($row['rotating_section'] === null) {
                $row['rotating_section'] = "NULL";
            }
            $return[$row['rotating_section']] = intval($row['cnt']);
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
        $this->database->query("
SELECT g.*, u.* 
FROM grading_rotating AS g
LEFT JOIN (
  SELECT *
  FROM users
) AS u ON u.user_id = g.user_id
WHERE g.g_id=? {$where}
ORDER BY g.sections_rotating_id, g.user_id", $params);
        $user_store = array();
        foreach ($this->database->rows() as $row) {
            if ($row['sections_rotating_id'] === null) {
                $row['sections_rotating_id'] = "NULL";
            }
            if (!isset($return[$row['sections_rotating_id']])) {
                $return[$row['sections_rotating_id']] = array();
            }
            if (!isset($user_store[$row['user_id']])) {
                $user_store[$row['user_id']] = new User($row);
            }
            $return[$row['sections_rotating_id']][] = $user_store[$row['user_id']];
        }
        return $return;
    }

    public function getRegistrationSections() {
        $this->database->query("SELECT * FROM sections_registration ORDER BY sections_registration_id");
        return $this->database->rows();
    }

    public function getRotatingSections() {
        $this->database->query("SELECT * FROM sections_rotating ORDER BY sections_rotating_id");
        return $this->database->rows();
    }

    public function getCountUsersRotatingSections() {
        $this->database->query("
SELECT rotating_section, count(*) as count
FROM users
WHERE (registration_section IS NOT NULL OR manual_registration)
GROUP BY rotating_section
ORDER BY rotating_section");
        return $this->database->rows();
    }

    public function getCountNullUsersRotatingSections() {
        $this->database->query("
SELECT rotating_section, count(*) as count
FROM users
WHERE (registration_section IS NULL and NOT manual_registration) AND rotating_section IS NOT NULL
GROUP BY rotating_section
ORDER BY rotating_section");
        return $this->database->rows();
    }

    public function getRegisteredUserIdsWithNullRotating() {
        $this->database->query("
SELECT user_id 
FROM users 
WHERE
    (rotating_section IS NULL) and 
    (registration_section IS NOT NULL or manual_registration)
ORDER BY user_id ASC");
        return array_map(function($elem) { return $elem['user_id']; }, $this->database->rows());
    }

    public function getRegisteredUserIds() {
        $this->database->query("
SELECT user_id 
FROM users 
WHERE
    (registration_section IS NOT NULL) OR 
    (manual_registration)
ORDER BY user_id ASC");
        return array_map(function($elem) { return $elem['user_id']; }, $this->database->rows());
    }

    public function setAllUsersRotatingSectionNull() {
        $this->database->query("UPDATE users SET rotating_section=NULL");
    }

    public function setNonRegisteredUsersRotatingSectionNull() {
        $this->database->query("UPDATE users SET rotating_section=NULL WHERE registration_section IS NULL AND NOT manual_registration");
    }

    public function deleteAllRotatingSections() {
        $this->database->query("DELETE FROM sections_rotating");
    }

    public function getMaxRotatingSection() {
        $this->database->query("SELECT MAX(sections_rotating_id) as max FROM sections_rotating");
        $row = $this->database->row();
        return $row['max'];
    }

    public function insertNewRotatingSection($section) {
        $this->database->query("INSERT INTO sections_rotating (sections_rotating_id) VALUES(?)", array($section));
    }

    public function updateUsersRotatingSection($section, $users) {
        $update_array = array_merge(array($section), $users);
        $update_string = implode(",", array_pad(array(), count($users), "?"));
        $this->database->query("UPDATE users SET rotating_section=? WHERE user_id IN ({$update_string})", $update_array);
    }

    public function insertVersionDetails($g_id, $user_id, $team_id, $version, $timestamp) {
        $this->database->query("
INSERT INTO electronic_gradeable_data 
(g_id, user_id, team_id, g_version, autograding_non_hidden_non_extra_credit, autograding_non_hidden_extra_credit, 
autograding_hidden_non_extra_credit, autograding_hidden_extra_credit, submission_time) 
VALUES(?, ?, ?, ?, 0, 0, 0, 0, ?)", array($g_id, $user_id, $team_id, $version, $timestamp));
        if ($user_id === null) {
            $this->database->query("SELECT * FROM electronic_gradeable_version WHERE g_id=? AND team_id=?",
            array($g_id, $team_id));
        }
        else {
            $this->database->query("SELECT * FROM electronic_gradeable_version WHERE g_id=? AND user_id=?",
            array($g_id, $user_id));
        }
        $row = $this->database->row();
        if (!empty($row)) {
            $this->updateActiveVersion($g_id, $user_id, $team_id, $version);
        }
        else {
            $this->database->query("INSERT INTO electronic_gradeable_version (g_id, user_id, team_id, active_version) VALUES(?, ?, ?, ?)",
                array($g_id, $user_id, $team_id, $version));
        }
    }

    public function updateActiveVersion($g_id, $user_id, $team_id, $version) {
        if ($user_id === null) {
            $this->database->query("UPDATE electronic_gradeable_version SET active_version=? WHERE g_id=? AND team_id=?",
            array($version, $g_id, $team_id));
        }
        else {
            $this->database->query("UPDATE electronic_gradeable_version SET active_version=? WHERE g_id=? AND user_id=?",
            array($version, $g_id, $user_id));
        }  
    }

    public function updateGradeableData(Gradeable $gradeable) {
        $this->database->beginTransaction();
        if ($gradeable->getGdId() === null) {
            $params = array($gradeable->getId(), $gradeable->getUser()->getId(), $gradeable->getGrader()->getId(),
                            $gradeable->getOverallComment(), $gradeable->getStatus(), 0,
                            $gradeable->getActiveVersion());
            $this->database->query("INSERT INTO 
gradeable_data (g_id, gd_user_id, gd_grader_id, gd_overall_comment, gd_status, gd_late_days_used, gd_active_version)
VALUES (?, ?, ?, ?, ?, ?, ?)", $params);
            $gradeable->setGdId($this->database->getLastInsertId("gradeable_data_gd_id_seq"));
        }
        else {
            $this->database->query("UPDATE gradeable_data SET gd_grader_id=? WHERE gd_id=?", array($gradeable->getGrader()->getId(), $gradeable->getGdId()));
        }

        foreach ($gradeable->getComponents() as $component) {
            if ($component->getHasGrade()) {
                $params = array($component->getScore(), $component->getComment(), $component->getId(), $gradeable->getGdId());
                $this->database->query("
UPDATE gradeable_component_data SET gcd_score=?, gcd_component_comment=? WHERE gc_id=? AND gd_id=?", $params);
            }
            else {
                $params = array($component->getId(), $gradeable->getGdId(), $component->getScore(),
                                $component->getComment());
                $this->database->query("
INSERT INTO gradeable_component_data (gc_id, gd_id, gcd_score, gcd_component_comment) 
VALUES (?, ?, ?, ?)", $params);
            }

        }
        $this->database->commit();
    }

    public function updateUserViewedDate(Gradeable $gradeable) {
        if ($gradeable->getGdId() !== null) {
            $this->database->query("UPDATE gradeable_data SET gd_user_viewed_date = NOW() WHERE gd_id=?",
                array($gradeable->getGdId()));
        }
    }

    public function getSession($session_id) {
        $this->database->query("SELECT * FROM sessions WHERE session_id=?", array($session_id));
        return $this->database->row();
    }

    public function updateSessionExpiration($session_id) {
        $this->database->query("UPDATE sessions SET session_expires=(current_timestamp + interval '336 hours') 
        WHERE session_id=?", array($session_id));
    }

    public function newSession($session_id, $user_id, $csrf_token) {
        $this->database->query("INSERT INTO sessions (session_id, user_id, csrf_token, session_expires) VALUES(?,?,?,current_timestamp + interval '336 hours')",
                               array($session_id, $user_id, $csrf_token));

    }

    public function removeExpiredSessions() {
        $this->database->query("DELETE FROM sessions WHERE session_expires < current_timestamp");
    }

    public function removeSessionById($session_id) {
        $this->database->query("DELETE FROM sessions WHERE session_id=?", array($session_id));
    }
    
    public function getAllGradeablesIds() {
        $this->database->query("SELECT g_id FROM gradeable ORDER BY g_id");
        return $this->database->rows();
    }

    public function getAllElectronicGradeablesIds() {
        $this->database->query("SELECT g_id, g_title FROM gradeable WHERE g_gradeable_type=0 ORDER BY g_grade_released_date DESC");
        return $this->database->rows();
    }

    function getNewestElectronicGradeableId() {
    //IN:  No parameters
    //OUT: Return gradeable ID of the newest gradeable, or NULL if there are no
    //     gradeables in the system.
    //PURPOSE:  Gradeable drop down menu is ordered with newest first, determined
    //          by start date.
        $this->database->query("SELECT g_id FROM gradeable WHERE g_gradeable_type=0 ORDER BY g_grade_start_date DESC LIMIT 1;");
        return $this->database->rows();
    }

    public function newTeam($g_id, $user_id) {
        $this->database->query("SELECT * FROM gradeable_teams ORDER BY team_id");
        $team_id_prefix = count($this->database->rows());
        $team_id = "{$team_id_prefix}_{$user_id}";
        $this->database->query("INSERT INTO gradeable_teams (team_id, g_id) VALUES(?,?)", array($team_id, $g_id));
        $this->database->query("INSERT INTO teams (team_id, user_id, state) VALUES(?,?,1)", array($team_id, $user_id));
    }

    public function newTeamInvite($team_id, $user_id) {
        $this->database->query("INSERT INTO teams (team_id, user_id, state) VALUES(?,?,0)", array($team_id, $user_id,));
    }

    public function newTeamMember($team_id, $user_id) {
        $this->database->query("INSERT INTO teams (team_id, user_id, state) VALUES(?,?,1)", array($team_id, $user_id,));
    }

    public function removeTeamUser($g_id, $user_id) {
        $this->database->query("
          DELETE FROM teams AS t
          USING gradeable_teams AS gt
          WHERE gt.g_id=? AND gt.team_id = t.team_id AND t.user_id=?",
          array($g_id, $user_id));
    }

    public function getTeamsByGradeableId($g_id) {
        $this->database->query("
          SELECT gt.team_id, t.user_id, t.state
          FROM gradeable_teams AS gt 
          LEFT JOIN (
            SELECT *
            FROM teams
          ) AS t ON gt.team_id=t.team_id
          WHERE g_id=?
          ORDER BY team_id",
          array($g_id));

        $team_rows = array();
        foreach($this->database->rows() as $row) {
            if (!isset($team_rows[$row['team_id']])){
                $team_rows[$row['team_id']] = array();
            }
            $team_rows[$row['team_id']][] = $row;
        }
        $teams = array();
        foreach($team_rows as $team_row) {
            $teams[] = new Team($team_row);
        }
        return $teams;
    }

    public function getTeamByUserId($g_id, $user_id) {
        $this->database->query("
          SELECT team_id, user_id, state
          FROM gradeable_teams NATURAL JOIN teams
          WHERE g_id=? AND team_id IN (
            SELECT team_id
            FROM teams
            WHERE user_id=?)",
          array($g_id, $user_id));

        if (count($this->database->rows()) === 0) {
            return null;
        }
        else {
            $team = new Team($this->database->rows());
            return $team;
        }
    }

    public function getUsersWithLateDays() {
    //IN:  gradeable ID from database
    //OUT: all students who have late days.  Retrieves student rcs, first name,
    //     last name, timestamp and number of late days.
    //PURPOSE:  Retrieve list of students to display current late days.
      $this->database->query("
        SELECT u.user_id, user_firstname, user_preferred_firstname, 
          user_lastname, allowed_late_days, since_timestamp::timestamp::date
        FROM users AS u
        FULL OUTER JOIN late_days AS l
          ON u.user_id=l.user_id
        WHERE allowed_late_days IS NOT NULL
          AND allowed_late_days>0
          -- AND u.user_group=4
        ORDER BY
          user_email ASC, since_timestamp DESC;");

      $return = array();
      foreach($this->database->rows() as $row){
        $return[] = new SimpleLateUser($row);
      }
      return $return;
    }


    public function getUsersWithExtensions($gradeable_id) {
    //IN:  gradeable ID from database
    //OUT: all students who have late day exceptions, per gradeable ID parameter.
    //     retrieves student rcs, first name, last name, and late day exceptions.
    //PURPOSE:  Retrieve list of students to display current late day exceptions.
      $this->database->query("
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
      foreach($this->database->rows() as $row){
        $return[] = new SimpleLateUser($row);
      }
      return $return;
    }

    public function updateLateDays($user_id, $timestamp, $days){
        $this->database->query("
          UPDATE late_days
          SET allowed_late_days=?
          WHERE user_id=?
            AND since_timestamp=?", array($days, $user_id, $timestamp));
        if(count($this->database->rows())==0){
          $this->database->query("
            INSERT INTO late_days
            (user_id, since_timestamp, allowed_late_days)
            VALUES(?,?,?)", array($user_id, $timestamp, $days));
        }
    }

    public function updateExtensions($user_id, $g_id, $days){
        $this->database->query("
          UPDATE late_day_exceptions
          SET late_day_exceptions=?
          WHERE user_id=?
            AND g_id=?;", array($days, $user_id, $g_id));
        if(count($this->database->rows())==0){
          $this->database->query("
            INSERT INTO late_day_exceptions
            (user_id, g_id, late_day_exceptions)
            VALUES(?,?,?)", array($user_id, $g_id, $days));
        }
    }
}

