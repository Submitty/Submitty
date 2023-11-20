<?php

namespace app\repositories\banner;

use app\entities\banner\BannerImage;
use Doctrine\ORM\EntityRepository;

/** 
 * @template-extends EntityRepository<BannerImage>
 */
class BannerImageRepository extends EntityRepository {
    /**
     * @return BannerImage[]
     */
    public function getValidBannerImages(): array {
        $currentDate = new \DateTime();

        return $this->_em->createQuery('
            SELECT b
            FROM app\entities\banner\BannerImage b
            WHERE
                b.release_date <= :currentDate
                AND :currentDate <= b.closing_date
            ORDER BY b.release_date DESC
        ')
            ->setParameter('currentDate', $currentDate)
            ->getResult();
    }
}
