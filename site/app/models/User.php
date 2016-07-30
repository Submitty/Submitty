<?php

namespace app\models;

use app\libraries\database\IDatabaseQueries;

/**
 * Class User
 */
class User {
    
    /**
     * @var bool Is this user actually loaded (else you cannot access the other member variables)
     */
    private $loaded = false;
    
    /**
     * @var string $id          The id of this user which should be a unique identifier (ex: RCS ID at RPI)
     * @var string $first_name  The first name of the user
     * @var string $last_name   The last name of the user
     * @var string $email       The email of the user
     * @var int    $group       The group of the user, used for access controls (ex: student, instructor, etc.)
     */
    private $id;
    private $first_name;
    private $last_name;
    private $email;
    private $group;
    
    /**
     * @var int $registration_section What is the registration section that the user was assigned to for the course
     * @var int $rotating_section     What is the assigned rotating section for the user
     */
    private $registration_section;
    private $rotating_section;
    
    /**
     * @var bool Was the user imported via a normal class list or was added manually. This is useful for students
     *           that are doing independent studies in the course, so not actually registered and so wouldn't want
     *           to be shifted to a null registration section or rotating section like a dropped student
     */
    private $manual_registration;

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
        
        $this->loaded = true;
        $this->id = $details['user_id'];
        $this->first_name = $details['user_firstname'];
        $this->last_name = $details['user_lastname'];
        $this->email = $details['user_email'];
        $this->group = $details['user_group'];
        $this->registration_section = $details['registration_section'];
        $this->rotating_section = $details['rotating_section'];
        $this->manual_registration = $details['manual_registration'];

        return true;
    }
    
    /**
     * Gets whether the user was actually loaded from the DB with the given user id
     * @return bool
     */
    public function isLoaded() {
        return $this->loaded;
    }
    
    /**
     * Gets whether the user is allowed to access the grading interface
     * @return bool
     */
    public function accessGrading() {
        return $this->group < 4;
    }
    
    /**
     * Gets whether the user is allowed to access the full grading interface
     * @return bool
     */
    public function accessFullGrading() {
        return $this->group < 3;
    }
    
    /**
     * Gets whether the user is allowed to access the administrative interface
     * @return bool
     */
    public function accessAdmin() {
        return $this->group <= 1;
    }
    
    /**
     * Gets whether the user is considered a developer (and thus should have access to debug information)
     * @return int
     */
    public function isDeveloper() {
        return $this->group === 0;
    }
    
    /**
     * Get the id of the loaded user
     * @return string
     */
    public function getId() {
        return $this->id;
    }
    
    /**
     * Get the first name of the loaded user
     * @return string
     */
    public function getFirstName() {
        return $this->first_name;
    }
    
    /**
     * Get the last name of the loaded user
     * @return string
     */
    public function getLastName() {
        return $this->last_name;
    }
    
    /**
     * Get the email of the loaded user
     * @return string
     */
    public function getEmail() {
        return $this->email;
    }
    
    /**
     * Get the group of the loaded user
     * @return int
     */
    public function getGroup() {
        return $this->group;
    }
    
    /**
     * Get the registration section of the loaded user
     * @return int
     */
    public function getRegistrationSection() {
        return $this->registration_section;
    }
    
    /**
     * Get the rotating section of the loaded user
     * @return int
     */
    public function getRotatingSection() {
        return $this->rotating_section;
    }
    
    /**
     * Gets whether the user set as a manual registration
     * @return bool
     */
    public function isManualRegistration() {
        return $this->manual_registration;
    }
}