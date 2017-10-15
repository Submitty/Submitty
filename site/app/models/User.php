<?php

namespace app\models;

use app\libraries\Core;

/**
 * Class User
 *
 * @method string getId()
 * @method void setId(string $id) Get the id of the loaded user
 * @method void setAnonId(string $anon_id)
 * @method string getPassword()
 * @method string getFirstName() Get the first name of the loaded user
 * @method string getPreferredFirstName() Get the preferred name of the loaded user
 * @method string getDisplayedFirstName() Returns the preferred name if one exists and is not null or blank,
 *                                        otherwise return the first name field for the user.
 * @method string getLastName() Get the last name of the loaded user
 * @method void setLastName(string $last_name)
 * @method string getEmail()
 * @method void setEmail(string $email)
 * @method int getGroup()
 * @method void setGroup(integer $group)
 * @method int getRegistrationSection()
 * @method int getRotatingSection()
 * @method void setManualRegistration(bool $flag)
 * @method bool isManualRegistration()
 * @method void setUserUpdated(bool $flag)
 * @method bool isUserUpdated()
 * @method void setInstructorUpdated(bool $flag)
 * @method bool isInstructorUpdated()
 * @method array getGradingRegistrationSections()
 * @method bool isLoaded()
 */
class User extends AbstractModel {

    /** @property @var bool Is this user actually loaded (else you cannot access the other member variables) */
    protected $loaded = false;

    /** @property @var string The id of this user which should be a unique identifier (ex: RCS ID at RPI) */
    protected $id;
    /** @property @var string The anonymous id of this user which should be unique for each course they are in*/
    protected $anon_id;
    /**
     * @property
     * @var string The password for the student used for database authentication. This should be hashed and salted.
     * @link http://php.net/manual/en/function.password-hash.php
     */
    protected $password = null;
    /** @property @var string The first name of the user */
    protected $first_name;
    /** @property @var string The first name of the user */
    protected $preferred_first_name = "";
    /** @property @var  string The name to be displayed by the system (either preferred name or first name) */
    protected $displayed_first_name;
    /** @property @var string The last name of the user */
    protected $last_name;
    /** @property @var string The email of the user */
    protected $email;
    /** @property @var int The group of the user, used for access controls (ex: student, instructor, etc.) */
    protected $group;

    /** @property @var int What is the registration section that the user was assigned to for the course */
    protected $registration_section = null;
    /** @property @var int What is the assigned rotating section for the user */
    protected $rotating_section = null;

    /**
     * @property
     * @var bool Was the user imported via a normal class list or was added manually. This is useful for students
     *           that are doing independent studies in the course, so not actually registered and so wouldn't want
     *           to be shifted to a null registration section or rotating section like a dropped student
     */
    protected $manual_registration = false;

	/**
	 * @property
	 * @var bool This flag is set TRUE when a user edits their own preferred firstname.  When TRUE, preferred firstname
	 *           is supposed to be locked from changes via student auto feed script.  Note that auto feed is still
	 *           permitted to change (correct?) a user's legal firstname/lastname and email address.
	 */
    protected $user_updated = false;

	/**
	 * @property
	 * @var bool This flag is set TRUE when the instructor edits another user's record.  When TRUE, preferred firstname
	 *           is supposed to be locked from changes via student auto feed script.  Note that auto feed is still
	 *           permitted to change (correct?) a user's legal firstname/lastname and email address.
	 */
    protected $instructor_updated = false;

    /** @property @var array */
    protected $grading_registration_sections = array();

