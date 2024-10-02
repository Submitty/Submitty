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
     * @param int[] $status
     * @return Thread[]
     */
    public function getAllThreads(array $category_ids, array $status, bool $get_deleted, bool $get_merged_threads, bool $filter_unread, string $user_id, int $block_number): array {
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
                ->setParameter('category_ids', $category_ids);
        }
        if (count($status) > 0) {
            $qb->andWhere('t.status IN (:status)')
                ->setParameter('status', $status);
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

        $result = $qb->getQuery()->getResult();

        if ($filter_unread) {
            $result = array_filter($result, function ($x) use ($user_id) {
                return $x->isUnread($user_id);
            });
        }
        return $result;
    }

    
    public function getThreadDetail(int $thread_id, string $order_posts_by = 'tree'): Thread {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('t')
            ->from(Thread::class, 't')
            ->addSelect('p')
            ->addSelect('pa')
            ->addSelect('ph')
            ->addSelect('u')
            ->addSelect('pu')
            ->addSelect('ud')
            ->leftJoin('t.posts', 'p')
            ->leftJoin('p.attachments', 'pa')
            ->leftJoin('p.history', 'ph')
            ->join('t.categories', 'c')
            ->join('t.author', 'u')
            ->leftJoin('p.author', 'pu')
            ->leftJoin('p.upduckers', 'ud')
            ->andWhere('t.id = :thread_id')
            ->setParameter('thread_id', $thread_id);
        
        switch ($order_posts_by) {
            case 'alpha':
                $qb->addOrderBy('pu.user_familyname', 'ASC')
                    ->addOrderBy('p.timestamp', 'ASC')
                    ->addOrderBy('p.id', 'ASC');
                break;
            case 'alpha_by_registration':
                $qb->addOrderBy('pu.registration_section', 'ASC')
                    ->addSelect('COALESCE(pu.user_preferred_familyname, pu.user_familyname) AS HIDDEN user_familyname_order')
                    ->addOrderBy('user_familyname_order', 'ASC');
                break;
            case 'alpha_by_':
                $qb->addOrderBy('pu.rotating_section', 'ASC')
                    ->addSelect('COALESCE(pu.user_preferred_familyname, pu.user_familyname) AS HIDDEN user_familyname_order')
                    ->addOrderBy('user_familyname_order', 'ASC');
                break;
            case 'reverse-time':
                $qb->addOrderBy('p.timestamp', 'DESC')
                    ->addOrderBy('p.id', 'ASC');
                break;
            default:
                $qb->addOrderBy('p.timestamp', 'ASC')
                    ->addOrderBy('p.id', 'ASC');
                break;
        }
        
        return $qb->getQuery()->getSingleResult();
    }

    /**
     * @return Thread[]
     */
    public function getMergeThreadOptions(Thread $thread): array {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('t')
            ->from(Thread::class, 't')
            ->addSelect('p')
            ->leftJoin('t.posts', 'p')
            ->where('p.timestamp < :timestamp')
            ->setParameter('timestamp', $thread->getFirstPost()->getTimestamp())
            ->andWhere('t.merged_thread IS NULL');
        return $qb->getQuery()->getResult();
    }
}
