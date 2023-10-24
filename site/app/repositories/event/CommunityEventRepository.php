<?php

namespace app\repositories\event;

use app\entities\event\CommunityEvent;
use Doctrine\ORM\EntityRepository;

class CommunityEventRepository extends EntityRepository {
    /**
     * @return CommunityEvent[]
     */
    public function getValidBannerEventImages(): array {
        $currentDate = new \DateTime();

        return $this->_em->createQuery('SELECT b FROM app\entities\event\CommunityEvent b WHERE b.release_date <= :currentDate AND :currentDate <= b.closing_date ORDER BY b.release_date DESC')
            ->setParameter('currentDate', $currentDate)
            ->getResult();
    }
}
