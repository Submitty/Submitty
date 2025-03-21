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
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $host_id;

    #[ORM\Column(type: Types::STRING)]
    private string $host_name;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\Column(type: Types::STRING)]
    private string $description;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $is_active;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $allow_anon;

    /**
     * @var Collection<Message>
     */
    #[ORM\OneToMany(mappedBy: "chatroom", targetEntity: Message::class)]
    private Collection $messages;

    public function __construct(string $hostId, string $hostName, string $title, string $description) {
        $this->setHostId($hostId);
        $this->setHostName($hostName);
        $this->setTitle($title);
        $this->setDescription($description);
        $this->messages = new ArrayCollection();
        $this->is_active = false;
        $this->allow_anon = true;
    }

    public function getId(): int {
        return $this->id;
    }

    public function setHostId(string $hostId): void {
        $this->host_id = $hostId;
    }

    public function getHostId(): string {
        return $this->host_id;
    }

    public function setHostName(string $hostName): void {
        $this->host_name = $hostName;
    }

    public function getHostName(): string {
        return $this->host_name;
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

    public function setDescription(string $description): void {
        $this->description = $description;
    }

    public function toggle_on_off(): void {
        $this->is_active = !$this->is_active;
    }

    public function isActive(): bool {
        return $this->is_active;
    }

    public function isAllowAnon(): bool {
        return $this->allow_anon;
    }

    public function setAllowAnon(bool $allow_anon): void {
        $this->allow_anon = $allow_anon;
    }

    public function getMessages(): Collection {
        return $this->messages;
    }

    public function addMessage(Message|null $message): void {
        $this->messages->add($message);
    }
}
