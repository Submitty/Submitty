<?php

namespace app\repositories;

use app\entities\GitAuthToken;
use Doctrine\ORM\EntityRepository;

class GitAuthTokenRepository extends EntityRepository {
    /**
     * @param string $user_id
     * @param bool $expired
     * @return GitAuthToken[]
     */
    public function getAllByUser(string $user_id, bool $expired = false): array {
        $qb = $this->_em->createQueryBuilder();
        $qb = $qb->select('g')
            ->from('\app\entities\GitAuthToken', 'g');
        if (!$expired) {
            $qb = $qb->where(
                $qb->expr()->orX(
                    $qb->expr()->isNull('g.expiration'),
                    $qb->expr()->gt('g.expiration', ':now')
                )
            )->setParameter('now', new \DateTime());
        }
        $qb = $qb
            ->andWhere('g.user_id = :user')
            ->setParameter('user', $user_id);
        return $qb->getQuery()->execute();
    }
}
