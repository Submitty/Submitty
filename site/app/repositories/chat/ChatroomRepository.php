<?php

declare(strict_types=1);

namespace app\repositories\chat;

use Doctrine\ORM\EntityRepository;

class ChatroomRepository extends EntityRepository {
    public function findAllChatroomsByHostId(string $hostId) {
        return $this->createQueryBuilder('c')
                    ->where('c.host_id = :hostId')
                    ->setParameter('hostId', $hostId)
                    ->getQuery()
                    ->getResult();
    }

    public function findAllActiveChatrooms() {
        return $this->createQueryBuilder('c')
                    ->where('c.isActive = :isActive')
                    ->setParameter('isActive', true)
                    ->getQuery()
                    ->getResult();
    }

    public function findAllInactiveChatrooms() {
        return $this->createQueryBuilder('c')
                    ->where('c.isActive = :isActive')
                    ->setParameter('isActive', false)
                    ->getQuery()
                    ->getResult();
    }
}
