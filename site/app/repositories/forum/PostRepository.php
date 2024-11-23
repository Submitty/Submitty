<?php

declare(strict_types=1);

namespace app\repositories\forum;

use app\entities\forum\Post;
use Doctrine\ORM\EntityRepository;

class PostRepository extends EntityRepository {
    public function getPostWithHistory(int $post_id): ?Post {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('post')
            ->addSelect('history')
            ->addSelect('attachments')
            ->addSelect('author')
            ->addSelect('edit_author')
            ->from(Post::class, 'post')
            ->leftJoin('post.history', 'history')
            ->leftJoin('post.attachments', 'attachments')
            ->join('post.author', 'author')
            ->join('history.edit_author', 'edit_author')
            ->where('post.id = :post_id')
            ->setParameter('post_id', $post_id);

        return $qb->getQuery()->getSingleResult();
    }
}
