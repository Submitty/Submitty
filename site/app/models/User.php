<?php

namespace app\models;

use app\libraries\database\IDatabaseQueries;

/**
 * Class User
 */
class User extends Model {
    /**
     * @var array
     */
    private $details = array();

    /**
     * User constructor.
     *
     * @param string           $user_id
     * @param IDatabaseQueries $database
     */
    public function __construct($user_id, $database) {
        $details = $database->getUserById($user_id);
        if (count($details) == 0) {
            return false;
        }

        $this->details = $details;

        return true;
    }

    public function accessGrading() {
        return $this->details['user_group'] < 4;
    }
    
    public function accessFullGrading() {
        return $this->details['user_group'] < 3;
    }

    public function accessAdmin() {
        return $this->details['user_group'] <= 1;
    }

    public function isDeveloper() {
        return $this->details['user_group'] == 0;
    }

    public function getId() {
        return $this->details['user_id'];
    }
    
    public function getFirstName() {
        return $this->details['user_firstname'];
    }
    
    public function getLastName() {
        return $this->details['user_lastname'];
    }
    
    public function getEmail() {
        return $this->details['user_email'];
    }
    
    public function getGroup() {
        return $this->details['user_group'];
    }
    
    public function getRegistrationSection() {
        return $this->details['registration_section'];
    }
    
    public function getRotatingSection() {
        return $this->details['rotating_section'];
    }
    
    public function isManualRegistration() {
        return $this->details['manual_registration'];
    }
}