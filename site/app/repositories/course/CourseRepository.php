<?php

namespace app\repositories;

use app\entities\Course;
use Doctrine\ORM\EntityRepository;

class CourseRepository extends EntityRepository {
    
    public function getCoursesForUserId(string $user_id, bool $include_archived = false, bool $dropped = false): ?Course {
        $include_archived = "AND c.status=1";
        if ($archived) {
            $include_archived = "AND c.status=2 AND u.user_group=1";
        }
        $not = $dropped ? 'NOT ' : '';
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('t.name AS term_name', 'u.term', 'u.course', 'u.user_group', 'u.registration_section')
            ->from(CourseUser::class, 'u')
            ->innerJoin(Course::class, 'c', 'WITH', 'u.course = c.course AND u.term = c.term')
            ->innerJoin(Term::class, 't', 'WITH', 'u.term = t.termId')
            ->where('u.userId = :user_id')
            ->andWhere($not . '(u.registration_section IS NOT NULL OR u.user_group <> 4)')
            ->setParameter('userId', $userId);
            if ($include_archived) {
                $qb
                ->andWhere('c.status = 2')
                ->andWhere('u.user_group = 1');
            } else {
                $qb->andWhere('c.status = 1');
            }
        $qb->orderBy('u.user_group', 'ASC')
            ->addOrderBy('t.start_date', 'DESC')
            ->addOrderBy('u.course', 'ASC');
        $query = $qb->getQuery();
        $results = $query->getResult();
        return $results;
    }
}
