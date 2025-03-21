<?php

declare(strict_types=1);

namespace app\entities\chat;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "chatroom_messages")]
class Message {
    #[ORM\Id]
    #[ORM\Column(name: "id", type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Chatroom::class)]
    #[ORM\JoinColumn(name: "chatroom_id", referencedColumnName: "id")]
    private Chatroom $chatroom;

    #[ORM\Column(type: Types::STRING)]
    private string $user_id;

    #[ORM\Column(type: Types::STRING)]
    private string $display_name;

    #[ORM\Column(type: Types::STRING)]
    private string $role;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    private DateTime $timestamp;

    public function __construct(string $userId, string $displayName, string $role, string $text, Chatroom $chatroom) {
        $this->setUserId($userId);
        $this->setDisplayName($displayName);
        $this->setRole($role);
        $this->setTimestamp(new \DateTime("now"));
        $this->setContent($text);
        $this->setChatroom($chatroom);
    }

    public function getId(): int {
        return $this->id;
    }

    public function getUserId(): string {
        return $this->user_id;
    }

    public function setUserId(string $userId): void {
        $this->user_id = $userId;
    }

    public function getDisplayName(): string {
        return $this->display_name;
    }

    public function setDisplayName(string $displayName): void {
        $this->display_name = $displayName;
    }

    public function getRole(): string {
        return $this->role;
    }

    public function setRole(string $role): void {
        $this->role = $role;
    }

    public function getContent(): string {
        return $this->content;
    }

    public function setContent(string $text): void {
        $this->content = $text;
    }

    public function getTimestamp(): DateTime {
        return $this->timestamp;
    }

    public function setTimestamp(DateTime $timestamp): void {
        $this->timestamp = $timestamp;
    }

    public function getChatroom(): Chatroom {
        return $this->chatroom;
    }

    public function setChatroom(Chatroom $chatroom): void {
        $this->chatroom = $chatroom;
    }
}
