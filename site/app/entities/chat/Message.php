<?php

declare(strict_types=1);

namespace app\entities\chat;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "chatroom_messages")]
class Message {

    #[ORM\Id]
    #[ORM\Column(name: "id", type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private $id;

    #[ORM\ManyToOne(targetEntity: "Chatroom")]
    #[ORM\JoinColumn(name: "chatroom_id", referencedColumnName: "id")]
    private $chatroom;
    #[ORM\Column(name: "chatroom_id", type: Types::INTEGER)]
    private $chatroom_id;

    #[ORM\Column(type: Types::STRING)]
    private string $user_id;

    #[ORM\Column(type: Types::TEXT)]
    private $content;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private $timestamp;

    public function __construct() {
        $this->timestamp = new \DateTime();
    }

    public function getId(): int {
        return $this->id;
    }

    public function getUserId(): string {
        return $this->user_id;
    }

    public function setUserId($userId): void {
        $this->user_id = $userId;
    }

    public function getContent(): string {
        return $this->content;
    }

    public function setContent(string $text): void {
        $this->content = $text;
    }

    public function getTimestamp(): \DateTimeImmutable {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeImmutable $timestamp): void {
        $this->timestamp = $timestamp;
    }

    public function getChatroomId(): int {
        return $this->chatroom_id;
    }

    public function setChatroomId(int $chatroom_id): void {
        $this->chatroom_id = $chatroom_id;
    }

    public function getChatroom(): Chatroom {
        return $this->chatroom;
    }

    public function setChatroom(Chatroom $chatroom): void {
        $this->chatroom = $chatroom;
    }
}
