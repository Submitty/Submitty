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

    #[ORM\ManyToOne(targetEntity: UserEntity::class, fetch: "EAGER")]
    #[ORM\JoinColumn(name: "host_id", referencedColumnName: "user_id", nullable: false)]
    private UserEntity $host;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\Column(type: Types::STRING)]
    private string $description;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $is_active;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $is_deleted;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $allow_anon;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $allow_read_only_after_end = false;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true)]
    private ?\DateTime $session_started_at = null;

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
        $this->is_deleted = false;
        $this->allow_read_only_after_end = false;
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

    public function deleteChat(): void {
        $this->is_deleted = true;
    }

    public function chatDeleted(): bool {
        return $this->is_deleted;
    }

    /**
     * Format: AdjectiveAnimalNumber (e.g., "SwiftPanda123")
     * 
     * @param \app\libraries\Core $core Core instance for config access
     * @param string $user_id User ID
     * @return string Anonymous name
     */
    public function calcAnonName(\app\libraries\Core $core, string $user_id): string {
        // Use multiple entropy sources for security
        $session_started = $this->getSessionStartedAt();
        $session_timestamp = $session_started ? $session_started->getTimestamp() : 0;
        
        // This makes it difficult to reverse-engineer while remaining deterministic
        $hash_input = sprintf(
            '%s:%d:%s:%d:%s',
            $user_id,
            $this->getId(),
            $this->getHostId(),
            $session_timestamp,
            $core->getConfig()->getSecretSession() // Use Submitty's session secret
        );
        
        $hash = hash('sha256', $hash_input);
        
        // Convert hash to name components
        $adjectives = [
            'Swift', 'Brave', 'Clever', 'Wise', 'Bold', 'Calm', 'Bright', 'Quick',
            'Silent', 'Fierce', 'Gentle', 'Noble', 'Proud', 'Sharp', 'Strong', 'Keen',
            'Loyal', 'Steady', 'Witty', 'Daring', 'Graceful', 'Humble', 'Mighty', 'Nimble',
            'Patient', 'Radiant', 'Serene', 'Vigilant', 'Zesty', 'Agile', 'Curious', 'Eager',
            'Fearless', 'Happy', 'Jolly', 'Kind', 'Lively', 'Merry', 'Peaceful', 'Cheerful'
        ];
        
        $animals = [
            'Panda', 'Eagle', 'Dolphin', 'Wolf', 'Fox', 'Tiger', 'Bear', 'Hawk',
            'Owl', 'Raven', 'Falcon', 'Lynx', 'Otter', 'Deer', 'Moose', 'Bison',
            'Cheetah', 'Jaguar', 'Leopard', 'Panther', 'Lion', 'Cougar', 'Dragon', 'Phoenix',
            'Penguin', 'Turtle', 'Seal', 'Walrus', 'Badger', 'Ferret', 'Mink', 'Raccoon',
            'Squirrel', 'Rabbit', 'Hare', 'Vixen', 'Stag', 'Buck', 'Mustang', 'Stallion'
        ];
        
        // Use hash bytes to select components
        $hash_int = hexdec(substr($hash, 0, 8));
        
        $adjective_idx = $hash_int % count($adjectives);
        $animal_idx = (int)(($hash_int / count($adjectives)) % count($animals));
        $number = 100 + ($hash_int % 900); // 100-999
        
        return "{$adjectives[$adjective_idx]}{$animals[$animal_idx]}{$number}";
    }

    public function getSessionStartedAt(): ?\DateTime {
        return $this->session_started_at;
    }

    public function setSessionStartedAt(?\DateTime $session_started_at): void {
        $this->session_started_at = $session_started_at;
    }

    public function allowReadOnlyAfterEnd(): bool {
        return $this->allow_read_only_after_end;
    }

    public function setAllowReadOnlyAfterEnd(bool $allow): void {
        $this->allow_read_only_after_end = $allow;
    }

    public function isReadOnly(): bool {
        return !$this->isActive() && $this->allowReadOnlyAfterEnd();
    }
}
