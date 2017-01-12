<?php

namespace app\libraries\database;

use app\libraries\Core;
use app\libraries\Database;
use app\libraries\Utils;
use app\models\GradeableComponent;
use app\models\GradeableDb;
use app\models\GradeableVersion;
use app\models\User;

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

    public function getAllGradeables($user_id = null) {
        $this->database->query("
SELECT egv.*, egd.*, eg.*, gd.*, gc1.total_tagrading_extra_credit, gc2.total_tagrading_non_extra_credit, g.*
FROM gradeable as g 
LEFT JOIN (
  SELECT *
  FROM electronic_gradeable
) as eg ON eg.g_id = g.g_id
LEFT JOIN (
  SELECT SUM(gc_max_value) as total_tagrading_extra_credit, g_id
  FROM gradeable_component
  WHERE gc_is_text = FALSE AND gc_is_extra_credit = FALSE
  GROUP BY g_id
) AS gc1 ON g.g_id=gc1.g_id
LEFT JOIN (
  SELECT SUM(gc_max_value) as total_tagrading_non_extra_credit, g_id
  FROM gradeable_component
  WHERE gc_is_text = FALSE AND gc_is_extra_credit = TRUE
  GROUP BY g_id
) AS gc2 ON g.g_id=gc2.g_id
LEFT JOIN (
  SELECT *
  FROM gradeable_data
  WHERE gd_user_id=?
) as gd ON gd.g_id=g.g_id
LEFT JOIN (
  SELECT *
  FROM electronic_gradeable_version
  WHERE user_id=?
) as egv ON egv.g_id=g.g_id
LEFT JOIN (
  SELECT *
  FROM electronic_gradeable_data
) as egd ON egd.g_id=g.g_id AND egd.g_version=egv.active_version
ORDER BY g.g_id", array($user_id, $user_id));
        $return = array();
        foreach ($this->database->rows() as $row) {
            $return[$row['g_id']] = new GradeableDb($this->core, $row);
        }
        //var_dump($return);
        //die();
        return $return;
    }

    public function getGradeableById($g_id, $user_id = null) {
        $this->database->query("
SELECT gd.*, egv.*, egd.*, gc1.total_tagrading_extra_credit, gc2.total_tagrading_non_extra_credit, g.*, eg.*
FROM gradeable as g
LEFT JOIN (
  SELECT *
  FROM electronic_gradeable
) as eg ON eg.g_id = g.g_id
LEFT JOIN (
  SELECT SUM(gc_max_value) as total_tagrading_extra_credit, g_id
  FROM gradeable_component
  WHERE gc_is_text = FALSE AND gc_is_extra_credit = FALSE
  GROUP BY g_id
) AS gc1 ON g.g_id=gc1.g_id
LEFT JOIN (
  SELECT SUM(gc_max_value) as total_tagrading_non_extra_credit, g_id
  FROM gradeable_component
  WHERE gc_is_text = FALSE AND gc_is_extra_credit = TRUE
  GROUP BY g_id
) AS gc2 ON g.g_id=gc2.g_id
LEFT JOIN (
  SELECT *
  FROM gradeable_data
  WHERE gd_user_id=?
) as gd ON gd.g_id=g.g_id
LEFT JOIN (
  SELECT *
  FROM electronic_gradeable_version
  WHERE user_id=?
) as egv ON egv.g_id=g.g_id
LEFT JOIN (
  SELECT *
  FROM electronic_gradeable_data
) as egd ON egd.g_id=g.g_id AND egd.g_version=egv.active_version
WHERE g.g_id=?", array($user_id, $user_id, $g_id));
        if (count($this->database->rows()) === 0) {
            return null;
        }
        return new GradeableDb($this->core, $this->database->row());
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

    public function getGradeableVersions($g_id, $user_id, $due_date) {
        $this->database->query("
SELECT egd.*, egv.active_version = egd.g_version as active_version
FROM electronic_gradeable_data AS egd
LEFT JOIN (
  SELECT *
  FROM electronic_gradeable_version
) AS egv ON egv.active_version = egd.g_version AND egv.user_id = egd.user_id AND egv.g_id = egd.g_id
WHERE egd.g_id=? AND egd.user_id=?
ORDER BY egd.g_version", array($g_id, $user_id));
        $return = array();
        foreach ($this->database->rows() as $row) {
            $return[$row['g_version']] = new GradeableVersion($row, $due_date, new \DateTimeZone($this->core->getConfig()->getTimezone()));
        }

        return $return;
    }

    public function getGradeableForUsers($g_id, $users, $section_key="registration_section") {
        $return = array();
        if (count($users) > 0) {
            $keys = array("registration_section", "rotating_section");
            $section_key = (in_array($section_key, $keys)) ? $section_key : "registration_section";
            $users_query = implode(",", array_fill(0, count($users), "?"));
            $this->database->query("
    SELECT 
      egv.*, egd.*, gc1.total_tagrading_extra_credit, gc2.total_tagrading_non_extra_credit, gcd.graded_tagrading, 
      gd.*, eg.*, g.*, u.*
    FROM users AS u
    NATURAL JOIN gradeable AS g
    LEFT JOIN (
      SELECT *
      FROM gradeable_data
    ) AS gd ON gd.gd_user_id=u.user_id AND g.g_id=gd.g_id
    LEFT JOIN (
      SELECT SUM(gc_max_value) as total_tagrading_extra_credit, g_id
      FROM gradeable_component
      WHERE gc_is_text = FALSE AND gc_is_extra_credit = TRUE
      GROUP BY g_id
    ) AS gc1 ON g.g_id=gc1.g_id
    LEFT JOIN (
      SELECT SUM(gc_max_value) as total_tagrading_non_extra_credit, g_id
      FROM gradeable_component
      WHERE gc_is_text = FALSE AND gc_is_extra_credit = FALSE
      GROUP BY g_id
    ) AS gc2 ON g.g_id=gc2.g_id
    LEFT JOIN (
      SELECT SUM(gcd_score) as graded_tagrading, gd_id
      FROM gradeable_component_data
      GROUP BY gd_id
    ) AS gcd ON gd.gd_id=gcd.gd_id
    LEFT JOIN (
      SELECT *
      FROM electronic_gradeable
    ) AS eg ON eg.g_id=g.g_id
    LEFT JOIN (
      SELECT *
      FROM electronic_gradeable_version
    ) AS egv ON g.g_id=egv.g_id AND u.user_id=egv.user_id
    LEFT JOIN (
      SELECT *
      FROM electronic_gradeable_data
    ) AS egd ON g.g_id=egd.g_id AND egv.active_version=egd.g_version AND u.user_id=egd.user_id
    WHERE g.g_id=? AND u.user_id IN ({$users_query})
    ORDER BY u.{$section_key}, u.user_id", array_merge(array($g_id), $users));

            foreach ($this->database->rows() as $row) {
                $return[] = new GradeableDb($this->core, $row, new User($row));
            }
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
{$where}", $params);
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
        $this->database->query("SELECT sections_rotating_id FROM grading_rotating WHERE user_id=? AND g_id=?", array($user, $g_id));
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
GROUP BY registration_section 
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
SELECT count(u.*) as cnt, u.registration_section
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
WHERE g.g_id=? {$where}", $params);
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

    public function insertVersionDetails($g_id, $user_id, $version, $timestamp) {
        $this->database->query("
INSERT INTO electronic_gradeable_data 
(g_id, user_id, g_version, autograding_non_hidden_non_extra_credit, autograding_non_hidden_extra_credit, 
autograding_hidden_non_extra_credit, autograding_hidden_extra_credit, submission_time) 
VALUES(?, ?, ?, 0, 0, 0, 0, ?)", array($g_id, $user_id, $version, $timestamp));
        $this->database->query("SELECT * FROM electronic_gradeable_version WHERE g_id=? AND user_id=?",
            array($g_id, $user_id));
        $row = $this->database->row();
        if (!empty($row)) {
            $this->updateActiveVersion($g_id, $user_id, $version);
        }
        else {
            $this->database->query("INSERT INTO electronic_gradeable_version (g_id, user_id, active_version) VALUES(?, ?, ?)",
                array($g_id, $user_id, $version));
        }
    }

    public function updateActiveVersion($g_id, $user_id, $version) {
        $this->database->query("UPDATE electronic_gradeable_version SET active_version=? WHERE g_id=? AND user_id=?",
            array($version, $g_id, $user_id));
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
}