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
    protected string $password;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_givenname;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_familyname;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_email;

    public function getUserInfo() {
        $out = [];
        $out["given_name"] = $this->user_givenname;
        $out["family_name"] =  $this->user_familyname;
        $out["user_email"] = $this->user_email;
        $out["password"] = $this->password;
        $out[""] = $this->display_pronouns;

    }
}
