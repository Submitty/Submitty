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
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $user_id;

    #[ORM\Column(type: Types::TEXT)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $created;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $sent;

    #[ORM\Column(type: Types::STRING)]
    private string $error;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email_address;

    #[ORM\Column(type: Types::STRING)]
    private string $semester;

    #[ORM\Column(type: Types::STRING)]
    private string $course;

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

    public function getCreated(): DateTime {
        return $this->created;
    }

    public function getSent(): ?DateTime {
        return $this->sent;
    }

    public function getError(): ?string {
        return $this->error;
    }

    public function getEmailAddress(): string {
        return $this->email_address;
    }

    public function getSemester(): ?string {
        return $this->semester;
    }

    public function getCourse(): ?string {
        return $this->course;
    }
}
