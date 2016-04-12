<?php

namespace app\database;

class QueriesPostgresql implements IQueries{
    public function loadConfig() {
        Database::query("SELECT * FROM config");
        return Database::rows();
    }

    public function getUserById($user_id) {
        Database::query("SELECT * FROM users WHERE user_id=?", array($user_id));
        return Database::row();
    }

    public function getStaffByRcs($staff_id) {
        Database::query("SELECT * FROM staff WHERE staff_id=?", array($staff_id));
        return Database::row();
    }

    public function getStudentByRcs($student_id) {
        Database::query("SELECT * FROM students WHERE student_id=?", array($student_id));
        return Database::row();
    }
}