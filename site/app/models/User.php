<?php

namespace app\models;

use app\libraries\DatabaseUtils;

/**
 * Class User
 */
class User {
    
    /**
     * @var bool Is this user actually loaded (else you cannot access the other member variables)
     */
    private $loaded = false;
    
    /** @var string  The id of this user which should be a unique identifier (ex: RCS ID at RPI) */
    private $id;
    /** @var string  The first name of the user */
    private $first_name;
    /** @var string  The first name of the user */
    private $preferred_first_name = "";
    /** @var  string  The name to be displayed by the system (either preferred name or first name) */
    private $displayed_first_name;
    /** @var string  The last name of the user */
    private $last_name;
    /** @var string  The email of the user */
    private $email;
    /** @var int     The group of the user, used for access controls (ex: student, instructor, etc.) */
    private $group;
    
    /** @var int What is the registration section that the user was assigned to for the course */
    private $registration_section = null;
    /** @var int What is the assigned rotating section for the user */
    private $rotating_section = null;
    
    /**
     * @var bool Was the user imported via a normal class list or was added manually. This is useful for students
     *           that are doing independent studies in the course, so not actually registered and so wouldn't want
     *           to be shifted to a null registration section or rotating section like a dropped student
     */
    private $manual_registration = false;

    /** @var array */
    private $grading_registration_sections = array();

    /**
     * User constructor.
     * @param array $details
     */
    public function __construct($details) {
        if (count($details) == 0) {
            return;
        }

        $this->loaded = true;
        $this->id = $details['user_id'];
        $this->first_name = $details['user_firstname'];
        if (isset($details['user_preferred_firstname'])) {
            $this->preferred_first_name = $details['user_preferred_firstname'];
        }
        $this->setDisplayedFirstName();

        $this->last_name = $details['user_lastname'];
        $this->email = $details['user_email'];
        $this->group = $details['user_group'];
        $this->registration_section = $details['registration_section'];
        $this->rotating_section = $details['rotating_section'];
        $this->manual_registration = $details['manual_registration'];
        $this->grading_registration_sections = DatabaseUtils::fromPGToPHPArray($details['grading_registration_sections']);
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

    public function setId($id) {
        $this->id = $id;
    }
    
    /**
     * Get the first name of the loaded user
     * @return string
     */
    public function getFirstName() {
        return $this->first_name;
    }

    public function setFirstName($name) {
        $this->first_name = $name;
    }

    /**
     * Get the preferred name of the loaded user
     * @return string
     */
    public function getPreferredFirstName() {
        return $this->preferred_first_name;
    }

    public function setPreferredFirstName($name) {
        $this->preferred_first_name = $name;
        $this->setDisplayedFirstName();
    }

    /**
     * Returns the preferred name if one exists and is not null or blank, otherwise return the
     * first name field for the user.
     * @return string
     */
    public function getDisplayedFirstName() {
        return $this->displayed_first_name;
    }

    public function setDisplayedFirstName() {
        if ($this->preferred_first_name !== "" && $this->preferred_first_name !== null) {
            $this->displayed_first_name = $this->preferred_first_name;
        }
        else {
            $this->displayed_first_name = $this->first_name;
        }
    }
    
    /**
     * Get the last name of the loaded user
     * @return string
     */
    public function getLastName() {
        return $this->last_name;
    }

    public function setLastName($name) {
        $this->last_name = $name;
    }
    
    /**
     * Get the email of the loaded user
     * @return string
     */
    public function getEmail() {
        return $this->email;
    }

    public function setEmail($email) {
        $this->email = $email;
    }
    
    /**
     * Get the group of the loaded user
     * @return int
     */
    public function getGroup() {
        return $this->group;
    }

    public function setGroup($group) {
        $this->group = $group;
    }
    
    /**
     * Get the registration section of the loaded user
     * @return int
     */
    public function getRegistrationSection() {
        return $this->registration_section;
    }

    public function setRegistrationSection($section) {
        $this->registration_section = $section;
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

    public function setManualRegistration($manual) {
        $this->manual_registration = $manual === true;
    }

    /**
     * @return array
     */
    public function getGradingRegistrationSections() {
        return $this->grading_registration_sections;
    }

    public function setGradingRegistrationSections($sections) {
        $this->grading_registration_sections = $sections;
    }
}