<?php

declare(strict_types=1);

namespace app\repositories\forum;

use app\entities\forum\Thread;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;

class ThreadRepository extends EntityRepository {
    /**
     * @param int[] $category_ids
     * @param int[] $status
     * @return Thread[]
     */
    public function getAllThreads(array $category_ids, array $status, bool $get_deleted, bool $get_merged_threads, bool $filter_unread, string $user_id, int $block_number): array {
        $block_size = 30;
        $qb = $this->_em->createQueryBuilder();
        $qb->select('thread')
            ->from(Thread::class, 'thread')
        // AddSelect allows us to join server-side, reducing the number of database queries.
            ->addSelect('categories')
            ->addSelect('viewers')
            ->addSelect('post')
            ->addSelect('postHistory')
            ->addSelect('favorers')
            ->addSelect('author')
            ->join('thread.categories', 'categories')
            ->leftJoin('thread.viewers', 'viewers')
            ->leftJoin('thread.posts', 'post')
            ->leftJoin('post.history', 'postHistory')
            ->leftJoin('thread.favorers', 'favorers', Join::WITH, 'favorers.user_id = :user_id')
            ->setParameter('user_id', $user_id)
            ->join('thread.author', 'author');
        // If given any categories, filter out posts lacking at least one of them.
        if (count($category_ids) > 0) {
            $qb->andWhere('categories.category_id IN (:category_ids)')
                ->setParameter('category_ids', $category_ids);
        }
        // If given any status (resolved/unresolved), filter
        if (count($status) > 0) {
            $qb->andWhere('thread.status IN (:status)')
                ->setParameter('status', $status);
        }
        if (!$get_deleted) {
            $qb->andWhere('thread.deleted = false');
        }
        if (!$get_merged_threads) {
            $qb->andWhere('thread.merged_thread = -1');
        }
        $qb->addOrderBy('CASE WHEN thread.pinned_expiration > CURRENT_TIMESTAMP() THEN 1 ELSE 0 END', 'DESC')
            ->addOrderBy('CASE WHEN favorers.user_id IS NULL THEN 1 ELSE 0 END', 'DESC')
            ->addOrderBy('thread.id', 'DESC')
            ->setMaxResults($block_size)
            ->setFirstResult($block_size * $block_number);

        $result = $qb->getQuery()->getResult();

        // only get unread
        if ($filter_unread) {
            $result = array_filter($result, function ($x) use ($user_id) {
                return $x->isUnread($user_id);
            });
        }
        return $result;
    }


    public function getThreadDetail(int $thread_id, string $order_posts_by = 'tree'): Thread {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('thread')
            ->from(Thread::class, 'thread')
            ->addSelect('post')
            ->addSelect('postAttachments')
            ->addSelect('postHistory')
            ->addSelect('threadAuthor')
            ->addSelect('postAuthor')
            ->addSelect('postUpducker')
            ->leftJoin('thread.posts', 'post')
            ->leftJoin('post.attachments', 'postAttachments')
            ->leftJoin('post.history', 'postHistory')
            ->join('thread.author', 'threadAuthor')
            ->leftJoin('post.author', 'postAuthor')
            ->leftJoin('post.upduckers', 'postUpducker')
            ->andWhere('thread.id = :thread_id')
            ->setParameter('thread_id', $thread_id);

        switch ($order_posts_by) {
            case 'alpha':
                $qb->addOrderBy('postAuthor.user_familyname', 'ASC')
                    ->addOrderBy('post.timestamp', 'ASC')
                    ->addOrderBy('post.id', 'ASC');
                break;
            case 'alpha_by_registration':
                $qb->addOrderBy('postAuthor.registration_section', 'ASC')
                    ->addSelect('COALESCE(postAuthor.user_preferred_familyname, postAuthor.user_familyname) AS HIDDEN user_familyname_order')
                    ->addOrderBy('user_familyname_order', 'ASC');
                break;
            case 'alpha_by_':
                $qb->addOrderBy('postAuthor.rotating_section', 'ASC')
                    ->addSelect('COALESCE(postAuthor.user_preferred_familyname, postAuthor.user_familyname) AS HIDDEN user_familyname_order')
                    ->addOrderBy('user_familyname_order', 'ASC');
                break;
            case 'reverse-time':
                $qb->addOrderBy('post.timestamp', 'DESC')
                    ->addOrderBy('post.id', 'ASC');
                break;
            default:
                $qb->addOrderBy('post.timestamp', 'ASC')
                    ->addOrderBy('post.id', 'ASC');
                break;
        }

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * @return Thread[]
     */
    public function getMergeThreadOptions(Thread $thread): array {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('thread')
            ->from(Thread::class, 'thread')
            ->addSelect('post')
            ->leftJoin('thread.posts', 'post')
            ->where('post.timestamp < :timestamp')
            ->setParameter('timestamp', $thread->getFirstPost()->getTimestamp())
            ->andWhere('thread.merged_thread IS NULL');
        return $qb->getQuery()->getResult();
    }
}