    /**
     * User constructor.
     *
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $details=array()) {
        parent::__construct($core);
        if (count($details) == 0) {
            return;
        }

        $this->loaded = true;
        $this->setId($details['user_id']);
        if (isset($details['user_password'])) {
            $this->setPassword($details['user_password']);
        }

        if (!empty($details['anon_id'])) {
            $this->anon_id = $details['anon_id'];
        }

        $this->setFirstName($details['user_firstname']);
        if (isset($details['user_preferred_firstname'])) {
            $this->setPreferredFirstName($details['user_preferred_firstname']);
        }

        $this->last_name = $details['user_lastname'];
        $this->email = $details['user_email'];
        $this->group = isset($details['user_group']) ? intval($details['user_group']) : 4;
        if ($this->group > 4 || $this->group < 0) {
            $this->group = 4;
        }

        $this->user_updated = isset($details['user_updated']) && $details['user_updated'] === true;
        $this->instructor_updated = isset($details['instructor_updated']) && $details['instructor_updated'] === true;

        $this->registration_section = isset($details['registration_section']) ? intval($details['registration_section']) : null;
        $this->rotating_section = isset($details['rotating_section']) ? intval($details['rotating_section']) : null;
        $this->manual_registration = isset($details['manual_registration']) && $details['manual_registration'] === true;
        if (isset($details['grading_registration_sections'])) {
            $this->setGradingRegistrationSections($details['grading_registration_sections']);
        }
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

    public function setPassword($password) {
        $info = password_get_info($password);
        if ($info['algo'] === 0) {
            $this->password = password_hash($password, PASSWORD_DEFAULT);
        }
        else {
            $this->password = $password;
        }
    }

    public function setFirstName($name) {
        $this->first_name = $name;
        $this->setDisplayedFirstName();
    }

    /**
     * Set the preferred name of the loaded user (does not affect db. call updateUser.)
     * @param string $name
     */
    public function setPreferredFirstName($name) {
        $this->preferred_first_name = $name;
        $this->setDisplayedFirstName();
    }

    private function setDisplayedFirstName() {
        if ($this->preferred_first_name !== "" && $this->preferred_first_name !== null) {
            $this->displayed_first_name = $this->preferred_first_name;
        }
        else {
            $this->displayed_first_name = $this->first_name;
        }
    }

    public function setRegistrationSection($section) {
        $this->registration_section = ($section !== null) ? intval($section) : null;
    }

    public function setRotatingSection($section) {
        $this->rotating_section = ($section !== null) ? intval($section) : null;
    }

    public function setGradingRegistrationSections($sections) {
        if ($this->getGroup() < 4) {
            $this->grading_registration_sections = $sections;
        }
    }

    public function getAnonId() {
        if($this->anon_id === null) {
            $alpha = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
            $anon_ids = $this->core->getQueries()->getAllAnonIds();
            $alpha_length = strlen($alpha) - 1;
            do {
                $random = "";
                for ($i = 0; $i < 15; $i++) {
                    // this throws an exception if there's no avaiable source for generating
                    // random exists, but that shouldn't happen on our targetted endpoints (Ubuntu/Debian)
                    // so just ignore this fact
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $random .= $alpha[random_int(0, $alpha_length)];
                }
            } while(in_array($random, $anon_ids));
            $this->anon_id = $random;
            $this->core->getQueries()->updateUser($this, $this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse());
        }
        return $this->anon_id;
    }

    /**
     * Checks $data to make sure it is acceptable for $field.
     *
     * @param string $field
     * @param mixed $data
     * @return bool
     */
    static public function validateUserData($field, $data) {

    	switch($field) {
		case 'user_id':
			//Username / useer_id must contain only lowercase alpha, numbers, underscores, hyphens
			return preg_match("~^[a-z0-9_\-]+$~", $data) === 1;
		case 'user_firstname':
		case 'user_lastname':
		case 'user_preferred_firstname':
			//First, Last, Preferred name must be alpha characters, white-space, or certain punctuation.
        	return preg_match("~^[a-zA-Z'`\-\.\(\) ]+$~", $data) === 1;
		case 'user_email':
			//Check email address for appropriate format. e.g. "user@university.edu", "user@cs.university.edu", etc.
			return preg_match("~^[^(),:;<>@\\\"\[\]]+@(?!\-)[a-zA-Z0-9\-]+(?<!\-)(\.[a-zA-Z0-9]+)+$~", $data) === 1;
		case 'user_group':
            //user_group check is a digit between 1 - 4.
			return preg_match("~^[1-4]{1}$~", $data) === 1;
		case 'user_password':
	        //Database password cannot be blank, no check on format
			return $data !== "";
		default:
			//$data can't be validated since $field is unknown.  Notify developer with a stop error (also protectes data record integrity).
			$field = var_export(htmlentities($field), true);
			$data = var_export(htmlentities($data), true);
			trigger_error('User::validateUserData() called with unknown $field '.$field.' and $data '.$data, E_USER_ERROR);
    	}
    }
}
