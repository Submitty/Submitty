<?php

declare(strict_types=1);

namespace app\repositories\forum;

use app\entities\forum\ForumBlockAction;
use app\entities\forum\ForumBlockedUser;
use DateTime;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ForumBlockedUser>
 */
class ForumBlockedUserRepository extends EntityRepository {
    public function isUserBlockedFromForumPosts(string $user_id): bool {
        $block = $this->findOneBy(['user_id' => $user_id, 'action' => ForumBlockAction::NoForumPosts]);
        return $block !== null && $block->isActive();
    }

    /**
     * @return ForumBlockedUser[]
     */
    public function getActiveBlockedUsers(?string $user_id = null): array {
        $qb = $this->createQueryBuilder('b');
        $qb->where('(b.expiration_date IS NULL OR b.expiration_date > :now)')
            ->setParameter('now', new DateTime())
            ->orderBy('b.created_at', 'DESC');

        if ($user_id !== null) {
            $qb->andWhere('b.user_id = :user_id')
                ->setParameter('user_id', $user_id);
        }

        return $qb->getQuery()->getResult();
    }

    public function addBlockedUser(string $user_id, ForumBlockAction $action, ?DateTime $expiration_date, string $created_by): void {
        $em = $this->getEntityManager();
        $existing = $this->findOneBy(['user_id' => $user_id, 'action' => $action]);

        if ($existing !== null) {
            $existing->setExpirationDate($expiration_date);
        }
        else {
            $block = new ForumBlockedUser($user_id, $action, $expiration_date, $created_by);
            $em->persist($block);
        }

        $em->flush();
    }

    public function updateBlockedUser(int $id, ?DateTime $expiration_date): void {
        $em = $this->getEntityManager();
        $block = $this->find($id);
        if ($block !== null) {
            $block->setExpirationDate($expiration_date);
            $em->flush();
        }
    }

    public function deleteBlockedUser(int $id): void {
        $em = $this->getEntityManager();
        $block = $this->find($id);
        if ($block !== null) {
            $em->remove($block);
            $em->flush();
        }
    }

    /**
     * @param string[] $author_ids
     * @return string[] the subset of $author_ids who are blocked from forum posts
     */
    public function getUsersBlockedFromForumPosts(array $author_ids): array {
        if ($author_ids === []) {
            return [];
        }

        $blocked = $this->findBy(['user_id' => $author_ids, 'action' => ForumBlockAction::NoForumPosts]);

        $now = new DateTime();
        $blocked_user_ids = [];

        foreach ($blocked as $block) {
            if ($block->getExpirationDate() === null || $block->getExpirationDate() > $now) {
                $blocked_user_ids[] = $block->getUserId();
            }
        }

        return $blocked_user_ids;
    }
}
