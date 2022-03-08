<?php

namespace app\repositories;

use app\entities\GitAuthToken;
use Doctrine\ORM\EntityRepository;

class GitAuthTokenRepository extends EntityRepository {
    /**
     * @param string $user_id
     * @return GitAuthToken[]
     */
    public function getAllByUser(string $user_id, bool $expired = false): array {
        $qb = $this->_em->createQueryBuilder();
        $qb = $qb->select('a')
            ->from('\app\entities\GitAuthToken', 'a');
        if (!$expired) {
            $qb = $qb->where(
                $qb->expr()->orX(
                    $qb->expr()->isNull('a.expiration'),
                    $qb->expr()->gt('a.expiration', ':now')
                )
            )->setParameter('now', new \DateTime());
        }
        $qb = $qb
            ->andWhere('a.user_id = :user')
            ->setParameter('user', $user_id);
        return $qb->getQuery()->execute();
    }
}
