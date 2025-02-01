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

    /**
     * @param int $thread_id
     * @return int[]
     */
    public function getCategoriesIdForThread(int $thread_id): array {
        $qb = $this->createQueryBuilder('c');
        $qb->select('c.category_id')
            ->join('c.threads', 't')
            ->where('t.id = :thread_id')
            ->setParameter('thread_id', $thread_id);

        $result = $qb->getQuery()->getSingleColumnResult();

        return array_map('intval', $result);
    }
}
