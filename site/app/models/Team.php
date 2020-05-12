<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\FileUtils;
use app\models\gradeable\Gradeable;

/**
 * Class Team
 *
 * @method string getId()
 * @method string[] getMemberUserIds()
 * @method string[] getInvitedUserIds()
 * @method User[] getMemberUsers()
 * @method User[] getInvitedUsers()
 */
class Team extends AbstractModel {

    /** @prop @var string The id of this team of form "<unique number>_<creator user id>" */
    protected $id;
    /** @prop @var integer rotating section (registration or rotating) of team creator */
    protected $registration_section;
    /** @prop @var integer registration section (registration or rotating) of team creator */
    protected $rotating_section;
    /** @prop @var string[] containing user ids of team members */
    protected $member_user_ids;
    /** @prop @var string[] containing user ids of those invited to the team */
    protected $invited_user_ids;
    /** @prop @var User[] containing users of team members */
    protected $member_users;
    /** @prop @var User[] containing users of those invited to the team */
    protected $invited_users;
    /** @prop @var string containing comma-seperated list of team members */
    protected $member_list;
    /** @var array $assignment_settings */
    protected $assignment_settings;

    /**
     * Team constructor.
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $details) {
        parent::__construct($core);

        $this->id = $details['team_id'];
        $this->registration_section = $details['registration_section'];
        $this->rotating_section = $details['rotating_section'];
        $this->member_user_ids = array();
        $this->invited_user_ids = array();
        $this->member_users = array();
        $this->invited_users = array();
        foreach ($details['users'] as $user_details) {
            //If we have user details, get user objects
            if (array_key_exists('anon_id', $user_details)) {
                $user = new User($core, $user_details);
            }
            else {
                $user = null;
            }
            if ($user_details['state'] === 1) {
                $this->member_user_ids[] = $user_details['user_id'];
                if ($user !== null) {
                    $this->member_users[] = $user;
                }
            }
            elseif ($user_details['state'] === 0) {
                $this->invited_user_ids[] = $user_details['user_id'];
                if ($user !== null) {
                    $this->invited_users[] = $user;
                }
            }
        }
        $this->member_list = count($this->member_user_ids) === 0 ? "[empty team]" : implode(", ", $this->member_user_ids);
    }

    /**
     * Gets the anonymous id of this team
     * TODO: this is to create symmetry with the User class, teams' anon ids are just their ids for now
     * @return string
     */
    public function getAnonId() {
        return $this->id;
    }

    /**
     * Get registration section
     * @return integer
    */
    public function getRegistrationSection() {
        return $this->registration_section;
    }

    /**
     * Get rotating section
     * @return integer
    */
    public function getRotatingSection() {
        return $this->rotating_section;
    }

    /**
     * Get user ids of team members
     * @return string[]
    */
    public function getMembers() {
        return $this->member_user_ids;
    }

    /**
     * Get users of team, sorted by id
     * @return User[]
    */
    public function getMemberUsersSorted() {
        $ret = $this->member_users;
        usort($ret, function ($a, $b) {
            return strcmp($a->getId(), $b->getId());
        });
        return $ret;
    }

    /**
     * Get user ids of those invited to the team
     * @return string[]
    */
    public function getInvitations() {
        return $this->invited_user_ids;
    }

    /**
     * Get string list of team members
     * @return string
    */
    public function getMemberList() {
        return $this->member_list;
    }

    /**
     * Get number of users in team
     * @return integer
    */
    public function getSize() {
        return count($this->member_user_ids);
    }

    /**
     * Get the user id of the leader of the team
     * @return string|null
     */
    public function getLeaderId() {
        if (count($this->getMembers()) === 0) {
            return null;
        }
        return $this->getMembers()[0];
    }

    /**
     * Get whether or not a given user is on the team
     * @param string $user_id
     * @return bool
     */
    public function hasMember($user_id) {
        return in_array($user_id, $this->member_user_ids);
    }

    /**
     * Get whether or not a given user invited to the team
     * @param string $user_id
     * @return bool
     */
    public function sentInvite($user_id) {
        return in_array($user_id, $this->invited_user_ids);
    }

    /**
     * Gets the data from a team's user_assignment_settings.json file
     * @param Gradeable $gradeable
     * @return array
     */
    public function getAssignmentSettings(Gradeable $gradeable) {
        if ($this->assignment_settings === null) {
            $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable->getId(), $this->getId(), "user_assignment_settings.json");
            $this->assignment_settings = FileUtils::readJsonFile($settings_file);
        }
        return $this->assignment_settings;
    }
}
