<?php

namespace app\repositories\course;

use app\entities\course\CourseMaterial;
use Doctrine\ORM\EntityRepository;

class CourseMaterialRepository extends EntityRepository {
    /**
     * @return CourseMaterial[]
     */
    public function getCourseMaterials(): array {
        return $this->findBy(['is_deleted' => false], ['priority' => 'ASC', 'path' => 'ASC']);
    }
}
