<?php

namespace app\libraries\database;

use app\libraries\Database;

class DatabaseQueriesPostgresql implements IDatabaseQueries{
    /**
     * @var Database
     */
    private $database;

    public function __construct(Database $database) {
        $this->database = $database;
    }

    public function getUserById($user_id) {
        $this->database->query("SELECT * FROM users WHERE user_id=?", array($user_id));
        return $this->database->row();
    }

    public function getAssignmentById($assignment_id) {
        // TODO: Implement getAssignmentById() method.
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
    
    public function getAllStudents() {
        $this->database->query("
SELECT u.*, s.section_title
FROM users u 
LEFT JOIN (
    SELECT section_number, section_title 
    FROM sections
) as s ON s.section_number = u.user_course_section
WHERE user_group=1
ORDER BY u.user_course_section, u.user_id");
        return $this->database->rows();
    }

    public function getAllUsers() {
        $this->database->query("
SELECT u.*, s.section_title, g.group_name
FROM users u 
LEFT JOIN (
    SELECT section_number, section_title 
    FROM sections
) as s ON s.section_number = u.user_course_section
LEFT JOIN (
    SELECT group_number, group_name
    FROM groups
) as g ON g.group_number = u.user_group
WHERE user_group > 1
ORDER BY u.user_id");
        return $this->database->rows();
    }

    public function getAllGroups() {
        $this->database->query("SELECT * FROM groups ORDER BY group_number");
        return $this->database->rows();
    }

    public function getAllCourseSections() {
        $this->database->query("SELECT * FROM sections ORDER BY section_number");
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