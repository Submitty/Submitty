<?php

namespace app\repositories;

use app\entities\Session;
use Doctrine\ORM\EntityRepository;

class CourseEntityRepository extends EntityRepository {
    /**
     * @param string $session_id
     * @return Session|null
     */
    public function getActiveSessionById(string $session_id): ?Session {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb = $qb->select('s')
            ->from('app\entities\Session', 's')
            ->where('s.session_id = :session_id')
            ->setParameter('session_id', $session_id)
            ->andWhere('s.session_expires > CURRENT_TIMESTAMP()');
        $result = $qb->getQuery()->execute();
        return empty($result) ? null : $result[0];
    }
}
