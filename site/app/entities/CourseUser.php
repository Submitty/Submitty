<?php

declare(strict_types=1);

namespace app\entities;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use app\repositories\CourseUserRepository;

/**
 * Doctrine entity for Terms, not much used but used for other queries.
 * @package app\entities
 */
#[ORM\Entity(repositoryClass: CourseUserRepository::class)]
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
    protected string $user_group;

    #[ORM\Column(type: Types::STRING)]
    protected string $registration_section;

    #[ORM\Column(type: Types::STRING)]
    protected string $registration_type;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected string $manual_registration;

    #[ORM\Column(type: Types::STRING)]
    protected string $previous_registration_section;

    public function __construct(string $term, string $course, string $user_id, int $user_group, string $registration_section, string $registration_type, bool $manual_registration, string $previous_registration_section) {
        $this->term = $term;
        $this->course = $course;
        $this->user_group = $user_group;
        $this->registration_section = $registration_section;
        $this->registration_type = $registration_type;
        $this->manual_registration = $manual_registration;
        $this->previous_registration_section = $previous_registration_section;
    }
}
