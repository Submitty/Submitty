<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

/**
 * @method string getId()
 * @method string getLateDayExceptions()
 * @method string getDisplayedGivenName()
 * @method string getDisplayedFamilyName()
 * @method string getReasonForException()
 */
class SimpleLateUser extends AbstractModel {
    /** @prop
     * @var bool Is this user actually loaded (else you cannot access the other member variables) */
    protected $loaded = false;

    /** @prop
     * @var string The id of this user which should be a unique identifier (ex: RCS ID at RPI) */
    protected $id;
    /** @prop
     * @var string The given name of the user */
    protected $legal_given_name;
    /** @prop
     * @var string The preferred given name of the user if exists */
    protected $preferred_given_name;
    /** @prop
     * @var  string The name to be displayed by the system (either preferred given name or legal given name) */
    protected $displayed_given_name;
    /** @prop
     * @var string The family name of the user */
    protected $legal_family_name;
    /** @prop
     * @var string The preferred family name of the user if exists */
    protected $preferred_family_name;
    /** @prop
     * @var  string The name to be displayed by the system (either preferred family name or legal family name) */
    protected $displayed_family_name;
    /** @prop
     * @var string The allowed late days of the user */
    protected $allowed_late_days;
    /** @prop
     * @var date The day late days are put into effect */
    protected $since_timestamp;
    /** @prop
     * @var string The extensions of a user (allowed late days for a specific homework) */
    protected $late_day_exceptions;
    /** @prop
     * @var string The reason for an extension given on a homework assignment */
    protected $reason_for_exception;

    /**
     * User constructor.
     * @param array $details
     */
    public function __construct(Core $core, $details) {
        parent::__construct($core);
        if (count($details) == 0) {
            return;
        }

        $this->loaded = true;
        $this->id = $details['user_id'];
        $this->legal_given_name = $details['user_givenname'];
        if (isset($details['user_preferred_givenname'])) {
            $this->preferred_given_name = $details['user_preferred_givenname'];
            $this->displayed_given_name = $details['user_preferred_givenname'];
        }
        else {
            $this->displayed_given_name = $details['user_givenname'];
        }

        $this->legal_family_name = $details['user_familyname'];
        if (isset($details['user_preferred_familyname'])) {
            $this->preferred_family_name = $details['user_preferred_familyname'];
            $this->displayed_family_name = $details['user_preferred_familyname'];
        }
        else {
            $this->displayed_family_name = $details['user_familyname'];
        }

        if (isset($details['allowed_late_days']) && isset($details['since_timestamp'])) {
            $this->allowed_late_days = $details['allowed_late_days'];
            $this->since_timestamp = DateUtils::parseDateTime($details['since_timestamp'], $this->core->getDateTimeNow()->getTimezone());
        }
        if (isset($details['late_day_exceptions'])) {
            $this->late_day_exceptions = $details['late_day_exceptions'];
        }
        if (isset($details['reason_for_exception'])) {
            $this->reason_for_exception = $details['reason_for_exception'];
        }
    }

    public function getSinceTimestamp() {
        return $this->since_timestamp->format($this->core->getConfig()->getDateTimeFormat()->getFormat('late_days_allowed'));
    }
}
