<?php

namespace app\entities\email;

use app\repositories\email\EmailRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailRepository::class)]
#[ORM\Table(name: "emails")]
class EmailEntity {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected int $id;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_id;

    #[ORM\Column(type: Types::TEXT)]
    protected string $subject;

    #[ORM\Column(type: Types::TEXT)]
    protected string $body;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    protected DateTime $created;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?DateTime $sent;

    #[ORM\Column(type: Types::STRING)]
    protected string $error;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $email_address;

    #[ORM\Column(type: Types::STRING)]
    protected string $term;

    #[ORM\Column(type: Types::STRING)]
    protected string $course;

    public function getId(): int {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUserId(): string {
        return $this->user_id;
    }

    public function getSubject(): string {
        return $this->subject;
    }

    public function getBody(): string {
        return $this->body;
    }

    public function getCreated(): \DateTime {
        return $this->created;
    }

    public function getSent(): ?\DateTime {
        return $this->sent;
    }

    public function getError(): ?string {
        return $this->error;
    }

    public function getEmailAddress(): string {
        return $this->email_address;
    }

    public function getTerm(): ?string {
        return $this->term;
    }

    public function getCourse(): ?string {
        return $this->course;
    }
}
