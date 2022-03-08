<?php


namespace app\entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class GitAuthToken
 * @package app\entities
 * @ORM\Entity(repositoryClass="\app\repositories\GitAuthTokenRepository")
 * @ORM\Table(name="auth_tokens")
 */
class GitAuthToken {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var integer
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $user_id;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $token;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $name;

    /**
     * @ORM\Column(type="datetimetz")
     * @var \DateTime
     */
    protected $expiration;

    public function __construct(string $user_id, string $token, string $name, ?string $expiration) {
        $this->user_id = $user_id;
        $this->token = $token;
        $this->name = $name;
        if ($expiration === null) {
            $this->expiration = null;
        }
        else {
            $this->expiration = new \DateTime($expiration);
        }
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

    public function isExpired(\DateTime $time): bool {
        if ($this->expiration === null) {
            return false;
        }
        return $time >= $this->expiration;
    }
}
