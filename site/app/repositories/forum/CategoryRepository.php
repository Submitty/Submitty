<?php

declare(strict_types=1);

namespace app\repositories\forum;

use app\entities\forum\Category;
use Doctrine\ORM\EntityRepository;

class CategoryRepository extends EntityRepository {
    /**
     * @return Category[]
     */
    public function getCategories(): array {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('category')
            ->from(Category::class, 'category')
            ->addOrderBy('category.rank', 'ASC')
            ->addOrderBy('CASE WHEN category.rank IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('category.category_id', 'ASC');
        return $qb->getQuery()->getResult();
    }
}
