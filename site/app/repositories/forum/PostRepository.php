<?php

declare(strict_types=1);

namespace app\repositories\forum;

use app\entities\forum\Post;
use Doctrine\ORM\EntityRepository;

class PostRepository extends EntityRepository {
    public function getPostHistory(int $post_id): ?Post {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('p')
            ->from(Post::class, 'p')
            ->leftJoin('p.history', 'h')
            ->leftJoin('p.attachments', 'a')
            ->leftJoin('p.author', 'u')
            ->where('p.id = :post_id')
            ->setParameter('post_id', $post_id);

        return $qb->getQuery()->getSingleResult();
    }
}
