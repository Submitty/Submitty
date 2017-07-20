<?php

namespace app\models;
use app\libraries\Core;

/**
 * Class Team
 *
 * @method string getId()
 */
class Team extends AbstractModel {
     
    /** @property @var string The id of this team of form "<unique number>_<creator user id>" */
    protected $id;
    /** @property @var array containing user ids of team members */
    protected $member_user_ids;
    /** @propety @var array containing user ids of those invited to the team */
    protected $invited_user_ids;
    /** @property @var string containing comma-seperated list of team members */
    protected $member_list;

    /**
     * Team constructor.
     * @parma Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $details) {
        parent::__construct($core);

        $this->id = $details[0]['team_id'];
        $this->member_user_ids = array();
        $this->invited_user_ids = array();
        foreach($details as $user) {
            if ($user['state'] === 1) {
                $this->member_user_ids[] = $user['user_id'];
            }
            else {
                $this->invited_user_ids[] = $user['user_id'];
            }
        }
        $this->member_list = count($this->member_user_ids) === 0 ? "[empty team]" : implode(", ", $this->member_user_ids);
    }

    /**
     * Get user ids of team members
     * @return array(string)
    */
    public function getMembers() {
        return $this->member_user_ids;
    }

    /**
     * Get user ids of those invited to the team
     * @return array(string)
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
}
