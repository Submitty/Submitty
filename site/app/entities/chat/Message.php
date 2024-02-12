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

    #[ORM\ManyToOne(targetEntity: Chatroom::class, inversedBy: "messages")]
    #[ORM\JoinColumn(name: "chatroom_id", referencedColumnName: "id")]
    private $chatroom;

    #[ORM\Column(type: Types::TEXT)]
    private $content;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private $timestamp;

    public function __construct() {
        $this->timestamp = new \DateTime();
    }

    public function getId(): int {
        return $this->id;
    }

    public function getContent(): string {
        return $this->content;
    }

    public function setContent(string $text): void {
        $this->content = $text;
    }

    public function getTimestamp(): \DateTime {
        return $this->timestamp;
    }

    public function getChatroom(): Chatroom {
        return $this->chatroom;
    }

    public function setChatroom(Chatroom $chatroom): self {
        $this->chatroom = $chatroom;
        return $this;
    }
}
