<?php

namespace app\models;

use app\libraries\Core;
use app\exceptions\ValidationException;
use app\libraries\DateUtils;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;

/**
 * Class User
 *
 * @method string getId()
 * @method void setId(string $id) Get the id of the loaded user
 * @method string getNumericId()
 * @method void setNumericId(string $id)
 * @method string getPassword()
 * @method string getLegalGivenName() Get the given name of the loaded user
 * @method string|null getPreferredGivenName() Get the preferred given name of the loaded user
 * @method string|null getDisplayedGivenName() Returns the preferred given name if one exists and is not null or blank,
 *                                        otherwise return the legal given name field for the user.
 * @method string getLegalFamilyName() Get the family name of the loaded user
 * @method string getPreferredFamilyName()  Get the preferred family name of the loaded user
 * @method string getDisplayedFamilyName()  Returns the preferred family name if one exists and is not null or blank,
 *                                        otherwise return the legal family name field for the user.
 * @method string getPronouns() Returns the pronouns of the loaded user
 * @method bool getDisplayPronouns() Returns the display pronoun variable of loaded user
 * @method void setPronouns(string $pronouns)
 * @method int getLastInitialFormat()
 * @method string getDisplayNameOrder()
 * @method void setDisplayNameOrder()
 * @method string getEmail()
 * @method void setEmail(string $email)
 * @method string getSecondaryEmail()
 * @method void setSecondaryEmail(string $email)
 * @method bool getEmailBoth()
 * @method void setEmailBoth(bool $flag)
 * @method int getGroup()
 * @method int getAccessLevel()
 * @method void setGroup(integer $group)
 * @method void setRegistrationType(string $type)
 * @method string getRegistrationSection()
 * @method string getCourseSectionId()
 * @method void setCourseSectionId(string $Id)
 * @method int getRotatingSection()
 * @method string getRegistrationType()
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

    /**
     * Profile image set return codes
     */
    const PROFILE_IMG_SET_FAILURE = 0;
    const PROFILE_IMG_SET_SUCCESS = 1;
    /** Profile image quota of 50 images exhausted */
    const PROFILE_IMG_QUOTA_EXHAUSTED = 2;

    /**
     * Last initial display formats
     */
    const LAST_INITIAL_FORMATS = [ "Single", "Multi", "Hyphen-Multi", "None" ];

    /** @prop
     * @var bool Is this user actually loaded (else you cannot access the other member variables) */
    protected $loaded = false;

    /** @prop
     * @var string The id of this user which should be a unique identifier */
    protected $id;
    /** @prop
     * @var string Alternate ID for a user, such as a campus assigned ID */
    protected $numeric_id = null;
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
     * @var ?string The preferred given name of the user */
    protected $preferred_given_name;
    /** @prop
     * @var  string The given name to be displayed by the system (either given name or preferred given name) */
    protected $displayed_given_name;
    /** @prop
     * @var string The family name of the user */
    protected $legal_family_name;
    /** @prop
     * @var ?string The preferred family name of the user */
    protected $preferred_family_name;
    /** @prop
     * @var  string The family name to be displayed by the system (either family name or preferred family name) */
    protected $displayed_family_name;
    /** @prop
     * @var string The pronouns of the user */
    protected $pronouns = "";
    /** @prop
     * @var bool The display pronouns option of the user */
    protected bool $display_pronouns = false;
    /** @prop
     * @var int The display format for the last initial of the user */
    protected $last_initial_format = 0;
    /** @prop
     * @var string The order in which the user's given and family names are displayed */
    protected $display_name_order = "GIVEN_F";
    /** @prop
     * @var string The primary email of the user */
    protected $email;
    /** @prop
     * @var string The secondary email of the user */
    protected $secondary_email;
    /** @prop
     * @var string Determines whether or not user chose to receive emails to secondary email */
    protected $email_both;
    /** @prop
     * @var int The group of the user, used for access controls (ex: student, instructor, etc.) */
    protected $group;
    /** @prop
     * @var int The access level of the user (ex: superuser, faculty, user) */
    protected $access_level;
    /** @prop
     * @var bool Should the user only have one active session at a time? */
    protected bool $enforce_single_session;
    /** @prop
     * @var string What is the registration section that the user was assigned to for the course */
    protected $registration_section = null;
    /** @prop
     * @var string Unique id for course section */
    protected $course_section_id = null;
    /** @prop
     * @var int What is the assigned rotating section for the user */
    protected $rotating_section = null;
    /** @var string Appropriate time zone string from DateUtils::getAvailableTimeZones() */
    protected $time_zone;
    /** @prop
     * @var string|null The name of the preferred locale */
    protected $preferred_locale = null;
    /** @prop
     * @var string What is the registration subsection that the user was assigned to for the course */
    protected $registration_subsection = "";
    /** @prop
     * @var string What is the registration type of the user (graded, audit, withdrawn, staff) for the course */
    protected $registration_type;

    /**
     * @prop
     * @var bool Was the user imported via a normal class list or was added manually. This is useful for students
     *           that are doing independent studies in the course, so not actually registered and so wouldn't want
     *           to be shifted to a null registration section or rotating section like a dropped student
     */
    protected $manual_registration = false;

    /**
     * @prop
     * @var bool This flag is set TRUE when a user edits their own preferred givenname.  When TRUE, preferred givenname
     *           is supposed to be locked from changes via student auto feed script.  Note that auto feed is still
     *           permitted to change (correct?) a user's legal givenname/familyname and email address.
     */
    protected $user_updated = false;

    /**
     * @prop
     * @var bool This flag is set TRUE when the instructor edits another user's record.  When TRUE, preferred givenname
     *           is supposed to be locked from changes via student auto feed script.  Note that auto feed is still
     *           permitted to change (correct?) a user's legal givenname/familyname and email address.
     */
    protected $instructor_updated = false;

    /** @prop
     * @var array */
    protected $grading_registration_sections = [];

    /** @prop
     * @var array */
    protected $notification_settings = [];

    /** @prop
     * @var string The display_image_state string which can be used to instantiate a DisplayImage object */
    protected $display_image_state;

    /** @var array A cache of [gradeable id] => [anon id] */
    private $anon_id_by_gradeable = [];

    /**
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
        if (isset($details['user_password'])) {
            $this->setPassword($details['user_password']);
        }

        if (isset($details['user_numeric_id'])) {
            $this->setNumericId($details['user_numeric_id']);
        }

        $this->setLegalGivenName($details['user_givenname']);
        if (isset($details['user_preferred_givenname'])) {
            $this->setPreferredGivenName($details['user_preferred_givenname']);
        }

        $this->setPronouns($details['user_pronouns']);

        if (isset($details['display_name_order'])) {
            $this->setDisplayNameOrder($details['display_name_order']);
        }

        if (isset($details['display_pronouns'])) {
            $this->setDisplayPronouns($details['display_pronouns']);
        }

        $this->setLegalFamilyName($details['user_familyname']);
        if (isset($details['user_preferred_familyname'])) {
            $this->setPreferredFamilyName($details['user_preferred_familyname']);
        }

        $this->last_initial_format = isset($details['user_last_initial_format']) ? intval($details['user_last_initial_format']) : 0;
        if ($this->last_initial_format < 0 || $this->last_initial_format > 3) {
            $this->last_initial_format = 0;
        }

        $this->email = $details['user_email'];
        $this->secondary_email = $details['user_email_secondary'];
        $this->email_both = $details['user_email_secondary_notify'];
        $this->group = isset($details['user_group']) ? intval($details['user_group']) : 4;
        if ($this->group > 4 || $this->group < 0) {
            $this->group = 4;
        }
        $this->access_level = isset($details['user_access_level']) ? intval($details['user_access_level']) : 3;
        if ($this->access_level > 3 || $this->access_level < 1) {
            $this->access_level = 3;
        }
        $this->enforce_single_session = isset($details['enforce_single_session']) && $details['enforce_single_session'] === true;
        $this->user_updated = isset($details['user_updated']) && $details['user_updated'] === true;
        $this->instructor_updated = isset($details['instructor_updated']) && $details['instructor_updated'] === true;

        //Other call to get notification settings??
        $this->notification_settings = self::constructNotificationSettings($details);

        $this->registration_section = isset($details['registration_section']) ? $details['registration_section'] : null;
        $this->course_section_id = isset($details['course_section_id']) ? $details['course_section_id'] : null;
        $this->rotating_section = isset($details['rotating_section']) ? intval($details['rotating_section']) : null;
        $this->manual_registration = isset($details['manual_registration']) && $details['manual_registration'] === true;
        if (isset($details['grading_registration_sections'])) {
            $this->setGradingRegistrationSections($details['grading_registration_sections']);
        }

        if (isset($details['display_image_state'])) {
            $this->display_image_state = $details['display_image_state'];
        }

        $this->time_zone = $details['time_zone'] ?? 'NOT_SET/NOT_SET';

        if (isset($details['user_preferred_locale'])) {
            $this->preferred_locale = $details['user_preferred_locale'];
            $this->core->getConfig()->setLocale($this->preferred_locale);
        }

        if (isset($details['registration_subsection'])) {
            $this->setRegistrationSubsection($details['registration_subsection']);
        }

        // Use registration type data or default to "graded" for students and "staff" for others
        $this->registration_type = $details['registration_type'] ?? ($this->group == 4 ? 'graded' : 'staff');
    }

    /**
     * Gets the message the user sets when seeking a team or a parter
     * @param string $g_id the gradeable where the user is seeking for a team
     * @return string, message if it exists or N/A if it doesn't
     */
    public function getSeekMessage($g_id): string {
        $ret = $this->core->getQueries()->getSeekMessageByUserId($g_id, $this->id);

        if (is_null($ret)) {
            return "N/A";
        }
        else {
            return $ret;
        }
    }

    /**
     * set true or false to variable display_pronouns
     * @param bool $new_display_pronouns new display_pronouns option
     */
    public function setDisplayPronouns(?bool $new_display_pronouns): void {
        if ($new_display_pronouns === null) {
            $this->display_pronouns = false;
        }
        else {
            $this->display_pronouns = $new_display_pronouns;
        }
    }

    /**
     * Set $this->time_zone
     * @param string $time_zone Appropriate time zone string from DateUtils::getAvailableTimeZones()
     * @return bool True if time zone was able to be updated, False otherwise
     */
    public function setTimeZone(string $time_zone): bool {

        // Validate the $time_zone string
        if (in_array($time_zone, DateUtils::getAvailableTimeZones())) {
            // Attempt to update database
            $result = $this->core->getQueries()->updateSubmittyUserTimeZone($this, $time_zone);

            // Return true if we were able to update the database
            if ($result === 1) {
                $this->time_zone = $time_zone;
                return true;
            }
        }

        return false;
    }

    /**
     * Get the UTC offset for this user's time zone.
     *
     * @return string The offset in hours and minutes, for example '+9:30' or '-4:00'
     */
    public function getUTCOffset(): string {
        return DateUtils::getUTCOffset($this->time_zone);
    }

    /**
     * Gets a \DateTimeZone instantiation for the user's time zone if they have one set, or the server time zone
     * if they don't.
     *
     * @return \DateTimeZone
     */
    public function getUsableTimeZone(): \DateTimeZone {
        if ($this->time_zone === 'NOT_SET/NOT_SET') {
            return $this->core->getConfig()->getTimezone();
        }
        else {
            return new \DateTimeZone($this->time_zone);
        }
    }

    /**
     * Get the user's time zone, in 'nice' format.  This simply returns a cleaner 'NOT SET' string when the
     * user has not set their time zone.
     *
     * @return string The user's PHP DateTimeZone identifier string or 'NOT SET'
     */
    public function getNiceFormatTimeZone(): string {
        return $this->time_zone === 'NOT_SET/NOT_SET' ? 'NOT SET' : $this->time_zone;
    }


    /**
     * Get the user's preferred locale.
     */
    public function getPreferredLocale(): string|null {
        return $this->preferred_locale;
    }

    /**
     * Update the user's preferred locale.
     *
     * @param string|null $locale The desired new locale, must be one of Core::getSupportedLocales()
     */
    public function setPreferredLocale(string|null $locale): void {
        if (is_null($locale) || in_array($locale, $this->core->getSupportedLocales())) {
            $this->core->getQueries()->updateSubmittyUserPreferredLocale($this, $locale);
            $this->preferred_locale = $locale;
        }
    }


    /**
     * Update the user's display image if they have uploaded a new one
     *
     * @param string $image_extension The extension, for example 'jpeg' or 'gif'
     * @param string $tmp_file_path The temporary path to the file, where it can be collected from, processed, and saved
     *                              elsewhere.
     * @return int PROFILE_IMG_SET_SUCCESS if the update was successful, PROFILE_IMG_QUOTA_EXHAUSTED if image upload quota of 50 has been exhausted, PROFILE_IMG_SET_FAILURE otherwise
     * @throws \ImagickException
     */
    public function setDisplayImage(string $image_extension, string $tmp_file_path): int {
        $image_saved = true;

        // Try saving image to its new spot in the file directory
        try {
            DisplayImage::saveUserImage($this->core, $this->id, $image_extension, $tmp_file_path, 'user_images');
        }
        catch (\Exception $exception) {
            $image_saved = false;
            if ($exception->getCode() === self::PROFILE_IMG_QUOTA_EXHAUSTED) {
                return self::PROFILE_IMG_QUOTA_EXHAUSTED;
            }
        }

        // Update the DB to 'preferred'
        if ($image_saved && $this->core->getQueries()->updateUserDisplayImageState($this->id, 'preferred')) {
            $this->display_image_state = 'preferred';
            return self::PROFILE_IMG_SET_SUCCESS;
        }

        return self::PROFILE_IMG_SET_FAILURE;
    }

    /**
     * Gets the user's DisplayImage object.
     *
     * @return DisplayImage|null The user's DisplayImage object, or null if an error occurred
     */
    public function getDisplayImage(): ?DisplayImage {
        try {
            $result = new DisplayImage($this->core, $this->id, $this->display_image_state);
        }
        catch (\Exception $exception) {
            $result = null;
        }

        return $result;
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

    public function isSuperUser() {
        return $this->access_level === self::LEVEL_SUPERUSER;
    }

    public function setPassword($password) {
        if (!empty($password)) {
            $info = password_get_info($password);
            if (empty($info['algo'])) {
                $this->password = password_hash($password, PASSWORD_DEFAULT);
            }
            else {
                $this->password = $password;
            }
        }
    }

    public function setLegalGivenName($name) {
        $this->legal_given_name = $name;
        $this->setDisplayedGivenName();
    }

    public function setLegalFamilyName($name) {
        $this->legal_family_name = $name;
        $this->setDisplayedFamilyName();
    }

    public function setLastInitialFormat(int $format) {
        if ($format < 0 || $format > 3) {
            throw new \InvalidArgumentException("Invalid format value specified");
        }
        $this->last_initial_format = $format;
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
     * Set the preferred given name of the loaded user (does not affect db. call updateUser.)
     * @param ?string $name
     */
    public function setPreferredGivenName($name) {
        $this->preferred_given_name = $name;
        $this->setDisplayedGivenName();
    }

    public function setPreferredFamilyName($name) {
        $this->preferred_family_name = $name;
        $this->setDisplayedFamilyName();
    }

    private function setDisplayedGivenName() {
        $this->displayed_given_name = (!empty($this->preferred_given_name)) ? $this->preferred_given_name : $this->legal_given_name;
    }

    private function setDisplayedFamilyName() {
        $this->displayed_family_name = (!empty($this->preferred_family_name)) ? $this->preferred_family_name : $this->legal_family_name;
    }

    public function getDisplayFullName() {
        return $this->getDisplayedGivenName() . ' ' . $this->getDisplayedFamilyName();
    }

    public function getDisplayAbbreviatedName(int $last_initial_format = -1): string {
        if ($last_initial_format < 0) {
            $last_initial_format = $this->getLastInitialFormat();
        }
        $last_initial = ' ';
        $family_name = $this->getDisplayedFamilyName();
        $format_name = self::LAST_INITIAL_FORMATS[$last_initial_format];
        switch ($format_name) {
            case 'Multi':
                $spaced = preg_split('/\s+/', $family_name, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($spaced as $part) {
                    $last_initial .= $part[0] . '.';
                }
                break;
            case 'Hyphen-Multi':
                $spaced = preg_split('/\s+/', $family_name, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($spaced as $part) {
                    $dashed = explode('-', $part);
                    $l = array_map(fn(string $part) => $part[0], $dashed);
                    $last_initial .= implode('-', $l) . '.';
                }
                break;
            case 'None':
                $last_initial = '';
                break;
            default:
                $last_initial .= $family_name[0] . '.';
                break;
        }
        return $this->getDisplayedGivenName() . $last_initial;
    }

    public function getDisplayLastInitialFormat(int $format = -1): string {
        if ($format < 0) {
            $format = $this->last_initial_format;
        }
        if ($format < 0 || $format > count(self::LAST_INITIAL_FORMATS)) {
            return '';
        }
        return self::LAST_INITIAL_FORMATS[$format];
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

    /**
     * Get gradeable-specific anon_id of a user
     * @param string $g_id
     */
    public function getAnonId($g_id) {
        if ($g_id === "") {
            return "";
        }
        if (array_key_exists($g_id, $this->anon_id_by_gradeable)) {
            $anon_id = $this->anon_id_by_gradeable[$g_id];
        }
        else {
            $anon_id = $this->core->getQueries()->getAnonId($this->id, $g_id);
            $anon_id = empty($anon_id) ? null : $anon_id[$this->getId()];
            $this->anon_id_by_gradeable[$g_id] = $anon_id;
        }
        if ($anon_id === null) {
            $alpha = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
            $anon_ids = $this->core->getQueries()->getAllAnonIdsByGradeable($g_id);
            $alpha_length = strlen($alpha) - 1;
            do {
                $random = "";
                for ($i = 0; $i < 15; $i++) {
                    // this throws an exception if there's no available source for generating
                    // random exists, but that shouldn't happen on our targeted endpoints (Ubuntu/Debian)
                    // so just ignore this fact
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $random .= $alpha[random_int(0, $alpha_length)];
                }
            } while (in_array($random, $anon_ids));
            $this->core->getQueries()->insertGradeableAnonId($this->id, $g_id, $random);
        }
        return $anon_id ?? $random ?? null;
    }

    /**
     * Checks $data to make sure it is acceptable for $field.
     *
     * @param string $field
     * @param mixed $data
     * @return bool
     */
    public static function validateUserData($field, $data): bool {

        switch ($field) {
            case 'user_id':
                 //Username / user_id must contain only lowercase alpha,
                 //numbers, underscores, hyphens
                return preg_match("~^[a-z0-9_\-]+$~", $data) === 1;
            case 'user_legal_givenname':
            case 'user_legal_familyname':
                //Given and family name must be alpha characters, latin chars,
                //white-space, or certain punctuation.
                return preg_match("~^[a-zA-ZÀ-ÖØ-Ýà-öø-ÿ'`\-\.\(\) ]+$~", $data) === 1;
            case 'user_pronouns':
                //pronouns may be "", alpha chars, latin chars, white-space,
                //certain punctuation AND between 0 and 30 chars.
                return preg_match("~^[a-zA-ZÀ-ÖØ-Ýà-öø-ÿ'`\-\.\(\)\\\/ ]{0,30}$~", $data) === 1;
            case 'user_preferred_givenname':
            case 'user_preferred_familyname':
                //Preferred given and family name may be "", alpha chars,
                //latin chars, white-space,
                //certain punctuation AND between 0 and 30 chars.
                return preg_match("~^[a-zA-ZÀ-ÖØ-Ýà-öø-ÿ'`\-\.\(\) ]{0,30}$~", $data) === 1;
            case 'user_email':
            case 'user_email_secondary':
                // emails are allowed to be the empty string...
                if ($data === "") {
                    return true;
                }
                // -- or ---
                // validate email address against email RFCs
                $validator = new EmailValidator();
                return $validator->isValid($data, new RFCValidation());
            case 'user_group':
                //user_group check is a digit between 1 - 4.
                return preg_match("~^[1-4]{1}$~", $data) === 1;
            case 'registration_section':
                //Registration section must contain only alpha (upper and lower permitted), numbers, underscores, hyphens.
                // AND between 0 and 20 chars.
                //"NULL" registration section should be validated as a datatype, not as a string.
                return preg_match("~^(?!^null$)[a-z0-9_\-]{1,20}$~i", $data) === 1 || is_null($data);
            case 'course_section_id':
                //Course Section Id section must contain only alpha (upper and lower permitted), numbers, underscores, hyphens.
                return preg_match("~^(?!^null$)[a-z0-9_\-]+$~i", $data) === 1 || is_null($data);
            case 'grading_assignments':
                // Grading assignments must be comma-separated registration sections (containing only alpha, numbers, underscores or hyphens).
                return preg_match("~^[0-9a-z_\-]+(,[0-9a-z_\-]+)*$~i", $data) === 1;
            case 'student_registration_type':
                // Student registration type must be one of either 'graded','audit', or 'withdrawn
                return preg_match("~^(graded|audit|withdrawn)$~", $data) === 1;
            case 'user_password':
                //Database password cannot be blank, no check on format
                return $data !== "";
            default:
                //$data can't be validated since $field is unknown. Notify developer with an exception (also protects data record integrity).
                $ex_field = '$field: ' . var_export(htmlentities($field), true);
                $ex_data = '$data: ' . var_export(htmlentities($data), true);
                throw new ValidationException('User::validateUserData() called with unknown $field.  See extra details, below.', [$ex_field, $ex_data]);
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
        $notification_settings['all_released_grades'] = $details['all_released_grades'] ?? true;
        $notification_settings['reply_in_post_thread_email'] = $details['reply_in_post_thread_email'] ?? false;
        $notification_settings['merge_threads_email'] = $details['merge_threads_email'] ?? false;
        $notification_settings['all_new_threads_email'] = $details['all_new_threads_email'] ?? false;
        $notification_settings['all_new_posts_email'] = $details['all_new_posts_email'] ?? false;
        $notification_settings['all_modifications_forum_email'] = $details['all_modifications_forum_email'] ?? false;
        $notification_settings['team_invite_email'] = $details['team_invite_email'] ?? true;
        $notification_settings['team_joined_email'] = $details['team_joined_email'] ?? true;
        $notification_settings['team_member_submission_email'] = $details['team_member_submission_email'] ?? true;
        $notification_settings['self_registration_email'] = $details['self_registration_email'] ?? true;
        $notification_settings['self_notification_email'] = $details['self_notification_email'] ?? false;
        $notification_settings['all_released_grades_email'] = $details['all_released_grades_email'] ?? true;
        return $notification_settings;
    }

    /**
     * Checks if the user is on ANY team for the given assignment
     */
    public function onTeam(string $gradeable_id): bool {
        $team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $this->id);
        return $team !== null;
    }

    /**
     * Checks if the user has invites to multiple teams for the given assignment
     */
    public function hasMultipleTeamInvites(string $gradeable_id): bool {
        return $this->core->getQueries()->getUserMultipleTeamInvites($gradeable_id, $this->id);
    }
}
