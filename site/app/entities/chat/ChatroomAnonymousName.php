<?php

declare(strict_types=1);

namespace app\entities\chat;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "chatroom_anonymous_names")]
class ChatroomAnonymousName {
    #[ORM\Id]
    #[ORM\Column(name: "chatroom_id", type: "integer")]
    private int $chatroomId;

    #[ORM\Id]
    #[ORM\Column(name: "user_id", type: "string")]
    private string $userId;

    #[ORM\Column(name: "display_name", type: "string", length: 50)]
    private string $displayName;

    #[ORM\Column(name: "created_at", type: "datetime")]
    private \DateTime $createdAt;

    public function __construct(int $chatroomId, string $userId, string $displayName) {
        $this->chatroomId = $chatroomId;
        $this->userId = $userId;
        $this->displayName = $displayName;
        $this->createdAt = new \DateTime();
    }

    public function getChatroomId(): int {
        return $this->chatroomId;
    }

    public function getUserId(): string {
        return $this->userId;
    }

    public function getDisplayName(): string {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void {
        $this->displayName = $displayName;
    }

    public function getCreatedAt(): \DateTime {
        return $this->createdAt;
    }
}
