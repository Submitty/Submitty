<?php

namespace app\libraries;

use app\entities\CourseUser;
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
