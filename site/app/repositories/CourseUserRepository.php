<?php

namespace app\repositories;

use Doctrine\ORM\EntityRepository;

class CourseUserRepository extends EntityRepository {
    public function getTermStartDate(string $term_id): string {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $result = $qb->select('term.start_date')
            ->from('app\entities\Term', 'term')
            ->where('term.term_id = :term_id')
            ->setParameter('term_id', $term_id)
            ->getQuery()
            ->getResult();
        return array_column($result, 'start_date')[0];
    }

    public function unregisterCourseUser(string $user_id, string $term, string $course): void {
        $this->getEntityManager()
            ->createQueryBuilder()
            ->update('app\entities\CourseUser', 'CourseUser')
            ->set('CourseUser.registration_section', ':registration_section')
            ->where('CourseUser.user_id = :user_id')
            ->andWhere('CourseUser.term = :term')
            ->andWhere('CourseUser.course = :course')
            ->setParameter('registration_section', NULL)
            ->setParameter('user_id', $user_id)
            ->setParameter('term', $term)
            ->setParameter('course', $course)
            ->getQuery()
            ->getResult();
    }

    public function updateCourseUser(string $user_id, string $term, string $course): void {
        $params = [$user->getGroup(), $user->getRegistrationSection(),
                            $this->submitty_db->convertBoolean($user->isManualRegistration()),
                            $user->getRegistrationType(), $semester, $course,
                            $user->getId()];
            $this->submitty_db->query(
                "
UPDATE courses_users SET user_group=?, registration_section=?, manual_registration=?, registration_type=?
WHERE term=? AND course=? AND user_id=?",
                $params
            );
        $user = $this->findOneBy([
                'user_id' => $user->getId(),
                'term' => $term,
                'course' => $course
            ]);
        $user->setRegistrationSection($registration_section);
        $user->setUserGroup($user_group);
        $user->setManualRegistration($manual_registration);
        $user->setRegistrationType($registration_type);
        $this->getEntityManager()->persist();
        $this->getEntityManager()->flush();
    }



    
}
