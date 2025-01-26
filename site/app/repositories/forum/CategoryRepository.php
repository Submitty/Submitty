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
            ->addOrderBy('category.rank', 'ASC', 'NULLS LAST')
            ->addOrderBy('category.category_id', 'ASC');
        return $qb->getQuery()->getResult();
    }
}
