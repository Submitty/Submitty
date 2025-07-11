<?php

namespace app\libraries;

use app\entities\CourseUser;
use app\models\User;
use app\repositories\CourseUserRepository;

/**
 * Class CourseUserManager
 */
class CourseUserManager {
    public static function unregisterCourseUser(Core $core, string $user_id, string $term, string $course): void {
        $em = $core->getSubmittyEntityManager();
        /** @var CourseUserRepository $repo */
        $repo = $em->getRepository(CourseUser::class);
        $timestamp = $repo->unregisterCourseUser($user_id, $term, $course);
    }

    public static function wasStudentEverInCourse(Core $core, string $user_id, string $term, string $course): bool {
        $em = $core->getSubmittyEntityManager();
        /** @var CourseUserRepository $repo */
        $repo = $em->getRepository(CourseUser::class);
        return $repo->wasStudentEverInCourse($user_id, $term, $course);
    }

    public static function updateCourseUser(Core $core, User $user, string $term, string $course): void {
        $em = $core->getSubmittyEntityManager();
        /** @var CourseUserRepository $repo */
        $repo = $em->getRepository(CourseUser::class);
        $timestamp = $repo->updateCourseUser($user, $term, $course);
    }

    public static function addCourseUser(Core $core, User $user, string $term, string $course) {
        $params = [$semester, $course, $user->getId(), $user->getGroup(), $user->getRegistrationSection(),
                        $this->submitty_db->convertBoolean($user->isManualRegistration())];
        $em = $core->getSubmittyEntityManager();
        $course_user = new CourseUser(
            $term,
            $course,
            $user->getId(),
            $user->getGroup(),
            $user->getRegistrationSection(),
            $user->getRegistrationType(),
            $user->isManualRegistration(),
            ""
        );
        $em->persist($course_user);
        $em->flush();
    }

    // /**
    //  * @return string[]
    //  */
    // public static function getAllTermNames(Core $core): array {
    //     $em = $core->getSubmittyEntityManager();
    //     /** @var TermRepository $repo */
    //     $repo = $em->getRepository(Term::class);
    //     return $repo->getAllTermNames();
    // }

    // public static function createNewTerm(Core $core, string $term_id, string $term_name, string $start_date, string $end_date): void {
    //     $em = $core->getSubmittyEntityManager();
    //     $term = new Term(
    //         $term_id,
    //         $term_name,
    //         $start_date,
    //         $end_date,
    //     );
    //     $em->persist($term);
    //     $em->flush();
    // }
}
