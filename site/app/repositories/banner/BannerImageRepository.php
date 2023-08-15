<?php

namespace app\repositories\banner;

use app\entities\banner\BannerImage;
use Doctrine\ORM\EntityRepository;

class BannerImageRepository extends EntityRepository {
    /**
     * @return BannerImage[]
     */
    public function getValidBannerImages(): array {
        $currentDate = new \DateTime();

        $dql = 'SELECT b FROM app\entities\banner\BannerImage b WHERE b.release_date <= :currentDate AND :currentDate <= b.closing_date ORDER BY b.release_date DESC';
        return $this->_em->createQuery($dql)
            ->setParameter('currentDate', $currentDate)
            ->getResult();
    }
    /**
     * @return int
     */
    public function getLastBannerImageId(): int {
        $dql = 'SELECT b.id FROM app\entities\banner\BannerImage b WHERE b.release_date <= :currentDate AND :currentDate <= b.closing_date ORDER BY b.id DESC';        
        $query = $this->_em->createQuery($dql)
            ->setParameter('currentDate', new \DateTime())
            ->setMaxResults(1);

        $result = $query->getOneOrNullResult();

        return $result !== null ? $result['id'] : 0;
    }
}
