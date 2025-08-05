<?php

declare(strict_types=1);

namespace app\entities;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for course users
 * @package app\entities
 */
#[ORM\Table(name: "courses_users")]
class CourseUser {
    #[ORM\Column(type: Types::STRING)]
    protected string $term;

    #[ORM\Column(type: Types::STRING)]
    protected string $course;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected string $user_id;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $user_group;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $registration_section;

    #[ORM\Column(type: Types::STRING)]
    protected string $registration_type;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $manual_registration;

    #[ORM\Column(type: Types::STRING)]
    protected string $previous_registration_section;

    public function __construct(string $term, string $course, string $user_id, int $user_group, string $registration_section, string $registration_type, bool $manual_registration, string $previous_registration_section = "") {
        $this->term = $term;
        $this->course = $course;
        $this->user_id = $user_id;
        $this->user_group = $user_group;
        $this->registration_section = $registration_section;
        $this->registration_type = $registration_type;
        $this->manual_registration = $manual_registration;
        $this->previous_registration_section = $previous_registration_section;
    }

    public function setUserGroup(int $user_group): void {
        $this->user_group = $user_group;
    }

    public function setRegistrationSection(?string $registration_section): void {
        $this->registration_section = $registration_section;
    }

    public function setRegistrationType(string $registration_type): void {
        $this->registration_type = $registration_type;
    }

    public function setManualRegistration(bool $manual_registration): void {
        $this->manual_registration = $manual_registration;
    }

    public function setPreviousRegistrationSection(string $previous_registration_section): void {
        $this->previous_registration_section = $previous_registration_section;
    }
}
