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

        return $this->getEntityManager()->createQuery('
            SELECT b
            FROM app\entities\banner\BannerImage b
            WHERE
                b.release_date <= CURRENT_TIMESTAMP()
                AND CURRENT_TIMESTAMP() <= b.closing_date
            ORDER BY b.release_date DESC
        ')
            ->getResult();
    }
}
