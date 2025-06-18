<?php

namespace app\libraries;

use app\entities\CourseEntity;
use app\repositories\CourseEntityRepository;
use DateInterval;
use DateTime;


class CourseEntityManager {
    public function getCourseInfo(string $course_id, string $term_id) {
        $em = $this->core->getSubmittyEntityManager();
        /** @var CourseEntityRepository $repo */
        $course = $em->getRepository(CourseEntity::class);
        return $repo->getCourseInfo($course_id, $term_id);
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

}