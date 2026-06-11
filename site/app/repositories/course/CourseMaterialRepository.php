<?php

namespace app\repositories\course;

use app\entities\course\CourseMaterial;
use Doctrine\ORM\EntityRepository;

class CourseMaterialRepository extends EntityRepository {
    /**
     * @return CourseMaterial[]
     */
    public function getCourseMaterials(): array {
        return $this->findBy([], ['priority' => 'ASC', 'path' => 'ASC']);
    }

    /**
     * @param string $user_id
     * @return array
     */
    public function getCourseMaterialsViewedByUser(string $user_id): array {
        return $this->createQueryBuilder('cm')
            ->select('IDENTITY(cma.course_material)')
            ->from(\app\entities\course\CourseMaterialAccess::class, 'cma')
            ->where('cma.user_id = :user_id')
            ->setParameter('user_id', $user_id)
            ->getQuery()
            ->getSingleColumnResult();
    }
}
