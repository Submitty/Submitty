<?php

namespace app\models;

use app\libraries\Core;
use app\exceptions\ValidationException;

/**
 * Class User
 *
 * @method string getId()
 * @method void setId(string $id) Get the id of the loaded user
 * @method void getNumericId()
 * @method void setNumericId(string $id)
 * @method void setAnonId(string $anon_id)
 * @method string getPassword()
 * @method string getLegalFirstName() Get the first name of the loaded user
 * @method string getPreferredFirstName() Get the preferred first name of the loaded user
 * @method string getDisplayedFirstName() Returns the preferred first name if one exists and is not null or blank,
 *                                        otherwise return the legal first name field for the user.
 * @method string getLegalLastName() Get the last name of the loaded user
 * @method string getPreferredLastName()  Get the preferred last name of the loaded user
 * @method string getDisplayedLastName()  Returns the preferred last name if one exists and is not null or blank,
 *                                        otherwise return the legal last name field for the user.
 * @method string getEmail()
 * @method void setEmail(string $email)
 * @method int getGroup()
 * @method int getAccessLevel()
 * @method void setGroup(integer $group)
 * @method string getRegistrationSection()
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

    /**
     * Access groups, lower is more access
     */

    const GROUP_INSTRUCTOR            = 1;
    const GROUP_FULL_ACCESS_GRADER    = 2;
    const GROUP_LIMITED_ACCESS_GRADER = 3;
    const GROUP_STUDENT               = 4;
    /** Logged out */
    const GROUP_NONE                  = 5;

    /**
     * Access levels, lower level means more access
     */
    const LEVEL_SUPERUSER             = 1;
    const LEVEL_FACULTY               = 2;
    const LEVEL_USER                  = 3;

    /** @property @var bool Is this user actually loaded (else you cannot access the other member variables) */
    protected $loaded = false;

    /** @property @var string The id of this user which should be a unique identifier (ex: RCS ID at RPI) */
    protected $id;
    /** @property @var string Alternate ID for a user, such as a campus assigned ID (ex: RIN at RPI) */
    protected $numeric_id = null;
    /** @property @var string The anonymous id of this user which should be unique for each course they are in*/
    protected $anon_id;
    /**
     * @property
     * @var string The password for the student used for database authentication. This should be hashed and salted.
     * @link http://php.net/manual/en/function.password-hash.php
     */
    protected $password = null;
    /** @property @var string The first name of the user */
    protected $legal_first_name;
    /** @property @var string The preferred first name of the user */
    protected $preferred_first_name = "";
    /** @property @var  string The first name to be displayed by the system (either first name or preferred first name) */
    protected $displayed_first_name;
    /** @property @var string The last name of the user */
    protected $legal_last_name;
    /** @property @var string The preferred last name of the user */
    protected $preferred_last_name = "";
    /** @property @var  string The last name to be displayed by the system (either last name or preferred last name) */
    protected $displayed_last_name;
    /** @property @var string The email of the user */
    protected $email;
    /** @property @var int The group of the user, used for access controls (ex: student, instructor, etc.) */
    protected $group;
    /** @property @var int The access level of the user (ex: superuser, faculty, user) */
    protected $access_level;
    /** @property @var string What is the registration section that the user was assigned to for the course */
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

    /** @property @var array */
    protected $notification_settings = array();

    /**
     * User constructor.
     *
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $details = array()) {
        parent::__construct($core);
        if (count($details) == 0) {
            return;
        }

        $this->loaded = true;
        $this->setId($details['user_id']);
        if (isset($details['user_password'])) {
            $this->setPassword($details['user_password']);
        }

        if (isset($details['user_numeric_id'])) {
            $this->setNumericId($details['user_numeric_id']);
        }

        if (!empty($details['anon_id'])) {
            $this->anon_id = $details['anon_id'];
        }

        $this->setLegalFirstName($details['user_firstname']);
        if (isset($details['user_preferred_firstname'])) {
            $this->setPreferredFirstName($details['user_preferred_firstname']);
        }

        $this->setLegalLastName($details['user_lastname']);
        if (isset($details['user_preferred_lastname'])) {
            $this->setPreferredLastName($details['user_preferred_lastname']);
        }

        $this->email = $details['user_email'];
        $this->group = isset($details['user_group']) ? intval($details['user_group']) : 4;
        if ($this->group > 4 || $this->group < 0) {
            $this->group = 4;
        }
        $this->access_level = isset($details['user_access_level']) ? intval($details['user_access_level']) : 3;
        if ($this->access_level > 3 || $this->access_level < 1) {
            $this->access_level = 3;
        }

        $this->user_updated = isset($details['user_updated']) && $details['user_updated'] === true;
        $this->instructor_updated = isset($details['instructor_updated']) && $details['instructor_updated'] === true;

        //Other call to get notification settings??
        $this->notification_settings = self::constructNotificationSettings($details);

        $this->registration_section = isset($details['registration_section']) ? $details['registration_section'] : null;
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
        return $this->group === 1;
    }

    /**
     * Gets whether the user is allowed to access the faculty interface
     * @return bool
     */
    public function accessFaculty() {
        return $this->access_level < 3;
    }

    public function setPassword($password) {
        if (!empty($password)) {
            $info = password_get_info($password);
            if ($info['algo'] === 0) {
                $this->password = password_hash($password, PASSWORD_DEFAULT);
            }
            else {
                $this->password = $password;
            }
        }
    }

    public function setLegalFirstName($name) {
        $this->legal_first_name = $name;
        $this->setDisplayedFirstName();
    }

    public function setLegalLastName($name) {
        $this->legal_last_name = $name;
        $this->setDisplayedLastName();
    }

    public function getNotificationSettings() {
        return $this->notification_settings; //either receives it or not
    }

    public function getNotificationSetting($type) {
        return $this->notification_settings[$type];
    }

    public function updateUserNotificationSettings($key, $value) {
        $this->notification_settings[$key] = $value;
    }

    /**
     * Set the preferred first name of the loaded user (does not affect db. call updateUser.)
     * @param string $name
     */
    public function setPreferredFirstName($name) {
        $this->preferred_first_name = $name;
        $this->setDisplayedFirstName();
    }

    public function setPreferredLastName($name) {
        $this->preferred_last_name = $name;
        $this->setDisplayedLastName();
    }

    private function setDisplayedFirstName() {
        $this->displayed_first_name = (!empty($this->preferred_first_name)) ? $this->preferred_first_name : $this->legal_first_name;
    }

    private function setDisplayedLastName() {
        $this->displayed_last_name = (!empty($this->preferred_last_name)) ? $this->preferred_last_name : $this->legal_last_name;
    }

    public function getDisplayFullName() {
        return $this->getDisplayedFirstName() . ' ' . $this->getDisplayedLastName();
    }

    public function setRegistrationSection($section) {
        $this->registration_section = ($section !== null) ? $section : null;
    }

    public function setRotatingSection($section) {
        $this->rotating_section = ($section !== null) ? intval($section) : null;
    }

    public function setGradingRegistrationSections($sections) {
        if ($this->accessGrading()) {
            $this->grading_registration_sections = $sections;
        }
    }

    public function getAnonId() {
        if ($this->anon_id === null) {
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
            } while (in_array($random, $anon_ids));
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
    public static function validateUserData($field, $data) {

        switch ($field) {
            case 'user_id':
                 //Username / user_id must contain only lowercase alpha, numbers, underscores, hyphens
                return preg_match("~^[a-z0-9_\-]+$~", $data) === 1;
            case 'user_legal_firstname':
            case 'user_legal_lastname':
                //First and last name must be alpha characters, white-space, or certain punctuation.
                return preg_match("~^[a-zA-Z'`\-\.\(\) ]+$~", $data) === 1;
            case 'user_preferred_firstname':
            case 'user_preferred_lastname':
                //Preferred first and last name may be "", alpha chars, white-space, certain punctuation AND between 0 and 30 chars.
                return preg_match("~^[a-zA-Z'`\-\.\(\) ]{0,30}$~", $data) === 1;
            case 'user_email':
                // emails are allowed to be the empty string...
                if ($data === "") {
                    return true;
                }
                // -- or ---
                // Check email address for appropriate format. e.g. "user@university.edu", "user@cs.university.edu", etc.
                return preg_match("~^[^(),:;<>@\\\"\[\]]+@(?!\-)[a-zA-Z0-9\-]+(?<!\-)(\.[a-zA-Z0-9]+)+$~", $data) === 1;
            case 'user_group':
                //user_group check is a digit between 1 - 4.
                return preg_match("~^[1-4]{1}$~", $data) === 1;
            case 'registration_section':
                //Registration section must contain only alpha (upper and lower permitted), numbers, underscores, hyphens.
                //"NULL" registration section should be validated as a datatype, not as a string.
                return preg_match("~^(?!^null$)[a-z0-9_\-]+$~i", $data) === 1 || is_null($data);
            case 'user_password':
                //Database password cannot be blank, no check on format
                return $data !== "";
            default:
                //$data can't be validated since $field is unknown.  Notify developer with an exception (also protectes data record integrity).
                $ex_field = '$field: ' . var_export(htmlentities($field), true);
                $ex_data = '$data:  ' . var_export(htmlentities($data), true);
                throw new ValidationException('User::validateUserData() called with unknown $field.  See extra details, below.', array($ex_field, $ex_data));
        }
    }

    public static function constructNotificationSettings($details) {
        $notification_settings = [];
        $notification_settings['reply_in_post_thread'] = $details['reply_in_post_thread'] ?? false;
        $notification_settings['merge_threads'] = $details['merge_threads'] ?? false;
        $notification_settings['all_new_threads'] = $details['all_new_threads'] ?? false;
        $notification_settings['all_new_posts'] = $details['all_new_posts'] ?? false;
        $notification_settings['all_modifications_forum'] = $details['all_modifications_forum'] ?? false;
        $notification_settings['team_invite'] = $details['team_invite'] ?? true;
        $notification_settings['team_joined'] = $details['team_joined'] ?? true;
        $notification_settings['team_member_submission'] = $details['team_member_submission'] ?? true;
        $notification_settings['self_notification'] = $details['self_notification'] ?? false;
        $notification_settings['reply_in_post_thread_email'] = $details['reply_in_post_thread_email'] ?? false;
        $notification_settings['merge_threads_email'] = $details['merge_threads_email'] ?? false;
        $notification_settings['all_new_threads_email'] = $details['all_new_threads_email'] ?? false;
        $notification_settings['all_new_posts_email'] = $details['all_new_posts_email'] ?? false;
        $notification_settings['all_modifications_forum_email'] = $details['all_modifications_forum_email'] ?? false;
        $notification_settings['team_invite_email'] = $details['team_invite_email'] ?? true;
        $notification_settings['team_joined_email'] = $details['team_joined_email'] ?? true;
        $notification_settings['team_member_submission_email'] = $details['team_member_submission_email'] ?? true;
        $notification_settings['self_notification_email'] = $details['self_notification_email'] ?? false;
        return $notification_settings;
    }

    /**
     * Checks if the user is on ANY team for the given assignment
     *
     * @param string gradable_id
     * @return bool
     */
    public function onTeam($gradeable_id) {
        $team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $this->id);
        return $team !== null;
    }
}
