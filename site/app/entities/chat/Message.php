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
    private int $id;

    #[ORM\ManyToOne(targetEntity: "Chatroom")]
    #[ORM\JoinColumn(name: "chatroom_id", referencedColumnName: "id")]
    private Chatroom $chatroom;

    #[ORM\Column(type: Types::STRING)]
    private string $userId;

    #[ORM\Column(type: Types::STRING)]
    private string $display_name;

    #[ORM\Column(type: Types::STRING)]
    private string $role;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $timestamp;

    public function __construct() {
        $this->setTimestamp(new \DateTime("now"));
    }

    public function getId(): int {
        return $this->id;
    }

    public function getUserId(): string {
        return $this->userId;
    }

    public function setUserId($userId): void {
        $this->userId = $userId;
    }

    public function getDisplayName(): string {
        return $this->display_name;
    }

    public function setDisplayName($displayName): void {
        $this->display_name = $displayName;
    }

    public function getRole(): string {
        return $this->role;
    }

    public function setRole($role): string {
        return $this->role = $role;
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

    public function setTimestamp(datetime $timestamp): void {
        $this->timestamp = $timestamp;
    }

    public function getChatroom(): Chatroom {
        return $this->chatroom;
    }

    public function setChatroom(Chatroom $chatroom): void {
        $this->chatroom = $chatroom;
    }
}
