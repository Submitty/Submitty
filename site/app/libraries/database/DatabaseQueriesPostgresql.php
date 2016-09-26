<?php

namespace app\libraries\database;

use app\libraries\Database;
use app\libraries\Utils;
use app\models\User;

class DatabaseQueriesPostgresql implements IDatabaseQueries{
    /**
     * @var Database
     */
    private $database;

    public function __construct(Database $database) {
        $this->database = $database;
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
    
    public function getAllUsers() {
        $this->database->query("
SELECT u.*, sr.grading_registration_sections
FROM users u
LEFT JOIN (
	SELECT array_agg(sections_registration_id) as grading_registration_sections, user_id
	FROM grading_registration
	GROUP BY user_id
) as sr ON u.user_id=sr.user_id
ORDER BY u.registration_section, u.user_id");
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

    public function getAllGradeableIds() {
        $this->database->query("SELECT g_id FROM gradeable ORDER BY g_id");
        return $this->database->rows();
    }

    public function getGradeableById($g_id) {
        $this->database->query("SELECT g.*, eg.*
FROM gradeable as g
LEFT JOIN (
	SELECT *
	FROM electronic_gradeable
) as eg ON eg.g_id = g.g_id
WHERE g.g_id=?", array($g_id));
        return $this->database->row();
    }

    public function getRegistrationSections() {
        $this->database->query("SELECT * FROM sections_registration ORDER BY sections_registration_id");
        return $this->database->rows();
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