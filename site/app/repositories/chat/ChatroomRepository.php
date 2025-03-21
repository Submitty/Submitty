<?php

declare(strict_types=1);

namespace app\repositories\chat;

use Doctrine\ORM\EntityRepository;

class ChatroomRepository extends EntityRepository {
    /**
     * @return array<Chatroom>
     */
    public function findAllChatroomsByHostId(string $hostId): array {
        return $this->createQueryBuilder('c')
                    ->where('c.host_id = :hostId')
                    ->setParameter('hostId', $hostId)
                    ->getQuery()
                    ->getResult();
    }

    /**
     * @return array<Chatroom>
     */
    public function findAllActiveChatrooms(): array {
        return $this->createQueryBuilder('c')
                    ->where('c.isActive = :isActive')
                    ->setParameter('isActive', true)
                    ->getQuery()
                    ->getResult();
    }

    /**
     * @return array<Chatroom>
     */
    public function findAllInactiveChatrooms(): array {
        return $this->createQueryBuilder('c')
                    ->where('c.isActive = :isActive')
                    ->setParameter('isActive', false)
                    ->getQuery()
                    ->getResult();
    }
}
