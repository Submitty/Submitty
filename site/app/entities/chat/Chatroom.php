<?php

declare(strict_types=1);

namespace app\entities\chat;

use app\entities\UserEntity;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "chatrooms")]
class Chatroom {
    #[ORM\Id]
    #[ORM\Column(name: "id", type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: "host_id", referencedColumnName: "user_id", nullable: false)]
    private UserEntity $host;

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

    public function __construct(UserEntity $host, string $title, string $description) {
        $this->host = $host;
        $this->setTitle($title);
        $this->setDescription($description);
        $this->messages = new ArrayCollection();
        $this->is_active = false;
        $this->allow_anon = true;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getHost(): UserEntity {
        return $this->host;
    }

    public function getHostId(): string {
        return $this->host->getId();
    }

    public function getHostName(): string {
        return $this->host->getDisplayFullName();
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

    public function toggleActiveStatus(): void {
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
