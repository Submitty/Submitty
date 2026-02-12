<?php

declare(strict_types=1);

namespace app\entities;

use app\entities\UserEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for course users
 * @package app\entities
 */
#[ORM\Entity]
#[ORM\Table(name: "courses_users")]
class CourseUser {

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected string $term;
    
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected string $course;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: UserEntity::class, inversedBy: 'courseUsers')]
    #[ORM\JoinColumn(
        name: 'user_id',           // The column in courses_users
        referencedColumnName: 'user_id', // The column in users
        nullable: false
    )]
    protected UserEntity $user;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $user_group;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $registration_section;

    #[ORM\Column(type: Types::STRING)]
    protected string $registration_type;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $manual_registration;

    #[ORM\Column(type: Types::STRING)]
    protected string $previous_registration_section = '';

    public function __construct(string $term, string $course, UserEntity $user) {
        $this->term = $term;
        $this->course = $course;
        $this->user = $user;
        $this->user_group = $user->getGroup();
        $this->registration_section = $user->getRegistrationSection();
        $this->registration_type = $user->getRegistrationType();
        $this->manual_registration = $user->isManualRegistration();
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

    public function getUser(): UserEntity {
        return $this->user;
    }
}
