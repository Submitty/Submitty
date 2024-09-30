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
    public function getAllThreads(array $category_ids, bool $get_deleted, bool $get_merged_threads, int $block_number): array {
        $block_size = 30;
        $qb = $this->_em->createQueryBuilder();
        $qb->select('t')
            ->from(Thread::class, 't')
        // AddSelect allows us to join server-side, reducing the number of database queries.
            ->addSelect('c')
            ->addSelect('v')
            ->addSelect('p')
            ->addSelect('ph')
            ->addSelect('sf')
            ->addSelect('u')
            ->join('t.categories', 'c')
            ->leftJoin('t.viewers', 'v')
            ->leftJoin('t.posts', 'p')
            ->leftJoin('p.history', 'ph')
            ->leftJoin('t.favorers', 'sf')
            ->join('t.author', 'u');
        if (count($category_ids) > 0) {
            $qb->andWhere('c.category_id IN (:category_ids)')
                ->setParameter(':category_ids', $category_ids);
        }
        if (!$get_deleted) {
            $qb->andWhere('t.deleted = false');
        }
        if (!$get_merged_threads) {
            $qb->andWhere('t.merged_thread = -1');
        }
        $qb->addOrderBy('CASE WHEN t.pinned_expiration > CURRENT_TIMESTAMP() THEN 1 ELSE 0 END', 'DESC')
            ->addOrderBy('CASE WHEN sf.user_id IS NULL THEN 1 ELSE 0 END', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults($block_size)
            ->setFirstResult($block_size * $block_number);

        return $qb->getQuery()->getResult();
    }

    
    public function getThreadDetail(int $thread_id): Thread {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('t')
            ->from(Thread::class, 't')
            ->addSelect('p')
            ->addSelect('pa')
            ->addSelect('ph')
            ->addSelect('u')
            ->join('t.posts', 'p')
            ->join('p.attachments', 'pa')
            ->join('p.history', 'ph')
            ->join('t.categories', 'c')
            ->join('t.author', 'u')
            ->andWhere('t.id = :thread_id')
            ->setParameter('thread_id', $thread_id);
        return $qb->getQuery()->getSingleResult();
    }
}
