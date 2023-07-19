<?php

namespace app\repositories\banner;

use app\entities\banner\BannerImage;
use Doctrine\ORM\EntityRepository;

class BannerImageRepository extends EntityRepository {
    /**
     * @return BannerImage[]
     */
    public function getBannerImages(): array {
        $dql = 'SELECT b FROM app\entities\banner\BannerImage b ORDER BY b.name ASC, b.extra_info ASC';
        return $this->_em->createQuery($dql)->getResult();
    }
}