<?php
use app\controllers\course\CourseController.php;

// called in CourseController.php
// removes the user from a course
public function removeUserFromCourse($semester, $course, $user_id) {
    $this->submitty_db->beginTransaction();
    try {
        $params = [
            "semester" => $semester,
            "course" => $course,
            "user_id" => $user_id
        ];
        
        $this->submitty_db->query(
            "DELETE FROM courses_users 
            WHERE semester=:semester AND course=:course AND user_id=:user_id",
            $params
        );
        
        $this->submitty_db->commit();
        return true;
    }
    catch (\PDOException $e) {
        $this->submitty_db->rollback();
        throw $e;
    }
}
?>
