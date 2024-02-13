<?php

declare(strict_types=1);

namespace app\entities\chat;

use app\repositories\chat\ChatroomRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatroomRepository::class)]
#[ORM\Table(name: "chatrooms")]
class Chatroom {

    #[ORM\Id]
    #[ORM\Column(name: "id", type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected int $id;

    #[ORM\Column(type: Types::STRING)]
    protected string $host_id;

    #[ORM\Column(name: "title", type: Types::STRING)]
    protected string $title;

    #[ORM\Column(name: "description", type: Types::STRING)]
    protected string $description;

    #[ORM\Column(name: "is_active", type: Types::BOOLEAN)]
    protected bool $isActive;

    /**
     * @var Collection<Message>
     */
    #[ORM\OneToMany(mappedBy: "chat", targetEntity: Message::class, cascade: ["remove"])]
    protected Collection $messages;

    public function __construct() {

        $this->messages = new ArrayCollection();
        $this->isActive = true;
    }

    public function getId(): int {
        return $this->id;
    }

    public function setHostId($hostId): void {
        $this->host_id = $hostId;
    }

    public function getHostId(): string {
        return $this->host_id;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function setTitle(string $title): void {
        $this->title = $title;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function setDescription($description): void {
        $this->description = $description;
    }

    public function getStatus(): bool {
        return $this->isActive;
    }

    public function activate(): void {
        $this->isActive = True;
    }

    public function deactivate(): void {
        $this->isActive = False;
    }

    public function getMessages(): Collection {
        return $this->messages;
    }

    public function addMessage(Message $message): void {
        $this->messages->add($message);
    }
}