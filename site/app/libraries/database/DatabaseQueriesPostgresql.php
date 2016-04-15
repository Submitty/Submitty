<?php

namespace app\libraries\database;

use app\libraries\Database;

class DatabaseQueriesPostgresql implements IDatabaseQueries{
    private $database;

    /**
     * QueriesPostgresql constructor.
     *
     * @param Database $database
     */
    public function __construct(Database $database) {
        $this->database = $database;
    }

    public function loadConfig() {
        $this->database->query("SELECT * FROM config");
        return $this->database->rows();
    }

    public function getUserById($user_id) {
        $this->database->query("SELECT * FROM users WHERE user_id=?", array($user_id));
        return $this->database->row();
    }

    public function getAssignmentById($assignment_id) {
        // TODO: Implement getAssignmentById() method.
    }

    public function getAllAssignments() {
        $this->database->query("
SELECT r.*, CASE WHEN (q.cnt > 0) THEN true ELSE false END as has_rubric
FROM assignments as r
LEFT JOIN (
	SELECT assignment_id, count(*) as cnt
	FROM questions
	GROUP BY assignment_id
) as q ON q.assignment_id=r.assignment_id
ORDER BY assignment_due_date, assignment_id");
        return $this->database->rows();
    }
}