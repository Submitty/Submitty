<?php

namespace app\models;

use app\libraries\Core;
use app\exceptions\ValidationException;
use app\libraries\DateUtils;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;

/**
 * Class UnverifiedUser
 *
 * @method string getId()
 * @method void setId(string $id) Get the id of the loaded user
 * @method string getPassword()
 * @method void setPassword(string $password)
 * @method string getLegalGivenName() Get the given name of the loaded user
 * @method string getLegalFamilyName() Get the family name of the loaded user
 * @method string getVerificationCode()
 * @method int getVerificationExpiration()
 * @method void setVerificationCode(string $verification_code)
 * @method void setVerificationExpiration(int $verification_expiration)
 * @method string getEmail()
 * @method void setEmail(string $email)
 */
class UnverifiedUser extends AbstractModel {
    /** @prop
     * @var bool Is this user actually loaded (else you cannot access the other member variables) */
    protected $loaded = false;

    /** @prop
     * @var string The id of this user which should be a unique identifier */
    protected $id;

    /**
     * @prop
     * @var string The password for the student used for database authentication. This should be hashed and salted.
     * @link http://php.net/manual/en/function.password-hash.php
     */
    protected $password = null;
    /** @prop
     * @var string The given name of the user */
    protected $legal_given_name;

    /** @prop
     * @var string The family name of the user */
    protected $legal_family_name;
 
    /** @prop
     * @var string The primary email of the user */
    protected $email;

    /** @prop Email verification code */
    protected string $verification_code;

    /** @prop Timestamp of the expiration of the verification code */
    protected int $verification_expiration;
    
    
    /*
     * User constructor.
     *
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $details = []) {
        parent::__construct($core);
        if (count($details) == 0) {
            return;
        }
        $this->loaded = true;
        $this->setId($details['user_id']);
        $this->setPassword($details['user_password']);
        $this->setLegalGivenName($details['user_givenname']);
        $this->setLegalFamilyName($details['user_familyname']);
        $this->core->getQueries()->updateUserVerificationValues($details['user_email'], $details['user_verification_code'], $details['user_verification_expiration']);
        $this->verification_expiration = $details['user_verification_expiration'];
        $this->verification_code = $details['user_verification_code'];
    }
}
