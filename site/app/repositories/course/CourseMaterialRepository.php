<?php

namespace app\repositories\course;

use Doctrine\ORM\EntityRepository;

class CourseMaterialRepository extends EntityRepository {
    public function getCourseMaterials(): array {
        $dql = 'SELECT c FROM app\entities\course\CourseMaterial c ORDER BY c.priority ASC, c.path ASC';
        return $this->_em->createQuery($dql)->getResult();
    }
}
