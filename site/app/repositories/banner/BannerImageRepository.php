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

        $dql = 'SELECT b FROM app\entities\banner\BannerImage b WHERE b.release_date <= :currentDate AND :currentDate <= b.closing_date';
        
        return $this->_em->createQuery($dql)
            ->setParameter('currentDate', $currentDate)
            ->getResult();
    }
}