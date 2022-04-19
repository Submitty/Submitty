<?php

namespace app\repositories\course;

use app\entities\course\CourseMaterial;
use Doctrine\ORM\EntityRepository;

class CourseMaterialRepository extends EntityRepository {
    /**
     * @return CourseMaterial[]
     */
    public function getCourseMaterials(): array {
        $dql = 'SELECT c FROM app\entities\course\CourseMaterial c ORDER BY c.priority ASC, c.path ASC';
        return $this->_em->createQuery($dql)->getResult();
    }
    public function findDisplayName(string $dir, string $display_name): array {
        $dql = 'SELECT c FROM app\entities\course\CourseMaterial c WHERE c.path LIKE :dir AND NOT (c.path LIKE :post_dir ) AND c.display_name = :display_name';
        $query = $this->_em->createQuery($dql);
        $query->setParameter('dir', $dir . '/%');
        $query->setParameter('post_dir', $dir . '/%/%');
        $query->setParameter('display_name', $display_name);
        return $query->getResult();
    }
}
