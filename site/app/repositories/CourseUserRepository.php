<?php

namespace app\repositories;

use app\models\User;

use Doctrine\ORM\EntityRepository;

class CourseUserRepository extends EntityRepository {
    public function unregisterCourseUser(string $user_id, string $term, string $course): void {
        $course_user = $this->findOneBy([
            'user_id' => $user_id,
            'term' => $term,
            'course' => $course
        ]);
        $course_user->setRegistrationSection(null);
        $this->getEntityManager()->persist($course_user);
        $this->getEntityManager()->flush();
    }

    public function updateCourseUser(User $user, string $term, string $course): void {
        $course_user = $this->findOneBy([
            'user_id' => $user->getId(),
            'term' => $term,
            'course' => $course
        ]);
        $course_user->setRegistrationSection($user->getRegistrationSection());
        $course_user->setUserGroup($user->getGroup());
        $course_user->setManualRegistration($user->isManualRegistration());
        $course_user->setRegistrationType($user->getRegistrationType());
        $this->getEntityManager()->persist($course_user);
        $this->getEntityManager()->flush();
    }

     /**
     * Returns true if the student was ever in the course,
     * even if they are in the null section now.
     * @param string $user_id The name of the user.
     * @param string $course The course we're looking at.
     * @param string $term The term we're looking t.
     * @return bool True if the student was ever in the course, false otherwise.
     */
    public function wasStudentEverInCourse(
        string $user_id,
        string $course,
        string $term
    ): bool {
        $user = $this->findOneBy([
                'user_id' => $user_id,
                'term' => $term,
                'course' => $course
        ]);
        return $user !== null;
    }
}
