<?php

declare(strict_types=1);

namespace app\entities\chat;

use app\entities\UserEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "chatroom_participants")]
#[ORM\UniqueConstraint(name: "unique_participant", columns: ["chatroom_id", "user_id"])]
class ChatroomParticipant {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Chatroom::class, inversedBy: "participants")]
    #[ORM\JoinColumn(name: "chatroom_id", referencedColumnName: "id", nullable: false)]
    private Chatroom $chatroom;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "user_id", nullable: false)]
    private UserEntity $user;

    /**
     * Cryptographically random salt generated once per user per chatroom.
     * Stored permanently — never changes regardless of cookie clearing or re-login,
     * so the anonymous name base is always stable for a given user in a given room.
     */
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $anon_salt;

    /**
     * Snapshot of the chatroom's session_started_at at the time this anon name was assigned.
     * Used to detect when the session has been regenerated so the name should be re-derived.
     */
    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $session_snapshot = null;

    /**
     * The resolved anonymous name for the current session.
     * Null means it hasn't been assigned yet this session.
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $anon_name = null;

    public function __construct(Chatroom $chatroom, UserEntity $user) {
        $this->chatroom = $chatroom;
        $this->user = $user;
        // 64 hex chars of cryptographically secure random data
        $this->anon_salt = bin2hex(random_bytes(32));
    }

    public function getId(): int {
        return $this->id;
    }

    public function getChatroom(): Chatroom {
        return $this->chatroom;
    }

    public function getUser(): UserEntity {
        return $this->user;
    }

    public function getAnonSalt(): string {
        return $this->anon_salt;
    }

    public function getAnonName(): ?string {
        return $this->anon_name;
    }

    public function setAnonName(?string $name): void {
        $this->anon_name = $name;
    }

    public function getSessionSnapshot(): ?string {
        return $this->session_snapshot;
    }

    public function setSessionSnapshot(?string $snapshot): void {
        $this->session_snapshot = $snapshot;
    }

    public function clearAnonName(): void {
        $this->anon_name = null;
        $this->session_snapshot = null;
    }
}

