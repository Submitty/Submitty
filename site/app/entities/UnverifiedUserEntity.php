<?php

declare(strict_types=1);

namespace app\entities;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * ORM representation of app\libraries\User class.
 * Allows linked entities to access user data without needing to go to database.
 * Should (eventually) replace app\libraries\User as we refactor more code to use Doctrine.
 */
#[ORM\Entity]
#[ORM\Table(name: "unverified_users")]
class UnverifiedUserEntity {
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected string $user_id;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_password;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_givenname;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_familyname;

    #[ORM\Column(type: Types::STRING)]
    protected string $user_email;

    #[ORM\Column(type: Types::STRING)]
    protected string $verification_code;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    protected DateTime $verification_expiration;

    public function __construct(string $user_id, string $user_password, string $user_givenname, string $user_familyname, string $user_email, string $verification_code, DateTime $verification_expiration) {
        $this->setUserId($user_id);
        $this->setUserPassword($user_password);
        $this->setUserGivenName($user_givenname);
        $this->setUserFamilyName($user_familyname);
        $this->setUserEmail($user_email);
        $this->setVerificationValues(['verification_code' => $verification_code, 'verification_expiration' => $verification_expiration]);
    }

    public function getUserInfo() {
        $out = [];
        $out["user_givenname"] = $this->user_givenname;
        $out["user_familyname"] =  $this->user_familyname;
        $out["user_email"] = $this->user_email;
        $out["user_password"] = $this->user_password;
        $out['user_id'] = $this->user_id;
        $out['user_pronouns'] = '';
        $out['user_email_secondary'] = '';
        $out['user_email_secondary_notify'] = false;
        return $out;
    }

    public function getVerificationValues(): array {
        return ['verification_code' => $this->verification_code, 'verification_expiration' => $this->verification_expiration];
    }

    public function setVerificationValues(array $values): void {
        $this->verification_code = $values['verification_code'] ?? null;
        $this->verification_expiration = $values['verification_expiration'];
    }

    public function getId(): string {
        return $this->user_id;
    }

    public function getUserPassword(): string {
        return $this->user_password;
    }

    public function setUserId(string $user_id): void {
        $this->user_id = $user_id;
    }

    public function setUserPassword(string $user_password): void {
        $this->user_password = $user_password;
    }

    public function setUserGivenName(string $user_givenname): void {
        $this->user_givenname = $user_givenname;
    }

    public function setUserEmail(string $user_email): void {
        $this->user_email = $user_email;
    }

    public function setUserFamilyName(string $user_familyname): void {
        $this->user_familyname = $user_familyname;
    }
}
