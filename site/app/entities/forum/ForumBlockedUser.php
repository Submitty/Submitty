<?php

namespace app\entities\forum;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use app\repositories\forum\ForumBlockedUserRepository;

#[ORM\Entity(repositoryClass: ForumBlockedUserRepository::class)]
#[ORM\Table(name: "forum_blocked_user")]
class ForumBlockedUser {
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $id;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_id;

    #[ORM\Column(type: Types::STRING, enumType: ForumBlockAction::class)]
    protected ForumBlockAction $action;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true)]
    protected ?DateTime $expiration_date;

    #[ORM\Column(type: Types::STRING)]
    protected string $created_by;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $created_at;

    public function __construct(string $user_id, ForumBlockAction $action, ?DateTime $expiration_date, string $created_by) {
        $this->user_id = $user_id;
        $this->action = $action;
        $this->expiration_date = $expiration_date;
        $this->created_by = $created_by;
        $this->created_at = new DateTime();
    }

    public function getId(): int {
        return $this->id;
    }

    public function getUserId(): string {
        return $this->user_id;
    }

    public function getAction(): ForumBlockAction {
        return $this->action;
    }

    public function getExpirationDate(): ?DateTime {
        return $this->expiration_date;
    }

    public function setExpirationDate(?DateTime $expiration_date): void {
        $this->expiration_date = $expiration_date;
    }

    public function getCreatedBy(): string {
        return $this->created_by;
    }

    public function getCreatedAt(): DateTime {
        return $this->created_at;
    }

    public function isActive(): bool {
        return $this->expiration_date === null || $this->expiration_date > new DateTime();
    }
}
