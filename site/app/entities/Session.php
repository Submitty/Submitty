<?php

declare(strict_types=1);

namespace app\entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Session
 * @package app\entities
 * @ORM\Entity(repositoryClass="\app\repositories\SessionRepository")
 * @ORM\Table(name="sessions")
 */
class Session {
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @var string
     */
    private $session_id;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $user_id;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $csrf_token;

    /**
     * @ORM\Column(type="datetimetz")
     * @var \DateTime
     * @phpstan-ignore-next-line
     */
    private $session_expires;

    /**
     * @ORM\Column(type="datetimetz")
     * @var \DateTime | null
     */
    private $session_created;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $browser_name;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $browser_version;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $platform;

    public function __construct(string $session_id, string $user_id, string $csrf_token, \DateTime $session_expires, \DateTime $session_created, array $user_agent) {
        $this->session_id = $session_id;
        $this->user_id = $user_id;
        $this->csrf_token = $csrf_token;
        $this->session_expires = $session_expires;
        $this->session_created = $session_created;
        $this->browser_name = $user_agent['browser'];
        $this->browser_version = $user_agent['version'];
        $this->platform = $user_agent['platform'];
    }

    public function getSessionId(): string {
        return $this->session_id;
    }

    public function getUserId(): string {
        return $this->user_id;
    }

    public function getCsrfToken(): string {
        return $this->csrf_token;
    }

    public function getSessionCreated(): ?\DateTime {
        return $this->session_created;
    }

    public function getBrowserName(): string {
        return $this->browser_name;
    }

    public function getBrowserVersion(): string {
        return $this->browser_version;
    }

    public function getPlatform(): string {
        return $this->platform;
    }

    public function isCurrent(string $current_session_id): bool {
        return $this->session_id === $current_session_id;
    }

    public function updateSessionExpiration(\DateTime $current_dt) {
        $this->session_expires = $current_dt->add(\DateInterval::createFromDateString('2 weeks'));
    }
}
