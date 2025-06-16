<?php

namespace app\repositories;

use Doctrine\ORM\EntityRepository;
use app\entities\UnverifiedUserEntity;

class UnverifiedUserRepository extends EntityRepository {
    /**
     * @return UnverifiedUserEntity[]
     */
    public function getUnverifiedUsers(string $user_id, string $email): array {
        $qb = $this->getEntityManager()->createQueryBuilder();
        return $qb->select('u')
            ->from('app\entities\UnverifiedUserEntity', 'u')
            ->where('u.user_email = :email')
            ->orWhere('u.user_id = :user_id')
            ->setParameter('email', $email)
            ->setParameter('user_id', $user_id)
            ->getQuery()
            ->getResult();
    }
}
