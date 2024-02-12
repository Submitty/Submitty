<?php

declare(strict_types=1);

namespace app\repositories\chat;

use app\entities\chat\Chatroom;
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

    public function findByChatroomId(string $chatroomId): ?Chatroom {
        $result = $this->createQueryBuilder('c')
                    ->where('c.id = :id')
                    ->setParameter('id', $chatroomId)
                    ->getQuery()
                    ->getResult();
        if (count($result) === 0) {
            return null;
        }
        return $result[0];
    }
}
