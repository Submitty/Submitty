<?php

declare(strict_types=1);

namespace app\repositories\forum;

use app\entities\forum\Thread;
use app\entities\forum\Category;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\EntityRepository;

class ThreadRepository extends EntityRepository {
    /**
     * @param int[] $category_ids
     * @return Thread[]
     */
    public function getAllThreads(array $category_ids, bool $get_deleted, bool $get_merged_threads, int $block_number, string $user_id): array {
        $block_size = 30;
        $qb = $this->_em->createQueryBuilder();
        $qb->select('t')
            ->from(Thread::class, 't')
            ->join('t.categories', 'c')
            ->join('t.viewers', 'v')
            ->join('t.posts', 'p')
            ->join('p.history', 'ph')
            ->leftJoin('t.favorers', 'sf', Expr\Join::WITH, 'sf.user_id = :user_id')
            ->setParameter(':user_id', $user_id);
        if (count($category_ids) > 0) {
            $qb->andWhere('c.category_id IN (:category_ids)')
                ->setParameter(':category_ids', $category_ids);
        }
        if (!$get_deleted) {
            $qb->andWhere('t.deleted = false');
        }
        if (!$get_merged_threads) {
            $qb->andWhere('t.merged_thread IS NULL');
        }
        $qb->addOrderBy('CASE WHEN t.pinned_expiration > CURRENT_TIMESTAMP() THEN 1 ELSE 0 END', 'DESC')
            ->addOrderBy('CASE WHEN sf.user_id IS NULL THEN 1 ELSE 0 END', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults($block_size)
            ->setFirstResult($block_size * $block_number);

        return $qb->getQuery()->getArrayResult();
    } 
}
