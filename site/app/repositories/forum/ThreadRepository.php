<?php

declare(strict_types=1);

namespace app\repositories\forum;

use app\entities\forum\Thread;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;

class ThreadRepository extends EntityRepository {
    /**
     * Queries table flatly so we can get a unique and consistent block.
     * @param int $block_number >= 0
     * @return int[] thread ids in the block
     */
    private function getThreadBlock(string $user_id, int $block_number): array {
        $block_size = 30;
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('thread')
            ->from(Thread::class, 'thread')
            ->leftJoin('thread.favorers', 'favorers', Join::WITH, 'favorers.user_id = :user_id')
            ->setParameter(':user_id', $user_id)
            ->addOrderBy('CASE WHEN thread.pinned_expiration > CURRENT_TIMESTAMP() THEN 1 ELSE 0 END', 'DESC')
            ->addOrderBy('CASE WHEN favorers.user_id IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('thread.id', 'DESC')
            ->setMaxResults($block_size)
            ->setFirstResult($block_size * $block_number);

        $result = $qb->getQuery()->getResult();
        return array_map(function ($x) {
            return $x->getId();
        }, $result);
    }
    /**
     * @param int[] $category_ids
     * @param int[] $status
     * @return Thread[]
     */
    public function getAllThreads(array $category_ids, array $status, bool $get_deleted, bool $get_merged_threads, bool $filter_unread, string $user_id, int &$block_number, bool $scroll_down = true): array {
        if ($block_number < 0) {
            return [];
        }
        $block = $this->getThreadBlock($user_id, $block_number);

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('thread')
            ->from(Thread::class, 'thread')
        // AddSelect allows us to join server-side, reducing the number of database queries.
            ->addSelect('categories')
            ->addSelect('viewers')
            ->addSelect('post')
            ->addSelect('postHistory')
            ->addSelect('favorers')
            ->addSelect('author')
            ->addSelect('postUpducker')
            ->join('thread.categories', 'categories')
            ->leftJoin('thread.viewers', 'viewers', Join::WITH, 'viewers.user_id = :user_id')
            ->leftJoin('thread.posts', 'post')
            ->leftJoin('post.history', 'postHistory')
            ->leftJoin('post.upduckers', 'postUpducker')
            ->leftJoin('thread.favorers', 'favorers', Join::WITH, 'favorers.user_id = :user_id')
            ->setParameter('user_id', $user_id)
            ->join('thread.author', 'author')
            ->andWhere('thread.id IN (:block)')
            ->setParameter(':block', $block);
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
            ->addOrderBy('CASE WHEN favorers.user_id IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('thread.id', 'DESC');

        $result = $qb->getQuery()->getResult();

        // only get unread
        if ($filter_unread) {
            $result = array_filter($result, function ($x) use ($user_id) {
                return $x->isUnread($user_id);
            });
        }

        // if we filtered out all threads in this block, and the block was not empty,
        // recursively fetch the next block until we find a non-empty block or run out of blocks.
        if (count($result) === 0 && count($block) !== 0) {
            $block_number += $scroll_down ? 1 : -1;
            $result = $this->getAllThreads($category_ids, $status, $get_deleted, $get_merged_threads, $filter_unread, $user_id, $block_number);
        }
        return $result;
    }


    public function getThreadDetail(int $thread_id, string $order_posts_by = 'tree', bool $get_deleted = false): ?Thread {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('thread')
            ->from(Thread::class, 'thread')
            ->addSelect('post')
            ->addSelect('postAttachments')
            ->addSelect('postHistory')
            ->addSelect('threadAuthor')
            ->addSelect('postAuthor')
            ->addSelect('postUpducker')
            ->addSelect('postChildren')
            ->leftJoin('thread.posts', 'post')
            ->leftJoin('post.attachments', 'postAttachments')
            ->leftJoin('post.history', 'postHistory')
            ->join('thread.author', 'threadAuthor')
            ->leftJoin('post.author', 'postAuthor')
            ->leftJoin('post.upduckers', 'postUpducker')
            ->andWhere('thread.id = :thread_id')
            ->setParameter('thread_id', $thread_id);

        // sticking this join above and then adding a 'WHERE postChildren.deleted = false' clause would be buggy.
        // if a thread has all of its child posts deleted, that would return no rows and throw an error. Hence this implementation.
        if ($get_deleted) {
            $qb->leftJoin('post.children', 'postChildren');
        }
        else {
            $qb->leftJoin('post.children', 'postChildren', Join::WITH, 'postChildren.deleted = false')
                ->andWhere('post.deleted = false OR post.deleted IS NULL');
        }
        switch ($order_posts_by) {
            case 'alpha':
                $qb->addSelect("COALESCE(postAuthor.user_preferred_familyname, postAuthor.user_familyname) AS HIDDEN user_familyname_order")
                    ->addOrderBy('user_familyname_order', 'ASC')
                    ->addOrderBy('post.timestamp', 'ASC')
                    ->addOrderBy('post.id', 'ASC');
                break;
            case 'alpha_by_registration':
                $qb->addOrderBy('postAuthor.registration_section', 'ASC')
                    ->addSelect("COALESCE(postAuthor.user_preferred_familyname, postAuthor.user_familyname) AS HIDDEN user_familyname_order")
                    ->addOrderBy('user_familyname_order', 'ASC');
                break;
            case 'alpha_by_rotating':
                $qb->addOrderBy('postAuthor.rotating_section', 'ASC')
                    ->addSelect("COALESCE(postAuthor.user_preferred_familyname, postAuthor.user_familyname) AS HIDDEN user_familyname_order")
                    ->addOrderBy('user_familyname_order', 'ASC');
                break;
            case 'reverse-time':
                $qb->addOrderBy('post.timestamp', 'DESC')
                    ->addOrderBy('post.id', 'ASC')
                    ->addOrderBy('postChildren.id', 'ASC');
                break;
            default:
                $qb->addOrderBy('post.timestamp', 'ASC')
                    ->addOrderBy('post.id', 'ASC')
                    ->addOrderBy('postChildren.id', 'ASC');
                break;
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return Thread[]
     */
    public function getMergeThreadOptions(Thread $thread): array {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('thread')
            ->from(Thread::class, 'thread')
            ->addSelect('post')
            ->leftJoin('thread.posts', 'post')
            ->where('post.timestamp < :timestamp')
            ->setParameter('timestamp', $thread->getFirstPost()->getTimestamp())
            ->andWhere('thread.merged_thread = -1')
            ->andWhere('thread.deleted = false');
        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $thread_ids
     * @param string[] $user_ids
     * @return Thread[]
     */
    public function getThreadsForGrading(array $thread_ids, array $user_ids): array {
        if (count($thread_ids) === 0 || count($user_ids) === 0) {
            return [];
        }
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('thread')
            ->from(Thread::class, 'thread')
            ->addSelect('post')
            ->addSelect('threadAuthor')
            ->addSelect('postAuthor')
            ->addSelect('postHistory')
            ->addSelect('postAttachment')
            ->addSelect('postUpducker')
            ->leftJoin('thread.posts', 'post', Join::WITH, 'post.author IN (:user_ids)')
            ->join('thread.author', 'threadAuthor')
            ->leftJoin('post.author', 'postAuthor')
            ->leftJoin('post.history', 'postHistory')
            ->leftJoin('post.attachments', 'postAttachment')
            ->leftJoin('post.upduckers', 'postUpducker')
            ->andWhere('thread.id IN (:thread_ids)')
            ->setParameter('thread_ids', $thread_ids)
            ->setParameter('user_ids', $user_ids)
            ->addOrderBy('thread.id', 'ASC')
            ->addOrderBy('postAuthor.user_id', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
