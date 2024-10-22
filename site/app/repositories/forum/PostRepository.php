<?php

declare(strict_types=1);

namespace app\repositories\forum;

use app\entities\forum\Post;
use Doctrine\ORM\EntityRepository;

class PostRepository extends EntityRepository {
    public function getPostHistory(int $post_id): ?Post {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('post')
            ->from(Post::class, 'post')
            ->addSelect('history')
            ->addSelect('attachments')
            ->addSelect('author')
            ->leftJoin('post.history', 'history')
            ->leftJoin('post.attachments', 'attachments')
            ->leftJoin('post.author', 'author')
            ->where('post.id = :post_id')
            ->setParameter('post_id', $post_id);

        return $qb->getQuery()->getSingleResult();
    }

    public function getPostDetail(int $post_id): ?Post {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('post')
            ->from(Post::class, 'post')
            ->addSelect('history')
            ->addSelect('attachments')
            ->addSelect('author')
            ->addSelect('thread')
            ->addSelect('threadPosts')
            ->addSelect('upduckers')
            ->leftJoin('post.history', 'history')
            ->leftJoin('post.attachments', 'attachments')
            ->leftJoin('post.author', 'author')
            ->leftJoin('post.thread', 'thread')
            ->leftJoin('thread.posts', 'threadPosts')
            ->leftJoin('post.upduckers', 'upduckers')
            // We need the first post of the thread, which is where threadPost.parent == -1
            ->where('post.id = :post_id OR threadPosts.parent = -1')
            ->setParameter('post_id', $post_id);

        return $qb->getQuery()->getSingleResult();
    }
}
