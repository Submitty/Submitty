<?php

namespace app\entities;

use app\libraries\DateUtils;
use app\repositories\VcsAuthTokenRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class GitAuthToken
 * @package app\entities
 */
#[ORM\Entity(repositoryClass: VcsAuthTokenRepository::class)]
#[ORM\Table(name: "vcs_auth_tokens")]
class VcsAuthToken {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    protected int $id;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_id;

    #[ORM\Column(type: Types::STRING)]
    protected string $token;

    #[ORM\Column(type: Types::STRING)]
    protected string $name;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true)]
    protected ?DateTime $expiration;

    public function __construct(string $user_id, string $token, string $name, ?DateTime $expiration) {
        $this->user_id = $user_id;
        $this->token = $token;
        $this->name = $name;
        $this->expiration = $expiration;
    }

    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getToken(): string {
        return $this->token;
    }

    /**
     * @return \DateTime
     */
    public function getExpiration(): ?\DateTime {
        return $this->expiration;
    }

    /**
     * @return string
     */
    public function getUserId(): string {
        return $this->user_id;
    }

    public function isExpired(): bool {
        if ($this->expiration === null) {
            return false;
        }
        return DateUtils::getDateTimeNow() >= $this->expiration;
    }
}
