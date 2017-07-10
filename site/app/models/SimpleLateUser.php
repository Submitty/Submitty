<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DatabaseUtils;

class SimpleLateUser extends AbstractModel {
    
    /** @property @var bool Is this user actually loaded (else you cannot access the other member variables) */
    protected $loaded = false;
    
    /** @property @var string The id of this user which should be a unique identifier (ex: RCS ID at RPI) */
    protected $id;
    /** @property @var string The first name of the user */
    protected $first_name;
    /** @property @var string The preferred first name of the user if exists */
    protected $preferred_first_name = "";
    /** @property @var  string The name to be displayed by the system (either preferred name or first name) */
    protected $displayed_first_name;
    /** @property @var string The last name of the user */
    protected $last_name;
    /** @property @var string The allowed late days of the user */
    protected $allowed_late_days;
    /** @property @var string The day late days are put into effect */
    protected $since_timestamp;
    /** @property @var string The extensions of a user (allowed late days for a specific homework) */
    protected $late_day_exceptions;

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
        $this->setId($details['user_id']);
        $this->setFirstName($details['user_firstname']);
        if (isset($details['user_preferred_firstname']) && $details['user_preferred_firstname'] !== "") {
            $this->setPreferredFirstName($details['user_preferred_firstname']);
            $this->setDisplayedFirstName($details['user_preferred_firstname']);
        }
        else{
            $this->setDisplayedFirstName($details['user_firstname']);
        }
        $this->setLastName($details['user_lastname']);
        if(isset($details['allowed_late_days']) && isset($details['since_timestamp'])){
            $this->setAllowedLateDays($details['allowed_late_days']);
            $this->setSinceTimestamp($details['since_timestamp']);
        }
        if(isset($details['late_day_exceptions'])){
            $this->setLateDayExceptions($details['late_day_exceptions']);
        }
    }
}
