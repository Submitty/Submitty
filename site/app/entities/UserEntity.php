<?php

declare(strict_types=1);

namespace app\entities;

use app\entities\forum\Post;
use app\entities\forum\Thread;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

/**
 * ORM representation of app\libraries\User class.
 * Allows linked entities to access user data without needing to go to database.
 * Should (eventually) replace app\libraries\User as we refactor more code to use Doctrine.
 */
#[ORM\Entity]
#[ORM\Table(name: "users")]
class UserEntity {
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected string $user_id;

    public function getId(): string {
        return $this->user_id;
    }

    #[ORM\Column(type: Types::STRING)]
    protected string $user_numeric_id;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_givenname;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected string|null $user_preferred_givenname;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_familyname;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected string|null $user_preferred_familyname;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_email;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $user_group;

    #[ORM\Column(type: Types::STRING)]
    protected string $registration_section;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $rotating_section;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $user_updated;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $instructor_updated;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $manual_registration;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $last_updated;

    #[ORM\Column(type: Types::STRING)]
    protected string $time_zone;

    #[ORM\Column(type: Types::STRING)]
    protected string $display_image_state;

    #[ORM\Column(type: Types::STRING)]
    protected string $registration_subsection;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_email_secondary;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $user_email_secondary_notify;

    #[ORM\Column(type: Types::STRING)]
    protected string $registration_type;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_pronouns;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $user_last_initial_format;

    #[ORM\Column(type: Types::STRING)]
    protected string $display_name_order;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $display_pronouns;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_preferred_locale;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $previous_rotating_section;

    /**
     * @var Collection<Post>
     */
    #[ORM\OneToMany(mappedBy: "author", targetEntity: Post::class)]
    protected Collection $posts;

    /**
     * @var Collection<Thread>
     */
    #[ORM\OneToMany(mappedBy: "author", targetEntity: Thread::class)]
    protected Collection $threads;

    /**
     * @var Collection<Post>
     */
    #[ORM\ManyToMany(mappedBy: "upduckers", targetEntity: Post::class)]
    protected Collection $upducks;

    public function accessFullGrading(): bool {
        return $this->user_group < 3;
    }

    public function accessGrading(): bool {
        return $this->user_group < 4;
    }

    /**
     * @return array<string, bool|string>
     */
    public function getDisplayInfo(): array {
        $out = [];
        $out["given_name"] = $this->user_preferred_givenname ?? $this->user_givenname;
        $out["family_name"] = $this->user_preferred_familyname ?? $this->user_familyname;
        $out["user_email"] = $this->user_email;
        $out["pronouns"] = $this->user_pronouns;
        $out["display_pronouns"] = $this->display_pronouns;

        return $out;
    }
}
